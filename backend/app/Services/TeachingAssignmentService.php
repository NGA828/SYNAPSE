<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\TeachingAssignment;

class TeachingAssignmentService
{
    /**
     * Assign a teacher to a subject + class for the (default: current) year.
     *
     * @param  array{teacher_id: int, subject_id: int, class_id: int, academic_year_id?: ?int}  $data
     */
    public function assign(array $data): TeachingAssignment
    {
        $yearId = $data['academic_year_id'] ?? AcademicYear::current()?->id;

        abort_unless($yearId, 409, 'No active academic year is configured.');

        $teacher = Teacher::findOrFail($data['teacher_id']);

        return TeachingAssignment::firstOrCreate([
            'teacher_id' => $data['teacher_id'],
            'subject_id' => $data['subject_id'],
            'class_id' => $data['class_id'],
            'academic_year_id' => $yearId,
        ], [
            'school_id' => $teacher->school_id,
        ])->load(['teacher.user', 'subject', 'schoolClass', 'academicYear']);
    }

    /**
     * Remove an assignment (revoke access).
     */
    public function unassign(TeachingAssignment $assignment): void
    {
        $assignment->delete();
    }
}
