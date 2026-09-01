<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreHomeworkSubmissionRequest;
use App\Http\Resources\HomeworkAssignmentResource;
use App\Http\Resources\HomeworkSubmissionResource;
use App\Models\HomeworkAssignment;
use App\Services\HomeworkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeworkController extends Controller
{
    public function __construct(
        private readonly HomeworkService $homework,
    ) {}

    /**
     * Every published homework for the student's current class, each paired
     * with their own submission (null when they have not submitted).
     */
    public function index(Request $request): JsonResponse
    {
        $student = $this->student($request);

        $rows = $this->homework->forStudent($student)->map(fn (array $row) => [
            'assignment' => HomeworkAssignmentResource::make($row['assignment'])->resolve(),
            'submission' => $row['submission']
                ? HomeworkSubmissionResource::make($row['submission'])->resolve()
                : null,
        ]);

        return response()->json([
            'data' => $rows->values(),
            'summary' => $this->homework->studentSummary($student),
        ]);
    }

    /**
     * Submit — or replace before the deadline — the student's work.
     */
    public function submit(
        StoreHomeworkSubmissionRequest $request,
        HomeworkAssignment $homeworkAssignment,
    ): JsonResponse {
        $student = $this->student($request);

        $submission = $this->homework->submit(
            $student,
            $homeworkAssignment,
            $request->validated()['content'] ?? null,
            $request->file('attachments', []),
            $request->user(),
        );

        return response()->json([
            'data' => HomeworkSubmissionResource::make($submission),
        ], 201);
    }

    private function student(Request $request)
    {
        $student = $request->user()->student;

        abort_unless($student, 403, 'No student profile is attached to this account.');

        return $student;
    }
}
