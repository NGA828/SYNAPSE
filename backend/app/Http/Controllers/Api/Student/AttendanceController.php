<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
    ) {}

    /**
     * The authenticated student's own attendance summary.
     */
    public function index(Request $request): JsonResponse
    {
        $student = $request->user()->student;

        abort_unless(
            $student instanceof Student,
            403,
            'No student profile is attached to this account.',
        );

        return response()->json(
            $this->attendanceService->studentSummary($student),
        );
    }
}
