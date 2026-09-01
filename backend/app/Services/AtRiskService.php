<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkSubmission;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Support\AtRiskSignal;
use Illuminate\Support\Collection;

/**
 * The pastoral register: which students need attention this week, and why.
 *
 * A risk flag is never stored. It is recomputed from the records that already
 * exist, because a stored flag goes stale the moment a teacher enters a grade
 * and nobody remembers to regenerate it. Every entry therefore carries the
 * signals that produced it, in words, so a form teacher can act on it rather
 * than merely being told a number went down.
 *
 * Who sees whom:
 *
 * - an administrator sees every student in the school;
 * - a teacher sees the students enrolled in their classes, and the academic
 *   signals are computed only over the subjects that teacher teaches (a maths
 *   teacher should not be shown the English homework set by someone else),
 *   while attendance is school-wide because attendance is not subject-specific;
 * - a student sees their own signals, phrased as what to work on.
 *
 * There is no parent role in this system, so there is no parent view.
 */
class AtRiskService
{
    public function __construct(
        private readonly AcademicScopeService $academicScope,
    ) {}

    /**
     * Every flagged student the caller may see, worst first.
     *
     * @param  array{class_id?: int, severity?: string, search?: string}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function register(User $viewer, array $filters = []): Collection
    {
        $students = $this->studentsFor($viewer, $filters);

        if ($students->isEmpty()) {
            return collect();
        }

        $bundles = $this->bundles($students->pluck('id')->all(), $viewer);

        $severity = $filters['severity'] ?? null;

        return $students
            ->map(fn (Student $student) => $this->assess($student, $bundles))
            ->filter(fn (array $entry) => $entry['signals'] !== [])
            ->when(
                in_array($severity, [AtRiskSignal::SEVERITY_WARNING, AtRiskSignal::SEVERITY_CRITICAL], true),
                fn (Collection $entries) => $entries->filter(fn (array $entry) => $entry['severity'] === $severity),
            )
            ->sort(fn (array $a, array $b) => $this->compareEntries($a, $b))
            ->values();
    }

    /**
     * Worst first: critical before warning, then the lowest average, then the
     * name so the list does not shuffle between page loads.
     *
     * A single comparator rather than sortBy([...]) — one obvious ordering,
     * and the tie-break is explicit.
     *
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function compareEntries(array $a, array $b): int
    {
        $rank = static fn (array $entry): int => $entry['severity'] === AtRiskSignal::SEVERITY_CRITICAL ? 0 : 1;

        $bySeverity = $rank($a) <=> $rank($b);

        if ($bySeverity !== 0) {
            return $bySeverity;
        }

        // A missing average sorts last: no data is not the worst possible mark.
        $byAverage = ($a['average'] ?? PHP_FLOAT_MAX) <=> ($b['average'] ?? PHP_FLOAT_MAX);

        if ($byAverage !== 0) {
            return $byAverage;
        }

        return strcmp($a['student']['name'], $b['student']['name']);
    }

    /**
     * One student in full, for the detail view.
     *
     * @return array<string, mixed>
     */
    public function profile(User $viewer, Student $student): array
    {
        $this->assertVisible($viewer, $student);

        return $this->assess($student, $this->bundles([$student->id], $viewer));
    }

    /**
     * A student's own signals.
     *
     * The same computation as the register, so what a student is told about
     * themselves cannot drift from what their form teacher sees. Only the
     * framing differs: the detail is written as something to work on.
     *
     * @return array<string, mixed>
     */
    public function mine(User $student): array
    {
        $profile = Student::query()->where('user_id', $student->id)->first();

        if (! $profile) {
            return ['signals' => [], 'average' => null, 'severity' => null, 'attendance' => null, 'student' => null];
        }

        return $this->assess($profile, $this->bundles([$profile->id], $student));
    }

