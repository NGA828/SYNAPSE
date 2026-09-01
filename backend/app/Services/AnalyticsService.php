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
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * School-wide academic analytics.
 *
 * Everything here is read-only aggregation over records the earlier phases
 * already collect. The point is not a prettier dashboard: it is that an
 * administrator can answer "is Level 2A behind, and is that an attendance
 * problem or a teaching one?" without opening six screens and a spreadsheet.
 *
 * A teacher gets the same shape restricted to the classes they teach, so the
 * two views never disagree about how a number is defined.
 */
class AnalyticsService
{
    public function __construct(
        private readonly AcademicScopeService $academicScope,
        private readonly AtRiskService $atRisk,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(User $viewer): array
    {
        $year = AcademicYear::current();
        $scope = $viewer->role === User::ROLE_ADMIN ? null : $this->academicScope->for($viewer, $year);

        $studentIds = $this->studentIds($scope, $year?->id);

        return [
            'academic_year' => $year ? ['id' => $year->id, 'name' => $year->name] : null,
            'scope' => $scope
                ? ['type' => 'teacher', 'classes' => $this->classNames($scope)]
                : ['type' => 'school'],
            'counts' => $this->counts($scope, $year?->id, $studentIds),
            'performance' => $this->performance($scope, $studentIds),
            'by_class' => $this->byClass($scope, $year?->id),
            'distribution' => $this->distribution($scope, $studentIds),
            'attendance' => $this->attendance($scope, $studentIds),
            'engagement' => $this->engagement($scope, $year?->id, $studentIds),
            'at_risk' => $this->atRisk->summary($viewer),
        ];
    }

    // ----------------------------------------------------------------- parts

    /**
     * @param  array{classes: list<int>, pairs: list<string>, is_teacher: bool}|null  $scope
     * @return list<int>
     */
    private function studentIds(?array $scope, ?int $yearId): array
    {
        if (! $yearId) {
            return [];
        }

        $query = Enrollment::query()->where('academic_year_id', $yearId);

        if ($scope) {
            $query->whereIn('class_id', $scope['classes']);
        }

        return $query->pluck('student_id')->unique()->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param  array{classes: list<int>, pairs: list<string>, is_teacher: bool}|null  $scope
     * @return array<string, mixed>
     */
    private function counts(?array $scope, ?int $yearId, array $studentIds): array
    {
        if ($scope) {
            return [
                'students' => count($studentIds),
                'classes' => count($scope['classes']),
                'subjects' => count(array_unique(array_map(
                    fn (string $pair) => $this->academicScope->subjectFromPair($pair),
                    $scope['pairs'],
                ))),
                'teachers' => $this->teachersInClasses($scope),
            ];
        }

        return [
            'students' => Student::count(),
            'teachers' => Teacher::count(),
            'classes' => SchoolClass::count(),
            'subjects' => Subject::count(),
        ];
    }

    /**
     * @param  array{classes: list<int>, pairs: list<string>, is_teacher: bool}|null  $scope
     * @return array<string, mixed>
     */
    private function performance(?array $scope, array $studentIds): array
    {
        $averages = $this->studentAverages($scope, $studentIds);

        if ($averages->isEmpty()) {
            return ['average' => null, 'pass_rate' => null, 'graded_students' => 0];
        }

        $pass = (float) config('synapse.grading.pass_mark', 10);
        $passing = $averages->filter(fn (?float $value) => $value !== null && $value >= $pass)->count();

        return [
            'average' => round($averages->avg(), 2),
            'pass_rate' => round(($passing / $averages->count()) * 100, 1),
            'graded_students' => $averages->count(),
        ];
    }

    /**
     * @param  array{classes: list<int>, pairs: list<string>, is_teacher: bool}|null  $scope
     * @return list<array{label: string, value: float, students: int}>
     */
    private function byClass(?array $scope, ?int $yearId): array
    {
        if (! $yearId) {
            return [];
        }

        $classIds = $scope ? $scope['classes'] : SchoolClass::query()->pluck('id')->all();

        $grades = $this->gradeQuery($scope)->get();

        $enrolled = Enrollment::query()
            ->where('academic_year_id', $yearId)
            ->whereIn('class_id', $classIds)
            ->get(['class_id', 'student_id'])
            ->groupBy('class_id');

        $averages = $grades->groupBy('student_id')->map(function (Collection $rows) {
            $values = $rows->map(fn (Grade $grade) => $grade->average)->filter(fn ($v) => $v !== null);

            return $values->isEmpty() ? null : $values->avg();
        });

        $out = [];

        foreach (SchoolClass::query()->whereIn('id', $classIds)->orderBy('name')->get() as $class) {
            $students = ($enrolled[$class->id] ?? collect())->pluck('student_id')->map(fn ($id) => (int) $id)->all();

            // A class with nobody in it is not a finding, and reporting its
            // average as 0.0 would read as "this class is failing" rather than
            // "there is no data yet".
            if ($students === []) {
                continue;
            }

            $values = collect($students)
                ->map(fn (int $id) => $averages[$id] ?? null)
                ->filter(fn ($value) => $value !== null);

            $out[] = [
                'label' => $class->name,
                'value' => $values->isEmpty() ? null : round($values->avg(), 2),
                'students' => count($students),
            ];
        }

        return $out;
    }

    /**
     * Grade distribution against the configured mentions, so the buckets match
     * the labels already printed on report cards.
     *
     * @param  array{classes: list<int>, pairs: list<string>, is_teacher: bool}|null  $scope
     * @return list<array{label: string, value: int, dot?: string}>
     */
    private function distribution(?array $scope, array $studentIds): array
    {
        $averages = $this->studentAverages($scope, $studentIds)->filter(fn ($v) => $v !== null);

        /** @var list<array{min: int, label: string}> $mentions */
        $mentions = config('synapse.grading.mentions', []);

        $buckets = [];

        foreach ($mentions as $mention) {
            $buckets[$mention['label']] = 0;
        }

        foreach ($averages as $average) {
            foreach ($mentions as $mention) {
                if ($average >= $mention['min']) {
                    $buckets[$mention['label']]++;

                    break;
                }
            }
        }

        return collect($buckets)
            ->map(fn (int $count, string $label) => ['label' => $label, 'value' => $count])
            ->values()
            ->all();
    }

    /**
     * @param  array{classes: list<int>, pairs: list<string>, is_teacher: bool}|null  $scope
     * @return array<string, mixed>
     */
    private function attendance(?array $scope, array $studentIds): array
    {
        $query = Attendance::query();

        if ($scope) {
            $query->whereIn('class_id', $scope['classes']);
        } elseif ($studentIds !== []) {
            $query->whereIn('student_id', $studentIds);
        }

        $counts = $query
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $attended = ($counts[Attendance::PRESENT] ?? 0) + ($counts[Attendance::LATE] ?? 0);
        $counted = $attended + ($counts[Attendance::ABSENT] ?? 0);

        return [
            'rate' => $counted > 0 ? round(($attended / $counted) * 100, 1) : null,
            'records' => (int) $counts->sum(),
            'present' => (int) ($counts[Attendance::PRESENT] ?? 0),
            'late' => (int) ($counts[Attendance::LATE] ?? 0),
            'absent' => (int) ($counts[Attendance::ABSENT] ?? 0),
            'excused' => (int) ($counts[Attendance::EXCUSED] ?? 0),
        ];
    }

    /**
     * @param  array{classes: list<int>, pairs: list<string>, is_teacher: bool}|null  $scope
     * @return array<string, mixed>
     */
    private function engagement(?array $scope, ?int $yearId, array $studentIds): array
    {
        $assignments = $this->scoped(HomeworkAssignment::query()->published(), $scope, $yearId)->get();
        $quizzes = $this->scoped(Quiz::query()->published(), $scope, $yearId)->get(['id', 'max_score']);

        $submissions = $assignments->isEmpty()
            ? collect()
            : HomeworkSubmission::query()
                ->whereIn('homework_assignment_id', $assignments->pluck('id')->all())
                ->whereNotNull('submitted_at')
                ->get(['homework_assignment_id', 'student_id']);

        $expected = $assignments->count() * count($studentIds);

        $attempts = $quizzes->isEmpty()
            ? collect()
            : QuizAttempt::query()
                ->whereIn('quiz_id', $quizzes->pluck('id')->all())
                ->whereNotNull('submitted_at')
                ->get(['quiz_id', 'score']);

        $maxScores = $quizzes->pluck('max_score', 'id');

        $quizPercentages = $attempts
            ->map(function (QuizAttempt $attempt) use ($maxScores) {
                $max = (float) ($maxScores[$attempt->quiz_id] ?? 0);

                return $max > 0 && $attempt->score !== null ? ((float) $attempt->score / $max) * 100 : null;
            })
            ->filter(fn ($value) => $value !== null);

        return [
            'assignments_published' => $assignments->count(),
            'submissions' => $submissions->count(),
            'submission_rate' => $expected > 0 ? round(($submissions->count() / $expected) * 100, 1) : null,
            'quizzes_published' => $quizzes->count(),
            'quiz_attempts' => $attempts->count(),
            'quiz_average' => $quizPercentages->isEmpty() ? null : round($quizPercentages->avg(), 1),
        ];
    }

    // --------------------------------------------------------------- helpers

    /**
     * Mean grade average per student, used by both the headline number and the
     * distribution so the two can never disagree.
     *
     * @param  array{classes: list<int>, pairs: list<string>, is_teacher: bool}|null  $scope
     * @return Collection<int, float|null>
     */
    private function studentAverages(?array $scope, array $studentIds): Collection
    {
        if ($studentIds === []) {
            return collect();
        }

        return $this->gradeQuery($scope)
            ->get()
            ->groupBy('student_id')
            ->map(function (Collection $rows) {
                $values = $rows->map(fn (Grade $grade) => $grade->average)->filter(fn ($v) => $v !== null);

                return $values->isEmpty() ? null : round($values->avg(), 2);
            });
    }

    /**
     * @param  array{classes: list<int>, pairs: list<string>, is_teacher: bool}|null  $scope
     */
    private function gradeQuery(?array $scope)
    {
        $query = Grade::query()->with('scores.component');

        return $scope ? $this->academicScope->applyToPairs($query, $scope) : $query;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array{classes: list<int>, pairs: list<string>, is_teacher: bool}|null  $scope
     */
    private function scoped($query, ?array $scope, ?int $yearId)
    {
        if ($yearId) {
            $query->where('academic_year_id', $yearId);
        }

        return $scope ? $this->academicScope->applyToPairs($query, $scope) : $query;
    }

    /**
     * @param  array{classes: list<int>, pairs: list<string>, is_teacher: bool}  $scope
     * @return list<string>
     */
    private function classNames(array $scope): array
    {
        return SchoolClass::query()
            ->whereIn('id', $scope['classes'])
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /**
     * How many distinct teachers hold an assignment in these classes — useful
     * context for a teacher comparing their own classes against the staff who
     * share them.
     *
     * @param  array{classes: list<int>, pairs: list<string>, is_teacher: bool}  $scope
     */
    private function teachersInClasses(array $scope): int
    {
        return TeachingAssignment::query()
            ->whereIn('class_id', $scope['classes'])
            ->distinct()
            ->count('teacher_id');
    }
}
