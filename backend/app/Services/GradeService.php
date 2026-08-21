<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\GradeComponent;
use App\Models\GradeScore;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Support\Collection;

class GradeService
{
    /**
     * The authenticated student's grades for a year (optionally a semester).
     *
     * @return array{grades: Collection, average: ?float, class: ?SchoolClass, semester: ?Semester}
     */
    public function studentGrades(Student $student, ?AcademicYear $year = null, ?Semester $semester = null): array
    {
        $year ??= AcademicYear::current();

        abort_unless($year, 409, 'No active academic year is configured.');

        $schoolClass = $student->enrollments()
            ->where('academic_year_id', $year->id)
            ->first()?->schoolClass;

        $query = Grade::query()
            ->with(['subject', 'scores.component'])
            ->where('student_id', $student->id)
            ->where('academic_year_id', $year->id);

        if ($semester) {
            $query->where('semester_id', $semester->id);
        }

        $grades = $query->get()->map(function (Grade $grade) {
            $components = $grade->scores->map(fn (GradeScore $score) => [
                'name' => $score->component?->name,
                'weight' => $score->component?->weight,
                'score' => $score->score,
            ])->values();
            $legacy = $components->isEmpty();

            return [
                'subject' => $grade->subject->name,
                'subject_code' => $grade->subject->code,
                'semester_id' => $grade->semester_id,
                'test1' => $legacy ? $grade->test1 : null,
                'test2' => $legacy ? $grade->test2 : null,
                'exam' => $legacy ? $grade->exam : null,
                'components' => $components,
                'average' => $grade->average,
            ];
        });

        $average = $grades
            ->pluck('average')
            ->filter(fn ($value) => $value !== null)
            ->avg();

        return [
            'grades' => $grades,
            'average' => $average === null ? null : round($average, 2),
            'class' => $schoolClass,
            'semester' => $semester,
        ];
    }

    /**
     * The semesters defined for a year in the current tenant.
     *
     * @return Collection<int, Semester>
     */
    public function semesters(?AcademicYear $year = null): Collection
    {
        $year ??= AcademicYear::current();

        abort_unless($year, 409, 'No active academic year is configured.');

        return Semester::query()
            ->where('academic_year_id', $year->id)
            ->orderBy('sequence')
            ->get();
    }

    /**
     * Full gradebook for a teacher's assignment, with weighted components.
     *
     * @return array{class: SchoolClass, subject: Subject, academic_year: AcademicYear, semester: ?Semester, components: Collection, students: Collection}
     */
    public function gradebook(
        Teacher $teacher,
        SchoolClass $class,
        Subject $subject,
        ?AcademicYear $year = null,
        ?Semester $semester = null,
    ): array {
        $year ??= AcademicYear::current();

        abort_unless($year, 409, 'No active academic year is configured.');
        $this->assertAssigned($teacher, $class, $subject, $year);

        $components = $this->effectiveComponents($class->school, $subject);

        $grades = Grade::query()
            ->with('scores.component')
            ->where('class_id', $class->id)
            ->where('subject_id', $subject->id)
            ->where('academic_year_id', $year->id)
            ->when($semester, fn ($q) => $q->where('semester_id', $semester->id))
            ->get()
            ->keyBy('student_id');

        $students = $class->students()
            ->with('user')
            ->wherePivot('academic_year_id', $year->id)
            ->get()
            ->map(function (Student $student) use ($grades, $components) {
                $grade = $grades->get($student->id);

                return [
                    'id' => $student->id,
                    'name' => $student->user?->name,
                    'matricule' => $student->matricule,
                    'test1' => $grade?->test1,
                    'test2' => $grade?->test2,
                    'exam' => $grade?->exam,
                    'scores' => $components->mapWithKeys(function (GradeComponent $component) use ($grade) {
                        $score = $grade?->scores->firstWhere('component_id', $component->id);

                        return [$component->id => $score?->score];
                    })->all(),
                    'average' => $grade?->average,
                ];
            })
            ->sortBy('name')
            ->values();

        return [
            'class' => $class,
            'subject' => $subject,
            'academic_year' => $year,
            'semester' => $semester,
            'components' => $components->map(fn (GradeComponent $component) => [
                'id' => $component->id,
                'name' => $component->name,
                'weight' => $component->weight,
            ])->values(),
            'students' => $students,
        ];
    }

