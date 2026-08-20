<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\DocumentRequest;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;

class AdminAcademicService
{
    /**
     * High-level counts for the school admin dashboard (tenant-scoped by the
     * TenantScope on each model).
     *
     * @return array{students: int, teachers: int, classes: int, subjects: int, pending_requests: int}
     */
    public function summary(): array
    {
        return [
            'students' => Student::count(),
            'teachers' => Teacher::count(),
            'classes' => SchoolClass::count(),
            'subjects' => Subject::count(),
            'pending_requests' => DocumentRequest::query()
                ->whereIn('status', DocumentRequest::OPEN_STATUSES)
                ->count(),
        ];
    }

    public function createYear(School $school, array $data): AcademicYear
    {
        $year = AcademicYear::create([...$data, 'school_id' => $school->id]);

        if ($year->is_current) {
            $this->activate($year);
        }

        return $year->fresh();
    }

    /**
     * Mark one year as the current year (and deactivate the rest of the school).
     */
    public function activate(AcademicYear $year): AcademicYear
    {
        AcademicYear::query()->update(['is_current' => false]);
        $year->update(['is_current' => true]);

        return $year->fresh();
    }

    public function createClass(School $school, array $data): SchoolClass
    {
        return SchoolClass::create([...$data, 'school_id' => $school->id]);
    }

    public function createSubject(School $school, array $data): Subject
    {
        return Subject::create([...$data, 'school_id' => $school->id]);
    }
}
