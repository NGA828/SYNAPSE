<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\StudentDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StudentController extends Controller
{
    public function __construct(
        private readonly StudentDashboardService $dashboardService,
    ) {}

    /**
     * Return the authenticated student's dashboard payload.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $student = $request->user()->student;

        abort_unless(
            $student instanceof Student,
            403,
            'No student profile is attached to this account.'
        );

        Gate::forUser($request->user())->authorize('view', $student);

        return response()->json($this->dashboardService->dashboard($student));
    }
}
