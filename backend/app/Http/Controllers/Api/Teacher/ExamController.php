<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Subject;
use App\Services\ExamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    public function __construct(
        private readonly ExamService $examService,
    ) {}

    /**
     * Exam sessions for the authenticated teacher's assignments.
     */
    public function index(Request $request): JsonResponse
    {
        $teacher = $request->user()->teacher;

        abort_unless($teacher, 403, 'No teacher profile is attached to this account.');

        return response()->json([
            'data' => $this->examService->forTeacher($teacher),
        ]);
    }

    /**
     * Ranking for one of the teacher's assignments (re-verified).
     */
    public function ranking(Request $request): JsonResponse
    {
        $teacher = $request->user()->teacher;

        abort_unless($teacher, 403, 'No teacher profile is attached to this account.');

        $data = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
        ]);

        $assigned = $teacher->teachingAssignments()
            ->where('class_id', $data['class_id'])
            ->where('subject_id', $data['subject_id'])
            ->exists();

        abort_unless($assigned, 403, 'You are not assigned to teach this subject in this class.');

        $semester = ! empty($data['semester_id']) ? Semester::find($data['semester_id']) : null;

        return response()->json(
            $this->examService->ranking(
                $teacher->school,
                SchoolClass::findOrFail($data['class_id']),
                Subject::findOrFail($data['subject_id']),
                null,
                $semester,
            ),
        );
    }
}
