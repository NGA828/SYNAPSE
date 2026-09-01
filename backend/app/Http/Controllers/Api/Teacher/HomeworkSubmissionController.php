<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\GradeHomeworkSubmissionRequest;
use App\Http\Resources\HomeworkSubmissionResource;
use App\Models\HomeworkSubmission;
use App\Services\HomeworkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeworkSubmissionController extends Controller
{
    public function __construct(
        private readonly HomeworkService $homework,
    ) {}

    /**
     * Read one student's answer in full.
     */
    public function show(Request $request, HomeworkSubmission $homeworkSubmission): JsonResponse
    {
        $this->assertTeacherOwns($request, $homeworkSubmission);

        return response()->json([
            'data' => HomeworkSubmissionResource::make($homeworkSubmission->load(['homework.subject', 'student.user'])),
        ]);
    }

    /**
     * Grade and return the work to the student.
     */
    public function grade(GradeHomeworkSubmissionRequest $request, HomeworkSubmission $homeworkSubmission): JsonResponse
    {
        $teacher = $request->user()->teacher;

        abort_unless($teacher, 403, 'No teacher profile is attached to this account.');

        $graded = $this->homework->grade(
            $teacher,
            $homeworkSubmission,
            (float) $request->validated()['score'],
            $request->validated()['feedback'] ?? null,
        );

        return response()->json([
            'data' => HomeworkSubmissionResource::make($graded),
        ]);
    }

    /**
     * Route-model binding gives us the submission; ownership of its homework
     * is what actually authorises the teacher. HomeworkService re-checks it.
     */
    private function assertTeacherOwns(Request $request, HomeworkSubmission $homeworkSubmission): void
    {
        $teacher = $request->user()->teacher;

        abort_unless($teacher, 403, 'No teacher profile is attached to this account.');
        abort_unless(
            $homeworkSubmission->homework?->teacher_id === $teacher->id,
            403,
            'This submission belongs to another teacher\'s homework.',
        );
    }
}
