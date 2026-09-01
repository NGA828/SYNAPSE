<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TimetableEntry;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TimetableService
{
    /**
     * Normalised timetable entries for a class + year.
     *
     * @return Collection<int, array{id: int, day: int, start: string, end: string, subject: array{id: int, name: string}}>
     */
    public function entriesFor(SchoolClass $class, ?AcademicYear $year = null): Collection
    {
        $year ??= AcademicYear::current();

        abort_unless($year, 409, 'No active academic year is configured.');

        return TimetableEntry::query()
            ->with('subject')
            ->where('class_id', $class->id)
            ->where('academic_year_id', $year->id)
            ->orderBy('day')
            ->orderBy('start')
            ->get()
            ->map(fn (TimetableEntry $entry) => [
                'id' => $entry->id,
                'day' => (int) $entry->day,
                'start' => substr((string) $entry->start, 0, 5),
                'end' => substr((string) $entry->end, 0, 5),
                'subject' => [
                    'id' => $entry->subject->id,
                    'name' => $entry->subject->name,
                ],
            ]);
    }

    public function create(array $data): TimetableEntry
    {
        $class = SchoolClass::findOrFail($data['class_id']);
        $year = AcademicYear::current();
        abort_unless($year, 409, 'No active academic year is configured.');
        abort_unless($class->school_id === $year->school_id, 422, 'The selected class is invalid.');
        abort_unless(Subject::query()->whereKey($data['subject_id'])->where('school_id', $class->school_id)->exists(), 422, 'The selected subject is invalid.');

        $this->assertNoOverlap($class, $year->id, $data, null);

        return TimetableEntry::create([
            ...$data,
            'school_id' => $class->school_id,
            'academic_year_id' => $year->id,
        ])->load('subject');
    }

    public function update(TimetableEntry $entry, array $data): TimetableEntry
    {
        $class = SchoolClass::findOrFail($data['class_id']);
        abort_unless($class->school_id === $entry->school_id, 422, 'The selected class is invalid.');
        abort_unless(Subject::query()->whereKey($data['subject_id'])->where('school_id', $entry->school_id)->exists(), 422, 'The selected subject is invalid.');

        $this->assertNoOverlap($class, $entry->academic_year_id, $data, $entry->id);

        $entry->update($data);

        return $entry->fresh('subject');
    }

    public function delete(TimetableEntry $entry): void
    {
        $entry->delete();
    }

    /**
     * Reject a slot that overlaps one the class already has.
     *
     * The `timetable_slot_unique` index only covers an identical start, so a
     * class could hold Mathematics 08:00–10:00 and English 09:00–11:00 on the
     * same Monday and the database would accept both. Pupils cannot be in two
     * rooms at once, so the service refuses instead.
     *
     * Comparison is done on minutes since midnight rather than on the raw
     * strings: the column is a `time` and reads back as `H:i:s` while the request
     * supplies `H:i`, and a lexical comparison across those two forms is wrong at
     * the boundary.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertNoOverlap(SchoolClass $class, int $yearId, array $data, ?int $ignoreId): void
    {
        $start = $this->toMinutes($data['start'] ?? null);
        $end = $this->toMinutes($data['end'] ?? null);

        if ($start === null || $end === null) {
            throw ValidationException::withMessages(['start' => 'Both a start and an end time are required.']);
        }

        if ($end <= $start) {
            throw ValidationException::withMessages(['end' => 'The end time must be after the start time.']);
        }

        $clash = TimetableEntry::query()
            ->with('subject')
            ->where('class_id', $class->id)
            ->where('academic_year_id', $yearId)
            ->where('day', (int) $data['day'])
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get()
            // Two intervals overlap when each starts before the other ends. Back
            // to back is not an overlap: a class may finish at 10:00 and start
            // again at 10:00.
            ->first(fn (TimetableEntry $existing) => $start < $this->toMinutes($existing->end)
                && $end > $this->toMinutes($existing->start));

        if (! $clash) {
            return;
        }

        throw ValidationException::withMessages([
            'start' => sprintf(
                'That clashes with %s, which already occupies %s–%s on this day.',
                $clash->subject->name ?? 'another lesson',
                substr((string) $clash->start, 0, 5),
                substr((string) $clash->end, 0, 5),
            ),
        ]);
    }

    /**
     * Minutes since midnight, or null when the value is not a time.
     */
    private function toMinutes(mixed $value): ?int
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        if (! preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim((string) $value), $matches)) {
            return null;
        }

        return ((int) $matches[1]) * 60 + (int) $matches[2];
    }
}
