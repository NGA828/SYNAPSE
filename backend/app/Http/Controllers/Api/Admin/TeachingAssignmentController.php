<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTeachingAssignmentRequest;
use App\Models\TeachingAssignment;
use App\Services\TeachingAssignmentService;
use Illuminate\Http\JsonResponse;

class TeachingAssignmentController extends Controller
{
    public function __construct(
        private readonly TeachingAssignmentService $assignmentService,
    ) {}

    public function index(): JsonResponse
    {
        $assignments = TeachingAssignment::query()
            ->with(['teacher.user', 'subject', 'schoolClass', 'academicYear'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (TeachingAssignment $assignment) => [
                'id' => $assignment->id,
                'teacher' => [
                    'id' => $assignment->teacher->id,
                    'name' => $assignment->teacher->user?->name,
                ],
                'subject' => $assignment->subject,
                'class' => $assignment->schoolClass,
                'academic_year' => $assignment->academicYear,
            ]);

        return response()->json(['data' => $assignments]);
    }

    public function store(StoreTeachingAssignmentRequest $request): JsonResponse
    {
        $assignment = $this->assignmentService->assign($request->validated());

        return response()->json([
            'data' => [
                'id' => $assignment->id,
                'teacher' => [
                    'id' => $assignment->teacher->id,
                    'name' => $assignment->teacher->user?->name,
                ],
                'subject' => $assignment->subject,
                'class' => $assignment->schoolClass,
                'academic_year' => $assignment->academicYear,
            ],
        ], 201);
    }

    public function destroy(TeachingAssignment $teachingAssignment): JsonResponse
    {
        $this->assignmentService->unassign($teachingAssignment);

        return response()->json(['message' => 'Assignment removed.']);
    }
}