    /**
     * Persist a submitted gradebook (only enrolled students, only the
     * assigned teacher), then return the refreshed gradebook.
     *
     * @param  array<int, array{student_id: int, test1?: ?float, test2?: ?float, exam?: ?float, scores?: array<int, array{component_id: int, score: ?float}>}>  $rows
     * @return array<string, mixed>
     */
    public function save(
        Teacher $teacher,
        SchoolClass $class,
        Subject $subject,
        array $rows,
        ?AcademicYear $year = null,
        ?Semester $semester = null,
    ): array {
        $year ??= AcademicYear::current();

        abort_unless($year, 409, 'No active academic year is configured.');
        $this->assertAssigned($teacher, $class, $subject, $year);

        $components = $this->effectiveComponents($class->school, $subject);
        $enrolledIds = $class->students()
            ->wherePivot('academic_year_id', $year->id)
            ->pluck('students.id');

        foreach ($rows as $row) {
            if (! in_array((int) $row['student_id'], $enrolledIds->all(), true)) {
                continue;
            }

            $legacy = [
                'test1' => $row['test1'] ?? null,
                'test2' => $row['test2'] ?? null,
                'exam' => $row['exam'] ?? null,
            ];
            $componentScores = $row['scores'] ?? [];

            if (count(array_filter($legacy, fn ($value) => $value !== null)) === 0
                && $componentScores === []) {
                continue;
            }

            $grade = Grade::updateOrCreate(
                [
                    'student_id' => $row['student_id'],
                    'subject_id' => $subject->id,
                    'class_id' => $class->id,
                    'academic_year_id' => $year->id,
                ],
                [
                    'school_id' => $class->school_id,
                    'semester_id' => $semester?->id,
                    'teacher_id' => $teacher->id,
                    ...$legacy,
                ],
            );

            foreach ($componentScores as $score) {
                $component = $components->firstWhere('id', $score['component_id']);

                if (! $component) {
                    continue;
                }

                GradeScore::updateOrCreate(
                    [
                        'grade_id' => $grade->id,
                        'component_id' => $component->id,
                    ],
                    [
                        'school_id' => $class->school_id,
                        'score' => $score['score'] ?? null,
                    ],
                );
            }
        }

        return $this->gradebook($teacher, $class, $subject, $year, $semester);
    }

    /**
     * A printable report card for the student, including class rank.
     *
     * @return array<string, mixed>
     */
    public function reportCard(Student $student, ?AcademicYear $year = null, ?Semester $semester = null): array
    {
        $year ??= AcademicYear::current();

        $base = $this->studentGrades($student, $year, $semester);
        $class = $base['class'];

        $rank = null;
        $classSize = 0;

        if ($class) {
            $classmates = $class->students()
                ->wherePivot('academic_year_id', $year->id)
                ->get();

            $classSize = $classmates->count();

            $averages = $classmates
                ->map(function (Student $classmate) use ($year, $semester) {
                    $query = Grade::query()
                        ->where('student_id', $classmate->id)
                        ->where('academic_year_id', $year->id);

                    if ($semester) {
                        $query->where('semester_id', $semester->id);
                    }

                    $values = $query->get()->pluck('average')->filter(fn ($value) => $value !== null);

                    return [
                        'student_id' => $classmate->id,
                        'average' => $values->isEmpty() ? null : round($values->avg(), 2),
                    ];
                })
                ->filter(fn ($entry) => $entry['average'] !== null)
                ->sortByDesc('average')
                ->values();

            foreach ($averages as $index => $entry) {
                if ($entry['student_id'] === $student->id) {
                    $rank = $index + 1;
                    break;
                }
            }
        }

        return [
            'student' => $student,
            'class' => $class,
            'academic_year' => $year,
            'semester' => $semester,
            'grades' => $base['grades'],
            'average' => $base['average'],
            'rank' => $rank,
            'class_size' => $classSize,
        ];
    }

    /**
     * Effective weighted components for a subject: subject-specific first,
     * falling back to school-wide defaults.
     *
     * @return Collection<int, GradeComponent>
     */
    public function effectiveComponents(School $school, Subject $subject): Collection
    {
        $subjectComponents = GradeComponent::query()
            ->where('school_id', $school->id)
            ->where('subject_id', $subject->id)
            ->orderBy('sequence')
            ->get();

        if ($subjectComponents->isNotEmpty()) {
            return $subjectComponents;
        }

        return GradeComponent::query()
            ->where('school_id', $school->id)
            ->whereNull('subject_id')
            ->orderBy('sequence')
            ->get();
    }

    /**
     * Guard: the teacher must hold a TeachingAssignment for this class +
     * subject + year.
     */
    private function assertAssigned(
        Teacher $teacher,
        SchoolClass $class,
        Subject $subject,
        AcademicYear $year,
    ): void {
        $assigned = $teacher->teachingAssignments()
            ->where('class_id', $class->id)
            ->where('subject_id', $subject->id)
            ->where('academic_year_id', $year->id)
            ->exists();

        abort_unless($assigned, 403, 'You are not assigned to teach this subject in this class.');
    }
}
