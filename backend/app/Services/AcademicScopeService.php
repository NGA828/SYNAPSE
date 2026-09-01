<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which rows of academic data a given user is entitled to see.
 *
 * Extracted because both the calendar and the pastoral register need the same
 * answer, and two slightly different versions of "what does this teacher own?"
 * is exactly how a student ends up on one screen and not the other.
 *
 * The rule is: a student sees the one class they are enrolled in, taking every
 * subject taught there; a teacher sees each class/subject pair they are
 * assigned, so a teacher with three classes gets all three; an administrator
 * owns no timetable and is handled by the caller instead.
 */
class AcademicScopeService
{
    /**
     * @return array{classes: list<int>, pairs: list<string>, is_teacher: bool}|null  Null when the user owns no academic rows.
     */
    public function for(User $user, ?AcademicYear $year = null): ?array
    {
        $year ??= AcademicYear::current();

        if (! $year) {
            return null;
        }

        if ($user->role === User::ROLE_STUDENT) {
            return $this->forStudent($user, $year->id);
        }

        if ($user->role === User::ROLE_TEACHER) {
            return $this->forTeacher($user, $year->id);
        }

        return null;
    }

    /**
     * @return array{classes: list<int>, pairs: list<string>, is_teacher: bool}|null
     */
    private function forStudent(User $user, int $yearId): ?array
    {
        $student = Student::query()->where('user_id', $user->id)->first();

        $classId = $student?->enrollments()
            ->where('academic_year_id', $yearId)
            ->value('class_id');

        return $classId
            ? ['classes' => [(int) $classId], 'pairs' => [], 'is_teacher' => false]
            : null;
    }

    /**
     * @return array{classes: list<int>, pairs: list<string>, is_teacher: bool}|null
     */
    private function forTeacher(User $user, int $yearId): ?array
    {
        $teacher = Teacher::query()->where('user_id', $user->id)->first();

        if (! $teacher) {
            return null;
        }

        $assignments = $teacher->teachingAssignments()
            ->where('academic_year_id', $yearId)
            ->get(['class_id', 'subject_id']);

        if ($assignments->isEmpty()) {
            return null;
        }

        return [
            'classes' => $assignments->pluck('class_id')->map(fn ($id) => (int) $id)->unique()->values()->all(),
            'pairs' => $assignments
                ->map(fn ($assignment) => $this->pairKey($assignment->class_id, $assignment->subject_id))
                ->unique()
                ->values()
                ->all(),
            'is_teacher' => true,
        ];
    }

    public function pairKey(int|string|null $classId, int|string|null $subjectId): string
    {
        return "{$classId}:{$subjectId}";
    }

    public function classFromPair(string $pair): int
    {
        return (int) explode(':', $pair)[0];
    }

    public function subjectFromPair(string $pair): int
    {
        return (int) explode(':', $pair)[1];
    }

    /**
     * Restrict a query keyed on class_id/subject_id to the caller's scope.
     *
     * A teacher is limited to the exact combinations they teach, not to every
     * row in the classes they happen to appear in — otherwise a maths teacher
     * would see the English homework set by someone else.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array{classes: list<int>, pairs: list<string>, is_teacher: bool}  $scope
     * @return Builder<TModel>
     */
    public function applyToPairs(Builder $query, array $scope): Builder
    {
        if (! $scope['is_teacher']) {
            return $query->whereIn('class_id', $scope['classes']);
        }

        return $query->where(
            fn (Builder $inner) => collect($scope['pairs'])->each(
                fn (string $pair) => $inner->orWhere(
                    fn (Builder $clause) => $clause
                        ->where('class_id', $this->classFromPair($pair))
                        ->where('subject_id', $this->subjectFromPair($pair)),
                ),
            ),
        );
    }

    /**
     * The same restriction where only a class column exists.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array{classes: list<int>, pairs: list<string>, is_teacher: bool}  $scope
     * @return Builder<TModel>
     */
    public function applyToClasses(Builder $query, array $scope): Builder
    {
        return $query->whereIn('class_id', $scope['classes']);
    }

    /**
     * Whether this caller may act on one specific student.
     *
     * An administrator may act on any pupil in their school; a teacher only on
     * pupils enrolled in a class they hold this year; a student only on
     * themselves. Returns false rather than aborting so callers choose their own
     * status code.
     */
    public function sees(User $viewer, Student $student, ?AcademicYear $year = null): bool
    {
        if ($viewer->role === User::ROLE_ADMIN) {
            return (int) $student->school_id === (int) $viewer->school_id;
        }

        if ($viewer->role === User::ROLE_STUDENT) {
            return (int) $student->user_id === (int) $viewer->id;
        }

        $year ??= AcademicYear::current();
        $scope = $this->for($viewer, $year);

        if (! $scope) {
            return false;
        }

        return Enrollment::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $year?->id)
            ->whereIn('class_id', $scope['classes'])
            ->exists();
    }
}