    /**
     * Counts for the dashboard strip.
     *
     * @return array<string, mixed>
     */
    public function summary(User $viewer): array
    {
        $students = $this->studentsFor($viewer);

        if ($students->isEmpty()) {
            return ['flagged' => 0, 'critical' => 0, 'warning' => 0, 'monitored' => 0, 'by_class' => []];
        }

        $bundles = $this->bundles($students->pluck('id')->all(), $viewer);

        $assessed = $students->map(fn (Student $student) => $this->assess($student, $bundles));
        $flagged = $assessed->filter(fn (array $entry) => $entry['signals'] !== []);

        return [
            'flagged' => $flagged->count(),
            'critical' => $flagged->where('severity', AtRiskSignal::SEVERITY_CRITICAL)->count(),
            'warning' => $flagged->where('severity', AtRiskSignal::SEVERITY_WARNING)->count(),
            'monitored' => $students->count() - $flagged->count(),

            // Which classes need the most attention, so an admin knows where
            // to look first without paging through every student.
            'by_class' => $flagged
                ->groupBy(fn (array $entry) => $entry['student']['class']['name'] ?? 'Unassigned')
                ->map(fn (Collection $rows) => $rows->count())
                ->sortDesc()
                ->map(fn (int $count, string $class) => ['label' => $class, 'value' => $count])
                ->values()
                ->all(),
        ];
    }

    // ----------------------------------------------------------------- inputs

    /**
     * The students this caller may assess.
     *
     * @param  array{class_id?: int, severity?: string, search?: string}  $filters
     * @return Collection<int, Student>
     */
    private function studentsFor(User $viewer, array $filters = []): Collection
    {
        $year = AcademicYear::current();

        if (! $year) {
            return collect();
        }

        $query = Student::query()->with(['user', 'enrollments.schoolClass']);

        if ($viewer->role === User::ROLE_STUDENT) {
            $query->where('user_id', $viewer->id);
        } else {
            $scope = $this->academicScope->for($viewer, $year);

            if (! $scope) {
                return collect();
            }

            // A teacher sees the students in their classes, not the whole school.
            $query->whereIn(
                'id',
                Enrollment::query()
                    ->where('academic_year_id', $year->id)
                    ->whereIn('class_id', $scope['classes'])
                    ->select('student_id'),
            );
        }

        if (! empty($filters['class_id'])) {
            $query->whereIn(
                'id',
                Enrollment::query()
                    ->where('academic_year_id', $year->id)
                    ->where('class_id', (int) $filters['class_id'])
                    ->select('student_id'),
            );
        }

        if (! empty($filters['search'])) {
            $term = trim((string) $filters['search']);

            $query->whereHas(
                'user',
                fn ($inner) => $inner->where('name', 'like', "%{$term}%"),
            );
        }

        return $query->orderBy('id')->get();
    }

    /**
     * Everything needed to assess these students, fetched in bulk.
     *
     * Aggregating once per signal rather than once per student is what keeps the
     * register usable in a school with a few hundred pupils.
     *
     * @param  list<int>  $studentIds
     * @return array<string, mixed>
     */
    private function bundles(array $studentIds, User $viewer): array
    {
        $year = AcademicYear::current();
        $scope = $viewer->role === User::ROLE_ADMIN ? null : $this->academicScope->for($viewer, $year);

        return [
            'grades' => $this->gradeBundle($studentIds, $scope),
            'attendance' => $this->attendanceBundle($studentIds),
            'homework' => $this->homeworkBundle($studentIds, $scope, $year?->id),
            'quizzes' => $this->quizBundle($studentIds, $scope, $year?->id),
            'semesters' => $this->semesterPair($year?->id),
        ];
    }

    /**
     * @param  list<int>  $studentIds
     * @param  array{classes: list<int>, pairs: list<string>, is_teacher: bool}|null  $scope
     * @return array<int, Collection<int, Grade>>
     */
    private function gradeBundle(array $studentIds, ?array $scope): array
    {
        $query = Grade::query()
            ->with(['scores.component', 'subject'])
            ->whereIn('student_id', $studentIds);

        // A teacher's academic signals cover only the subjects they teach.
        if ($scope && $scope['is_teacher']) {
            $query->whereIn('subject_id', array_map(
                fn (string $pair) => $this->academicScope->subjectFromPair($pair),
                $scope['pairs'],
            ));
        }

        return $query->get()->groupBy('student_id')->all();
    }

