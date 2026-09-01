<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\LessonResource;
use App\Models\Lesson;
use App\Services\LessonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    public function __construct(
        private readonly LessonService $lessons,
    ) {}

    /**
     * Published lessons for the student's class, grouped subject → topic so the
     * page reads like a syllabus.
     */
    public function index(Request $request): JsonResponse
    {
        $student = $this->student($request);

        $grouped = $this->lessons->forStudent($student)->map(
            fn ($topics) => $topics->map(
                fn ($lessons) => $lessons->map(
                    fn (Lesson $lesson) => LessonResource::make($lesson)->resolve(),
                )->values(),
            ),
        );

        return response()->json([
            'data' => $grouped,
            'summary' => $this->lessons->studentSummary($student),
        ]);
    }

    /**
     * Read one lesson in full. Gated to the student's own class and to
     * published lessons only.
     */
    public function show(Request $request, Lesson $lesson): JsonResponse
    {
        $student = $this->student($request);

        abort_unless($lesson->is_published, 403, 'This lesson is not available yet.');
        abort_unless(
            $lesson->readableByStudent($student),
            403,
            'You are not enrolled in the class this lesson was written for.',
        );

        return response()->json([
            'data' => LessonResource::make($lesson->load(['subject', 'schoolClass', 'attachments'])),
        ]);
    }

    private function student(Request $request)
    {
        $student = $request->user()->student;

        abort_unless($student, 403, 'No student profile is attached to this account.');

        return $student;
    }
}
