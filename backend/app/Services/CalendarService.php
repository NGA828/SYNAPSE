<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Event;
use App\Models\Exam;
use App\Models\HomeworkAssignment;
use App\Models\Quiz;
use App\Models\TimetableEntry;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One personal calendar built from data that already exists.
 *
 * A student's week is currently spread across five screens: the timetable, the
 * exam list, homework due dates, quiz deadlines and school events. This reads
 * all five and returns a single ordered list of uniformly-shaped items, so the
 * frontend renders one list and a user answers "what do I have on Thursday?"
 * in one place.
 *
 * Nothing here is writable — it is a projection, and the source records stay
 * the only place a change can be made.
 *
 * What a user sees follows from what they own:
 *
 * - a student sees the one class they are enrolled in, every subject in it;
 * - a teacher sees every class/subject pair they are assigned, so a teacher
 *   with three classes gets all three rather than just the first;
 * - an administrator sees school events only, since they hold no timetable.
 */
class CalendarService
{
    /**
     * Widest expansion the service will walk, so a malformed range cannot loop
     * for ever.
     */
    private const MAX_DAYS = 100;

    public function __construct(
        private readonly AcademicScopeService $academicScope,
    ) {}

    /**
     * Items between two dates, for whoever is asking.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function itemsFor(User $user, string $from, string $to): Collection
    {
        $year = AcademicYear::current();

        if (! $year) {
            return collect();
        }

        $scope = $this->scopeFor($user, $year);

        $items = $this->events($user, $from, $to);

        if ($scope !== null) {
            $items = $items
                ->concat($this->lessons($user, $scope, $year->id, $from, $to))
                ->concat($this->exams($user, $scope, $year->id, $from, $to))
                ->concat($this->homework($user, $scope, $year->id, $from, $to))
                ->concat($this->quizzes($user, $scope, $year->id, $from, $to));
        }

        return $items
            ->sortBy([
                fn ($a, $b) => strcmp((string) $a['starts_at'], (string) $b['starts_at']),
                fn ($a, $b) => strcmp((string) $a['title'], (string) $b['title']),
            ])
            ->values();
    }

    /**
     * Today's items only — the dashboard strip.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function todayFor(User $user): Collection
    {
        return $this->itemsFor($user, now()->toDateString(), now()->toDateString());
    }

    // ------------------------------------------------------------------ scope

    /**
     * The class/subject pairs this user's calendar is built from. Delegated to
     * AcademicScopeService so the pastoral register and the calendar can never
     * disagree about what a teacher owns.
     *
     * @return array{classes: list<int>, pairs: list<string>, is_teacher: bool}|null
     */
    private function scopeFor(User $user, AcademicYear $year): ?array
    {
        return $this->academicScope->for($user, $year);
    }

    // ------------------------------------------------------------------ parts

    /**
     * Weekly timetable entries, expanded onto every matching weekday in the
     * window. A recurring row is not a calendar item until it is dated.
     *
     * @param  array{classes: list<int>, pairs: list<string>, is_teacher: bool}  $scope
     * @return Collection<int, array<string, mixed>>
     */
    private function lessons(User $user, array $scope, int $yearId, string $from, string $to): Collection
    {
        $query = TimetableEntry::query()
            ->with(['subject', 'schoolClass'])
            ->where('academic_year_id', $yearId);

        // A teacher holds specific class/subject pairs; a student takes
        // whatever their class is taught.
        $query = $this->academicScope->applyToPairs($query, $scope);

        return $this->expandRecurring(
            $query->get(),
            $from,
            $to,
            fn (TimetableEntry $entry, string $date) => [
                'kind' => 'lesson',
                'id' => $entry->id,
                'title' => $entry->subject?->name ?? 'Lesson',
                'subtitle' => $entry->schoolClass?->name,
                'starts_at' => "{$date}T{$entry->start}:00",
                'ends_at' => "{$date}T{$entry->end}:00",
                'all_day' => false,
                'url' => $user->role === User::ROLE_STUDENT ? '/student/timetable' : '/teacher/timetable',
            ],
        );
    }

