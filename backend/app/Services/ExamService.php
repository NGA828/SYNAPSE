<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Support\Collection;

class ExamService
{
    /**
     * Exam sessions for a school (admin), optionally filtered by class.
     *
     * @return Collection<int, Exam>
     */
    public function forSchool(School $school, ?SchoolClass $class = null): Collection
    {
        return Exam::query()
            ->with(['subject', 'schoolClass', 'semester'])
            ->where('school_id', $school->id)
            ->when($class, fn ($q) => $q->where('class_id', $class->id))
            ->orderBy('date')
            ->orderBy('start')
            ->get();
    }

    /**
     * Exam sessions the authenticated teacher supervises (their assignments).
     *
     * @return Collection<int, Exam>
     */
    public function forTeacher(Teacher $teacher, ?AcademicYear $year = null): Collection
    {
        $year ??= AcademicYear::current();

        $assignments = $teacher->teachingAssignments()
            ->where('academic_year_id', $year?->id)
            ->get(['class_id', 'subject_id']);

        if ($assignments->isEmpty()) {
            return collect();
        }

        return Exam::query()
            ->with(['subject', 'schoolClass', 'semester'])
            ->where('school_id', $teacher->school_id)
            ->where(function ($query) use ($assignments) {
                foreach ($assignments as $assignment) {
                    $query->orWhere(function ($q) use ($assignment) {
                        $q->where('class_id', $assignment->class_id)
                            ->where('subject_id', $assignment->subject_id);
                    });
                }
            })
            ->orderBy('date')
            ->orderBy('start')
            ->get();
    }

    /**
     * Exam sessions for a student's current class.
     *
     * @return Collection<int, Exam>
     */
    public function forStudent(Student $student, ?AcademicYear $year = null): Collection
    {
        $year ??= AcademicYear::current();

        $enrollment = $student->enrollments()
            ->where('academic_year_id', $year?->id)
            ->first();

        if (! $enrollment) {
            return collect();
        }

        return Exam::query()
            ->with(['subject', 'schoolClass', 'semester'])
            ->where('school_id', $student->school_id)
            ->where('class_id', $enrollment->class_id)
            ->where('academic_year_id', $year->id)
            ->orderBy('date')
            ->orderBy('start')
            ->get();
    }

    public function create(School $school, array $data): Exam
    {
        $year = AcademicYear::current();
        abort_unless($year, 409, 'No active academic year is configured.');

        abort_unless(
            SchoolClass::query()->whereKey($data['class_id'])->where('school_id', $school->id)->exists(),
            422,
            'The selected class is invalid.',
        );

        abort_unless(
            Subject::query()->whereKey($data['subject_id'])->where('school_id', $school->id)->exists(),
            422,
            'The selected subject is invalid.',
        );

        if (! empty($data['semester_id'])) {
            abort_unless(
                Semester::query()
                    ->whereKey($data['semester_id'])
                    ->where('school_id', $school->id)
                    ->where('academic_year_id', $year->id)
                    ->exists(),
                422,
                'The selected semester is invalid.',
            );
        }

        return Exam::create([
            ...$data,
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
        ])->load(['subject', 'schoolClass', 'semester']);
    }

    public function delete(Exam $exam): void
    {
        $exam->delete();
    }

    /**
     * Result compilation: students ranked by term average for a class +
     * subject (admin or the assigned teacher).
     *
     * @return array{subject: Subject, class: SchoolClass, ranking: Collection}
     */
    public function ranking(School $school, SchoolClass $class, Subject $subject, ?AcademicYear $year = null, ?Semester $semester = null): array
    {
        $year ??= AcademicYear::current();

        $students = $class->students()
            ->with('user')
            ->wherePivot('academic_year_id', $year?->id)
            ->get();

        $ranking = $students
            ->map(function (Student $student) use ($school, $class, $subject, $year, $semester) {
                $query = Grade::query()
                    ->where('school_id', $school->id)
                    ->where('student_id', $student->id)
                    ->where('class_id', $class->id)
                    ->where('subject_id', $subject->id)
                    ->where('academic_year_id', $year?->id);

                if ($semester) {
                    $query->where('semester_id', $semester->id);
                }

                $grade = $query->with('scores.component')->first();

                return [
                    'student_id' => $student->id,
                    'name' => $student->user?->name,
                    'matricule' => $student->matricule,
                    'average' => $grade?->average,
                ];
            })
            ->filter(fn ($entry) => $entry['average'] !== null)
            ->sortByDesc('average')
            ->values()
            ->map(function ($entry, $index) {
                $entry['rank'] = $index + 1;

                return $entry;
            });

        return [
            'subject' => $subject,
            'class' => $class,
            'academic_year' => $year,
            'semester' => $semester,
            'ranking' => $ranking,
        ];
    }
}
