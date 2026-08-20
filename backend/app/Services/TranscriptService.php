<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Student;

class TranscriptService
{
    /**
     * Multi-year academic history for a student: every enrollment (class +
     * year) with that year's grades and average, plus a cumulative average.
     *
     * @return array{years: array<int, array<string, mixed>>, cumulative: ?float}
     */
    public function forStudent(Student $student): array
    {
        $enrollments = $student->enrollments()
            ->with(['schoolClass', 'academicYear'])
            ->orderBy('academic_year_id')
            ->get();

        $years = [];
        $allAverages = [];

        foreach ($enrollments as $enrollment) {
            $grades = Grade::query()
                ->with(['subject', 'scores.component'])
                ->where('student_id', $student->id)
                ->where('academic_year_id', $enrollment->academic_year_id)
                ->get()
                ->map(fn (Grade $grade) => [
                    'subject' => $grade->subject->name,
                    'subject_code' => $grade->subject->code,
                    'average' => $grade->average,
                ]);

            $average = $grades
                ->pluck('average')
                ->filter(fn ($value) => $value !== null)
                ->avg();

            if ($average !== null) {
                $allAverages[] = $average;
            }

            $years[] = [
                'academic_year' => $enrollment->academicYear,
                'class' => $enrollment->schoolClass,
                'grades' => $grades,
                'average' => $average === null ? null : round($average, 2),
            ];
        }

        // Newest year first for display.
        $years = array_reverse($years);

        $cumulative = $allAverages === []
            ? null
            : round(array_sum($allAverages) / count($allAverages), 2);

        return [
            'student' => $student,
            'years' => $years,
            'cumulative' => $cumulative,
        ];
    }
}