    /**
     * @param  array{classes: list<int>, pairs: list<string>, is_teacher: bool}  $scope
     * @return Collection<int, array<string, mixed>>
     */
    private function exams(User $user, array $scope, int $yearId, string $from, string $to): Collection
    {
        $query = Exam::query()
            ->with(['subject', 'schoolClass'])
            ->where('academic_year_id', $yearId)
            ->whereIn('class_id', $scope['classes'])
            ->whereBetween('date', [$from, $to]);

        // A teacher only invigilates the subjects they teach.
        if ($scope['is_teacher']) {
            $query->whereIn(
                'subject_id',
                collect($scope['pairs'])->map(fn (string $pair) => $this->academicScope->subjectFromPair($pair))->unique()->all(),
            );
        }

        return $query->get()->map(fn (Exam $exam) => [
            'kind' => 'exam',
            'id' => $exam->id,
            'title' => ($exam->subject?->name ?? 'Exam').' exam',
            'subtitle' => $exam->room ?: $exam->schoolClass?->name,
            // `date` is cast to `date`, so it is a Carbon instance rather than
            // the raw string stored in the column.
            'starts_at' => "{$exam->date->toDateString()}T{$exam->start}:00",
            'ends_at' => "{$exam->date->toDateString()}T{$exam->end}:00",
            'all_day' => false,
            'url' => $user->role === User::ROLE_STUDENT ? '/student/exams' : '/teacher/exams',
        ]);
    }

    /**
     * @param  array{classes: list<int>, pairs: list<string>, is_teacher: bool}  $scope
     * @return Collection<int, array<string, mixed>>
     */
    private function homework(User $user, array $scope, int $yearId, string $from, string $to): Collection
    {
        return $this->scopedAcademic(
            HomeworkAssignment::query()
                ->with('subject')
                ->where('academic_year_id', $yearId)
                ->published()
                ->whereBetween('due_at', [
                    Carbon::parse($from)->startOfDay(),
                    Carbon::parse($to)->endOfDay(),
                ]),
            $scope,
        )
            ->get()
            ->map(fn (HomeworkAssignment $homework) => [
                'kind' => 'homework',
                'id' => $homework->id,
                'title' => $homework->title,
                'subtitle' => $homework->subject?->name.' due',
                'starts_at' => $homework->due_at->toIso8601String(),
                'ends_at' => $homework->due_at->toIso8601String(),
                'all_day' => false,
                'url' => $this->urlFor($user, '/student/homework', '/teacher/homework'),
            ]);
    }

    /**
     * @param  array{classes: list<int>, pairs: list<string>, is_teacher: bool}  $scope
     * @return Collection<int, array<string, mixed>>
     */
    private function quizzes(User $user, array $scope, int $yearId, string $from, string $to): Collection
    {
        return $this->scopedAcademic(
            Quiz::query()
                ->with('subject')
                ->where('academic_year_id', $yearId)
                ->published()
                ->whereNotNull('closes_at')
                ->whereBetween('closes_at', [
                    Carbon::parse($from)->startOfDay(),
                    Carbon::parse($to)->endOfDay(),
                ]),
            $scope,
        )
            ->get()
            ->map(fn (Quiz $quiz) => [
                'kind' => 'quiz',
                'id' => $quiz->id,
                'title' => $quiz->title,
                'subtitle' => $quiz->subject?->name.' closes',
                'starts_at' => $quiz->closes_at->toIso8601String(),
                'ends_at' => $quiz->closes_at->toIso8601String(),
                'all_day' => false,
                'url' => $this->urlFor($user, '/student/quizzes', '/teacher/quizzes'),
            ]);
    }

    /**
     * Restrict a homework/quiz query to the caller's scope. A teacher sees only
     * the class/subject combinations they own, not the whole class list.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TModel>  $query
     * @param  array{classes: list<int>, pairs: list<string>, is_teacher: bool}  $scope
     * @return \Illuminate\Database\Eloquent\Builder<TModel>
     */
    private function scopedAcademic($query, array $scope)
    {
        return $this->academicScope->applyToPairs($query, $scope);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function events(User $user, string $from, string $to): Collection
    {
        return Event::query()
            ->published()
            ->visibleToRole($user->role)
            ->between($from, $to)
            ->get()
            ->map(fn (Event $event) => [
                'kind' => 'event',
                'id' => $event->id,
                'title' => $event->title,
                'subtitle' => $event->location ?: ucfirst($event->type),
                'starts_at' => $event->starts_at->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
                'all_day' => $event->all_day,
                'url' => null,
            ]);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param  Collection<int, TimetableEntry>  $entries
     * @return Collection<int, array<string, mixed>>
     */
    private function expandRecurring(Collection $entries, string $from, string $to, callable $shape): Collection
    {
        $items = collect();

        $cursor = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        $guard = 0;

        while ($cursor->lte($end) && $guard < self::MAX_DAYS) {
            // ISO weekday: Monday 1 … Sunday 7, which is how `day` is stored.
            $weekday = $cursor->dayOfWeekIso;

            foreach ($entries as $entry) {
                if ((int) $entry->day === $weekday) {
                    $items->push($shape($entry, $cursor->toDateString()));
                }
            }

            $cursor->addDay();
            $guard++;
        }

        return $items;
    }

    private function urlFor(User $user, string $student, string $teacher): string
    {
        return $user->role === User::ROLE_STUDENT ? $student : $teacher;
    }
}
