<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Services\GradeService;
use App\Services\TimetableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademicController extends Controller
{
    public function __construct(
        private readonly GradeService $gradeService,
        private readonly TimetableService $timetableService,
    ) {}

    /**
     * The authenticated student's subject grades.
     */
    public function grades(Request $request): JsonResponse
    {
        $student = $this->student($request);

        $result = $this->gradeService->studentGrades($student);

        return response()->json([
            'class' => $result['class'],
            'academic_year' => AcademicYear::current(),
            'grades' => $result['grades'],
            'average' => $result['average'],
        ]);
    }

    /**
     * A printable report card with class rank.
     */
    public function reportCard(Request $request): JsonResponse
    {
        $student = $this->student($request);

        return response()->json($this->gradeService->reportCard($student));
    }

    /**
     * The authenticated student's weekly timetable for their current class.
     */
    public function timetable(Request $request): JsonResponse
    {
        $student = $this->student($request);
        $year = AcademicYear::current();

        $class = $student->enrollments()
            ->where('academic_year_id', $year?->id)
            ->first()?->schoolClass;

        return response()->json([
            'class' => $class,
            'academic_year' => $year,
            'entries' => $class ? $this->timetableService->entriesFor($class, $year) : [],
        ]);
    }

    private function student(Request $request): Student
    {
        $student = $request->user()->student;

        abort_unless(
            $student instanceof Student,
            403,
            'No student profile is attached to this account.',
        );

        return $student;
    }
}
