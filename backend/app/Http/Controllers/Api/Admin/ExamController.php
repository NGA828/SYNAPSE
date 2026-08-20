<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreExamRequest;
use App\Models\Exam;
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

    public function index(Request $request): JsonResponse
    {
        $classId = $request->query('class_id');
        $class = $classId ? SchoolClass::findOrFail($classId) : null;

        return response()->json([
            'data' => $this->examService->forSchool($request->user()->school, $class),
        ]);
    }

    public function store(StoreExamRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json([
            'data' => $this->examService->create($request->user()->school, $data),
        ], 201);
    }

    public function destroy(Exam $exam): JsonResponse
    {
        $this->examService->delete($exam);

        return response()->json(['message' => 'Exam session removed.']);
    }

    /**
     * Result compilation: ranked students per class + subject.
     */
    public function ranking(Request $request): JsonResponse
    {
        $data = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
        ]);

        $semester = $data['semester_id'] ?? null ? Semester::find($data['semester_id']) : null;

        return response()->json(
            $this->examService->ranking(
                $request->user()->school,
                SchoolClass::findOrFail($data['class_id']),
                Subject::findOrFail($data['subject_id']),
                null,
                $semester,
            ),
        );
    }
}