    /**
     * Attendance is school-wide: it is recorded per class per day and is not
     * subject-specific, so a teacher sees the whole picture for their students.
     *
     * @param  list<int>  $studentIds
     * @return array<int, array<string, int>>
     */
    private function attendanceBundle(array $studentIds): array
    {
        $rows = Attendance::query()
            ->whereIn('student_id', $studentIds)
            ->selectRaw('student_id, status, COUNT(*) as total')
            ->groupBy('student_id', 'status')
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row->student_id][$row->status] = (int) $row->total;
        }

        return $out;
    }

    /**
     * @param  list<int>  $studentIds
     * @param  array{classes: list<int>, pairs: list<string>, is_teacher: bool}|null  $scope
     * @return array<int, array{published: int, submitted: int, missing: int}>
     */
    private function homeworkBundle(array $studentIds, ?array $scope, ?int $yearId): array
    {
        $assignments = $this->scopedAssignments($scope, $yearId)->get();

        if ($assignments->isEmpty()) {
            return [];
        }

        $ids = $assignments->pluck('id')->all();

        $submissions = HomeworkSubmission::query()
            ->whereIn('homework_assignment_id', $ids)
            ->whereIn('student_id', $studentIds)
            ->whereNotNull('submitted_at')
            ->get(['student_id', 'homework_assignment_id']);

        $submittedBy = $submissions
            ->groupBy('student_id')
            ->map(fn (Collection $rows) => $rows->pluck('homework_assignment_id')->unique()->all())
            ->all();

        $now = now();
        $out = [];

        foreach ($studentIds as $studentId) {
            $submitted = $submittedBy[$studentId] ?? [];
            $missing = $assignments
                ->filter(fn (HomeworkAssignment $assignment) => ! in_array($assignment->id, $submitted, true))
                ->filter(fn (HomeworkAssignment $assignment) => $assignment->due_at !== null && $assignment->due_at->lt($now))
                ->count();

            $out[(int) $studentId] = [
                'published' => $assignments->count(),
                'submitted' => count($submitted),
                'missing' => $missing,
            ];
        }

        return $out;
    }

    /**
     * @param  array{classes: list<int>, pairs: list<string>, is_teacher: bool}|null  $scope
     */
    private function scopedAssignments(?array $scope, ?int $yearId)
    {
        $query = HomeworkAssignment::query()->published();

        if ($yearId) {
            $query->where('academic_year_id', $yearId);
        }

        return $scope ? $this->academicScope->applyToPairs($query, $scope) : $query;
    }

    /**
     * @param  list<int>  $studentIds
     * @param  array{classes: list<int>, pairs: list<string>, is_teacher: bool}|null  $scope
     * @return array<int, array{attempts: int, percentage: float|null}>
     */
    private function quizBundle(array $studentIds, ?array $scope, ?int $yearId): array
    {
        $quizzes = $this->scopedQuizzes($scope, $yearId)->get(['id', 'max_score']);

        if ($quizzes->isEmpty()) {
            return [];
        }

        $attempts = QuizAttempt::query()
            ->whereIn('quiz_id', $quizzes->pluck('id')->all())
            ->whereIn('student_id', $studentIds)
            ->whereNotNull('submitted_at')
            ->get(['student_id', 'quiz_id', 'score']);

        $maxScores = $quizzes->pluck('max_score', 'id');

        $out = [];

        foreach ($attempts->groupBy('student_id') as $studentId => $rows) {
            // Mean of each attempt as a percentage of its own paper, so a
            // 20-mark quiz and a 10-mark quiz weigh the same.
            $percentages = $rows
                ->map(function (QuizAttempt $attempt) use ($maxScores) {
                    $max = (float) ($maxScores[$attempt->quiz_id] ?? 0);

                    return $max > 0 && $attempt->score !== null
                        ? ((float) $attempt->score / $max) * 100
                        : null;
                })
                ->filter(fn ($value) => $value !== null);

            $out[(int) $studentId] = [
                'attempts' => $rows->count(),
                'percentage' => $percentages->isEmpty() ? null : round($percentages->avg(), 2),
            ];
        }

        return $out;
    }

    /**
     * @param  array{classes: list<int>, pairs: list<string>, is_teacher: bool}|null  $scope
     */
    private function scopedQuizzes(?array $scope, ?int $yearId)
    {
        $query = Quiz::query()->published();

        if ($yearId) {
            $query->where('academic_year_id', $yearId);
        }

        return $scope ? $this->academicScope->applyToPairs($query, $scope) : $query;
    }

    /**
     * The current and previous semester, for the trend signal.
     *
     * @return array{current: int|null, previous: int|null}
     */
    private function semesterPair(?int $yearId): array
    {
        if (! $yearId) {
            return ['current' => null, 'previous' => null];
        }

        $current = Semester::query()->where('academic_year_id', $yearId)->where('is_current', true)->first();

        $previous = $current
            ? Semester::query()
                ->where('academic_year_id', $yearId)
                ->where('sequence', $current->sequence - 1)
                ->first()
            : null;

        return [
            'current' => $current?->id,
            'previous' => $previous?->id,
        ];
    }

    // ------------------------------------------------------------- assessment

    /**
     * @param  array<string, mixed>  $bundles
     * @return array<string, mixed>
     */
    private function assess(Student $student, array $bundles): array
    {
        $id = (int) $student->id;
        $class = $this->currentClass($student);

        /** @var Collection<int, Grade> $grades */
        $grades = collect($bundles['grades'][$id] ?? []);

        $averages = $grades
            ->map(fn (Grade $grade) => $grade->average)
            ->filter(fn ($value) => $value !== null)
            ->values();

        $overall = $averages->isEmpty() ? null : round($averages->avg(), 2);

        $signals = collect()
            ->concat($this->averageSignals($overall))
            ->concat($this->failingSubjectSignals($grades))
            ->concat($this->declineSignals($grades, $bundles['semesters']))
            ->concat($this->homeworkSignals($bundles['homework'][$id] ?? null))
            ->concat($this->attendanceSignals($bundles['attendance'][$id] ?? []))
            ->concat($this->quizSignals($bundles['quizzes'][$id] ?? null))
            ->all();

        return [
            // Duplicated from `student` deliberately: it is the list row key.
            'id' => $id,
            'student' => [
                'id' => $id,
                'name' => $student->user?->name ?? 'Unknown',
                'matricule' => $student->matricule,
                'class' => [
                    'id' => $class?->id,
                    'name' => $class?->name,
                ],
            ],
            'average' => $overall,
            'signals' => array_map(fn (AtRiskSignal $signal) => $signal->toArray(), $signals),
            'severity' => collect($signals)->contains(fn (AtRiskSignal $s) => $s->isCritical())
                ? AtRiskSignal::SEVERITY_CRITICAL
                : (count($signals) > 0 ? AtRiskSignal::SEVERITY_WARNING : null),
            'attendance' => $this->attendanceRate($bundles['attendance'][$id] ?? []),
            'homework' => $bundles['homework'][$id] ?? null,
            'quizzes' => $bundles['quizzes'][$id] ?? null,
        ];
    }

    /**
     * @return list<AtRiskSignal>
     */
    private function averageSignals(?float $overall): array
    {
        if ($overall === null) {
            return [];
        }

        $threshold = (float) ($this->config('average') ?? config('synapse.grading.pass_mark', 10));
        $margin = (float) $this->config('critical_margin');

        if ($overall >= $threshold) {
            return [];
        }

        return $overall < ($threshold - $margin)
            ? [AtRiskSignal::critical(
                'low_average',
                'Low average',
                "Term average is {$overall} out of 20, well below the pass mark of {$threshold}.",
            )]
            : [AtRiskSignal::warning(
                'low_average',
                'Low average',
                "Term average is {$overall} out of 20, just below the pass mark of {$threshold}.",
            )];
    }

    /**
     * @param  Collection<int, Grade>  $grades
     * @return list<AtRiskSignal>
     */
    private function failingSubjectSignals(Collection $grades): array
    {
        $pass = (float) config('synapse.grading.pass_mark', 10);
        $limit = (int) $this->config('failing_subjects');

        $failing = $grades
            ->map(fn (Grade $grade) => ['subject' => $grade->subject?->name ?? 'Subject', 'average' => $grade->average])
            ->filter(fn (array $row) => $row['average'] !== null && $row['average'] < $pass)
            ->values();

        if ($failing->count() < $limit) {
            return [];
        }

        $names = $failing->pluck('subject')->take(4)->implode(', ');

        return [AtRiskSignal::warning(
            'failing_subjects',
            'Failing subjects',
            "Below the pass mark in {$failing->count()} subject(s): {$names}.",
        )];
    }

    /**
     * A student slipping is more actionable than one who has always sat at the
     * same level, so a term-over-term drop is its own signal.
     *
     * @param  Collection<int, Grade>  $grades
     * @param  array{current: int|null, previous: int|null}  $semesters
     * @return list<AtRiskSignal>
     */
    private function declineSignals(Collection $grades, array $semesters): array
    {
        if (! $semesters['current'] || ! $semesters['previous']) {
            return [];
        }

        $meanFor = function (int $semesterId) use ($grades): ?float {
            $values = $grades
                ->filter(fn (Grade $grade) => (int) $grade->semester_id === $semesterId)
                ->map(fn (Grade $grade) => $grade->average)
                ->filter(fn ($value) => $value !== null);

            return $values->isEmpty() ? null : $values->avg();
        };

        $current = $meanFor((int) $semesters['current']);
        $previous = $meanFor((int) $semesters['previous']);

        if ($current === null || $previous === null) {
            return [];
        }

        $drop = round($previous - $current, 2);
        $required = (float) $this->config('decline_points');

        if ($drop < $required) {
            return [];
        }

        return [AtRiskSignal::warning(
            'declining',
            'Declining',
            "Average fell from ".round($previous, 2)." to ".round($current, 2)." between semesters ({$drop} points).",
        )];
    }

    /**
     * @param  array{published: int, submitted: int, missing: int}|null  $homework
     * @return list<AtRiskSignal>
     */
    private function homeworkSignals(?array $homework): array
    {
        if (! $homework || $homework['published'] === 0) {
            return [];
        }

        $signals = [];

        $missingLimit = (int) $this->config('missing_homework');

        if ($homework['missing'] >= $missingLimit) {
            $signals[] = AtRiskSignal::critical(
                'missing_homework',
                'Missing homework',
                "{$homework['missing']} published assignment(s) past their deadline with nothing submitted.",
            );
        }

        $rate = round(($homework['submitted'] / $homework['published']) * 100, 1);
        $threshold = (float) $this->config('submission_rate');

        if ($rate < $threshold) {
            $signals[] = AtRiskSignal::warning(
                'low_submission_rate',
                'Low submission rate',
                "Handed in {$homework['submitted']} of {$homework['published']} assignments ({$rate}%).",
            );
        }

        return $signals;
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<AtRiskSignal>
     */
    private function attendanceSignals(array $counts): array
    {
        $rate = $this->attendanceRate($counts);

        if ($rate === null) {
            return [];
        }

        $threshold = (float) $this->config('attendance_rate');

        if ($rate >= $threshold) {
            return [];
        }

        return [AtRiskSignal::warning(
            'poor_attendance',
            'Poor attendance',
            "Attendance is {$rate}% against a {$threshold}% expectation.",
        )];
    }

    /**
     * @param  array{attempts: int, percentage: float|null}|null  $quizzes
     * @return list<AtRiskSignal>
     */
    private function quizSignals(?array $quizzes): array
    {
        if (! $quizzes || $quizzes['percentage'] === null) {
            return [];
        }

        $threshold = (float) $this->config('quiz_average');

        if ($quizzes['percentage'] >= $threshold) {
            return [];
        }

        return [AtRiskSignal::warning(
            'low_quiz_average',
            'Low quiz scores',
            "Averaging {$quizzes['percentage']}% on auto-marked quizzes, below the {$threshold}% expectation.",
        )];
    }

    /**
     * Present or late, over present, late and absent. An excused absence sits
     * outside both sides: a medical absence is not a warning sign.
     *
     * @param  array<string, int>  $counts
     */
    private function attendanceRate(array $counts): ?float
    {
        $attended = ($counts[Attendance::PRESENT] ?? 0) + ($counts[Attendance::LATE] ?? 0);
        $counted = $attended + ($counts[Attendance::ABSENT] ?? 0);

        return $counted > 0 ? round(($attended / $counted) * 100, 1) : null;
    }

    private function assertVisible(User $viewer, Student $student): void
    {
        if ($viewer->role === User::ROLE_ADMIN) {
            abort_unless($student->school_id === $viewer->school_id, 403, 'That student belongs to another school.');

            return;
        }

        if ($viewer->role === User::ROLE_STUDENT) {
            abort_unless((int) $student->user_id === (int) $viewer->id, 403, 'You can only review your own record.');

            return;
        }

        $year = AcademicYear::current();
        $scope = $this->academicScope->for($viewer, $year);

        abort_if(! $scope, 403, 'You have no classes assigned this year.');

        $enrolled = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $year?->id)
            ->whereIn('class_id', $scope['classes'])
            ->exists();

        abort_unless($enrolled, 403, 'That student is not in one of your classes.');
    }

    /**
     * The class the student is enrolled in *this* year. A pupil has one
     * enrollment per year, so the relation cannot be used directly without
     * naming which year is meant.
     */
    private function currentClass(Student $student): ?SchoolClass
    {
        $yearId = AcademicYear::current()?->id;

        return $student->enrollments
            ->first(fn (Enrollment $enrollment) => (int) $enrollment->academic_year_id === (int) $yearId)
            ?->schoolClass;
    }

    private function config(string $key): mixed
    {
        return config("synapse.at_risk.{$key}");
    }
}
