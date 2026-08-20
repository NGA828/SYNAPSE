<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\Semester;

class SemesterService
{
    public function create(School $school, array $data): Semester
    {
        $semester = Semester::create([...$data, 'school_id' => $school->id]);

        if ($semester->is_current) {
            $this->activate($semester);
        }

        return $semester->fresh();
    }

    /**
     * Mark one semester as current for its academic year.
     */
    public function activate(Semester $semester): Semester
    {
        Semester::query()
            ->where('school_id', $semester->school_id)
            ->where('academic_year_id', $semester->academic_year_id)
            ->update(['is_current' => false]);

        $semester->update(['is_current' => true]);

        return $semester->fresh();
    }

    public function delete(Semester $semester): void
    {
        $semester->delete();
    }

    /**
     * Semesters for a year (admin listing).
     */
    public function forYear(AcademicYear $year)
    {
        return Semester::query()
            ->where('academic_year_id', $year->id)
            ->orderBy('sequence')
            ->get();
    }
}
