<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds a teacher's personal weekly teaching schedule.
 *
 * The school timetable is stored per class; a teacher's schedule is therefore
 * the union of the timetable slots whose (class, subject) pair matches one of
 * their teaching assignments for the academic year. Because a teacher can be
 * assigned to several classes, two slots can land on the same day and time —
 * those clashes are detected and returned so the teacher (and the office) can
 * see them instead of discovering them in front of a classroom.
 */
class TeacherTimetableService
{
    /**
     * @return array<string, mixed>
     */
    public function forTeacher(Teacher $teacher, ?AcademicYear $year = null): array
    {
        $year ??= AcademicYear::current();

        abort_unless($year, 409, 'No active academic year is configured.');

        $assignments = $teacher->teachingAssignments()
            ->where('academic_year_id', $year->id)
            ->get(['id', 'class_id', 'subject_id']);

        if ($assignments->isEmpty()) {
            return $this->emptyPayload($year);
        }

        $pairs = $assignments
            ->map(fn ($assignment) => $assignment->class_id.':'.$assignment->subject_id)
            ->flip();

        $entries = TimetableEntry::query()
            ->with(['subject:id,name', 'schoolClass:id,name'])
            ->where('school_id', $teacher->school_id)
            ->where('academic_year_id', $year->id)
            ->whereIn('class_id', $assignments->pluck('class_id')->unique()->all())
            ->whereIn('subject_id', $assignments->pluck('subject_id')->unique()->all())
            ->orderBy('day')
            ->orderBy('start')
            ->get()
            // whereIn on both columns is a cross product, so keep only the
            // combinations this teacher is actually assigned to.
            ->filter(fn (TimetableEntry $entry) => $pairs->has($entry->class_id.':'.$entry->subject_id))
            ->values()
            ->map(fn (TimetableEntry $entry) => [
                'id' => $entry->id,
                'day' => (int) $entry->day,
                'start' => $this->time($entry->start),
                'end' => $this->time($entry->end),
                'duration_minutes' => $this->minutes($entry->start, $entry->end),
                'subject' => [
                    'id' => $entry->subject?->id,
                    'name' => $entry->subject?->name,
                ],
                'class' => [
                    'id' => $entry->schoolClass?->id,
                    'name' => $entry->schoolClass?->name,
                ],
            ]);

        return [
            'academic_year' => $year->only(['id', 'name', 'start_date', 'end_date']),
            'entries' => $entries->all(),
            'summary' => $this->summary($entries),
            'today' => $this->today($entries)->all(),
            'next' => $this->next($entries),
            'conflicts' => $this->conflicts($entries),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(AcademicYear $year): array
    {
        return [
            'academic_year' => $year->only(['id', 'name', 'start_date', 'end_date']),
            'entries' => [],
            'summary' => [
                'lessons' => 0,
                'classes' => 0,
                'subjects' => 0,
                'minutes_per_week' => 0,
                'hours_per_week' => 0.0,
                'busiest_day' => null,
            ],
            'today' => [],
            'next' => null,
            'conflicts' => [],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $entries
     * @return array<string, mixed>
     */
    private function summary(Collection $entries): array
    {
        $minutes = (int) $entries->sum('duration_minutes');

        $perDay = $entries->groupBy('day')->map->count();

        return [
            'lessons' => $entries->count(),
            'classes' => $entries->pluck('class.id')->filter()->unique()->count(),
            'subjects' => $entries->pluck('subject.id')->filter()->unique()->count(),
            'minutes_per_week' => $minutes,
            'hours_per_week' => round($minutes / 60, 1),
            'busiest_day' => $perDay->isEmpty() ? null : (int) $perDay->sortDesc()->keys()->first(),
        ];
    }

    /**
     * Lessons scheduled for today, in chronological order.
     *
     * @param  Collection<int, array<string, mixed>>  $entries
     * @return Collection<int, array<string, mixed>>
     */
    private function today(Collection $entries): Collection
    {
        $today = (int) Carbon::now()->isoWeekday();

        return $entries->where('day', $today)->sortBy('start')->values();
    }

    /**
     * The next lesson from now, looking forward through the rest of the week
     * and wrapping around to the start of the next one.
     *
     * @param  Collection<int, array<string, mixed>>  $entries
     * @return array<string, mixed>|null
     */
    private function next(Collection $entries): ?array
    {
        if ($entries->isEmpty()) {
            return null;
        }

        $now = Carbon::now();
        $day = (int) $now->isoWeekday();
        $time = $now->format('H:i');

        $sorted = $entries->sortBy(fn (array $entry) => sprintf('%d%s', $entry['day'], $entry['start']))->values();

        $upcoming = $sorted->first(
            fn (array $entry) => $entry['day'] > $day || ($entry['day'] === $day && $entry['start'] > $time)
        );

        return $upcoming ?? $sorted->first();
    }

    /**
     * Overlapping lessons: the same teacher expected in two rooms at once.
     *
     * @param  Collection<int, array<string, mixed>>  $entries
     * @return array<int, array<string, mixed>>
     */
    private function conflicts(Collection $entries): array
    {
        return $entries
            ->groupBy(fn (array $entry) => $entry['day'].'|'.$entry['start'])
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->map(fn (Collection $group, string $key) => [
                'day' => (int) explode('|', $key)[0],
                'start' => explode('|', $key)[1],
                'entries' => $group->values()->all(),
            ])
            ->values()
            ->all();
    }

    private function time(mixed $value): string
    {
        return substr((string) $value, 0, 5);
    }

    private function minutes(mixed $start, mixed $end): int
    {
        [$startHour, $startMinute] = array_pad(explode(':', $this->time($start)), 2, '0');
        [$endHour, $endMinute] = array_pad(explode(':', $this->time($end)), 2, '0');

        $minutes = (((int) $endHour * 60) + (int) $endMinute) - (((int) $startHour * 60) + (int) $startMinute);

        return max($minutes, 0);
    }
}
