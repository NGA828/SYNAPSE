<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Support\Collection;

class GradeService
{
    /**
     * The authenticated student's grades for the (default: current) year.
     *
     * @return array{grades: Collection, average: ?float, class: ?SchoolClass}
     */
    public function studentGrades(Student $student, ?AcademicYear $year = null): array
    {
        $year ??= AcademicYear::current();

        abort_unless($year, 409, 'No active academic year is configured.');

        $schoolClass = $student->enrollments()
            ->where('academic_year_id', $year->id)
            ->first()?->schoolClass;

        $grades = Grade::query()
            ->with('subject')
            ->where('student_id', $student->id)
            ->where('academic_year_id', $year->id)
            ->get()
            ->map(fn (Grade $grade) => [
                'subject' => $grade->subject->name,
                'subject_code' => $grade->subject->code,
                'test1' => $grade->test1,
                'test2' => $grade->test2,
                'exam' => $grade->exam,
                'average' => $grade->average,
            ]);

        $average = $grades
            ->pluck('average')
            ->filter(fn ($value) => $value !== null)
            ->avg();

        return [
            'grades' => $grades,
            'average' => $average === null ? null : round($average, 2),
            'class' => $schoolClass,
        ];
    }

    /**
     * Full gradebook for a teacher's assignment: every enrolled student with
     * their current scores. Assignment ownership is verified here as well.
     *
     * @return array{class: SchoolClass, subject: Subject, academic_year: AcademicYear, students: Collection}
     */
    public function gradebook(
        Teacher $teacher,
        SchoolClass $class,
        Subject $subject,
        ?AcademicYear $year = null,
    ): array {
        $year ??= AcademicYear::current();

        abort_unless($year, 409, 'No active academic year is configured.');
        $this->assertAssigned($teacher, $class, $subject, $year);

        $grades = Grade::query()
            ->where('class_id', $class->id)
            ->where('subject_id', $subject->id)
            ->where('academic_year_id', $year->id)
            ->get()
            ->keyBy('student_id');

        $students = $class->students()
            ->with('user')
            ->wherePivot('academic_year_id', $year->id)
            ->get()
            ->map(function (Student $student) use ($grades) {
                $grade = $grades->get($student->id);

                return [
                    'id' => $student->id,
                    'name' => $student->user?->name,
                    'matricule' => $student->matricule,
                    'test1' => $grade?->test1,
                    'test2' => $grade?->test2,
                    'exam' => $grade?->exam,
                    'average' => $grade?->average,
                ];
            })
            ->sortBy('name')
            ->values();

        return [
            'class' => $class,
            'subject' => $subject,
            'academic_year' => $year,
            'students' => $students,
        ];
    }

    /**
     * Persist a submitted gradebook (only enrolled students, only the
     * assigned teacher), then return the refreshed gradebook.
     *
     * @param  array<int, array{student_id: int, test1?: ?float, test2?: ?float, exam?: ?float}>  $rows
     * @return array{class: SchoolClass, subject: Subject, academic_year: AcademicYear, students: Collection}
     */
    public function save(
        Teacher $teacher,
        SchoolClass $class,
        Subject $subject,
        array $rows,
        ?AcademicYear $year = null,
    ): array {
        $year ??= AcademicYear::current();

        abort_unless($year, 409, 'No active academic year is configured.');
        $this->assertAssigned($teacher, $class, $subject, $year);

        $enrolledIds = $class->students()
            ->wherePivot('academic_year_id', $year->id)
            ->pluck('students.id');

        foreach ($rows as $row) {
            if (! in_array((int) $row['student_id'], $enrolledIds->all(), true)) {
                continue;
            }

            $scores = [
                'test1' => $row['test1'] ?? null,
                'test2' => $row['test2'] ?? null,
                'exam' => $row['exam'] ?? null,
            ];

            // Ignore rows with no entered score at all.
            if (count(array_filter($scores, fn ($value) => $value !== null)) === 0) {
                continue;
            }

            Grade::updateOrCreate(
                [
                    'student_id' => $row['student_id'],
                    'subject_id' => $subject->id,
                    'class_id' => $class->id,
                    'academic_year_id' => $year->id,
                ],
                [
                    'school_id' => $class->school_id,
                    'teacher_id' => $teacher->id,
                    ...$scores,
                ],
            );
        }

        return $this->gradebook($teacher, $class, $subject, $year);
    }

    /**
     * A printable report card for the student, including class rank.
     *
     * @return array<string, mixed>
     */
    public function reportCard(Student $student, ?AcademicYear $year = null): array
    {
        $year ??= AcademicYear::current();

        $base = $this->studentGrades($student, $year);
        $class = $base['class'];

        $rank = null;
        $classSize = 0;

        if ($class) {
            $classmates = $class->students()
                ->wherePivot('academic_year_id', $year->id)
                ->get();

            $classSize = $classmates->count();

            $averages = $classmates
                ->map(function (Student $classmate) use ($year) {
                    $values = Grade::query()
                        ->where('student_id', $classmate->id)
                        ->where('academic_year_id', $year->id)
                        ->get()
                        ->pluck('average')
                        ->filter(fn ($value) => $value !== null);

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
            'grades' => $base['grades'],
            'average' => $base['average'],
            'rank' => $rank,
            'class_size' => $classSize,
        ];
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
