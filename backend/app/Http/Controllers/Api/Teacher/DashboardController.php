<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Services\TeacherDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly TeacherDashboardService $dashboardService,
    ) {}

    /**
     * Teacher dashboard: assignments plus roll-up counts.
     */
    public function index(Request $request): JsonResponse
    {
        $teacher = $request->user()->teacher;

        abort_unless($teacher, 403, 'No teacher profile is attached to this account.');

        $assignments = $this->dashboardService->assignments($teacher);

        return response()->json([
            'summary' => [
                'assignments' => $assignments->count(),
                'students' => $assignments->sum('students_count'),
                'classes' => $assignments->pluck('class')->unique('id')->count(),
            ],
            'assignments' => $assignments,
        ]);
    }
}
