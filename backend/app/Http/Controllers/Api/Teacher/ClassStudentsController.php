<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Services\TeacherDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassStudentsController extends Controller
{
    public function __construct(
        private readonly TeacherDashboardService $dashboardService,
    ) {}

    /**
     * Students enrolled in a class for a subject the teacher is assigned to.
     *
     * Gated by the `teaching.assignment` middleware, which verifies the
     * TeachingAssignment record before this controller ever runs.
     */
    public function index(Request $request, SchoolClass $schoolClass, Subject $subject): JsonResponse
    {
        $teacher = $request->user()->teacher;
        $year = AcademicYear::current();

        $students = $this->dashboardService->studentsFor($teacher, $schoolClass, $subject, $year);

        return response()->json([
            'class' => $schoolClass,
            'subject' => $subject,
            'academic_year' => $year,
            'students' => $students,
        ]);
    }
}
