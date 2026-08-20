<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Services\TeacherDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function __construct(
        private readonly TeacherDashboardService $dashboardService,
    ) {}

    /**
     * List the authenticated teacher's assignments only.
     */
    public function index(Request $request): JsonResponse
    {
        $teacher = $request->user()->teacher;

        abort_unless($teacher, 403, 'No teacher profile is attached to this account.');

        return response()->json([
            'data' => $this->dashboardService->assignments($teacher),
        ]);
    }
}
