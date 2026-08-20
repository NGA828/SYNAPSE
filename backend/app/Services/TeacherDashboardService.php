<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Support\Collection;

class TeacherDashboardService
{
    /**
     * The teacher's assignments for the given (default: current) academic year,
     * with an enrolled-student count per assignment.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function assignments(Teacher $teacher, ?AcademicYear $year = null): Collection
    {
        $year ??= AcademicYear::current();

        abort_unless($year, 409, 'No active academic year is configured.');

        return $teacher->teachingAssignments()
            ->with(['subject', 'schoolClass', 'academicYear'])
            ->where('academic_year_id', $year->id)
            ->get()
            ->map(function ($assignment) use ($year) {
                return [
                    'id' => $assignment->id,
                    'subject' => $assignment->subject,
                    'class' => $assignment->schoolClass,
                    'academic_year' => $assignment->academicYear,
                    'students_count' => $assignment->schoolClass->students()
                        ->wherePivot('academic_year_id', $year->id)
                        ->count(),
                ];
            });
    }

    /**
     * Enrolled students for a class + subject, strictly limited to what the
     * teacher is assigned to. Re-verified here (defense in depth) even though
     * the CheckTeachingAssignment middleware already gates the route.
     *
     * @return Collection<int, array{id: int, matricule: ?string, name: ?string}>
     */
    public function studentsFor(
        Teacher $teacher,
        SchoolClass $class,
        Subject $subject,
        ?AcademicYear $year = null,
    ): Collection {
        $year ??= AcademicYear::current();

        abort_unless($year, 409, 'No active academic year is configured.');

        $assigned = $teacher->teachingAssignments()
            ->where('class_id', $class->id)
            ->where('subject_id', $subject->id)
            ->where('academic_year_id', $year->id)
            ->exists();

        abort_unless($assigned, 403, 'You are not assigned to teach this subject in this class.');

        return $class->students()
            ->with('user')
            ->wherePivot('academic_year_id', $year->id)
            ->get()
            ->map(fn (Student $student) => [
                'id' => $student->id,
                'matricule' => $student->matricule,
                'name' => $student->user?->name,
            ])
            ->sortBy('name')
            ->values();
    }
}
