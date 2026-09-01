<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\ReviewQuizAttemptRequest;
use App\Http\Resources\QuizAttemptResource;
use App\Models\QuizAttempt;
use App\Services\QuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizAttemptController extends Controller
{
    public function __construct(
        private readonly QuizService $quizzes,
    ) {}

    /**
     * One student's attempt in full, including their answers and the key.
     */
    public function show(Request $request, QuizAttempt $quizAttempt): JsonResponse
    {
        $teacher = $this->teacher($request);
        $quiz = $quizAttempt->quiz;

        abort_unless($quiz, 404, 'This attempt no longer belongs to any quiz.');
        abort_unless($quiz->teacher_id === $teacher->id, 403, 'This quiz belongs to another teacher.');

        return response()->json([
            'data' => QuizAttemptResource::make($quizAttempt->load(['student.user', 'quiz.subject'])),
        ]);
    }

    /**
     * Add commentary to an auto-marked attempt and release it to the student.
     */
    public function review(ReviewQuizAttemptRequest $request, QuizAttempt $quizAttempt): JsonResponse
    {
        $teacher = $this->teacher($request);

        $attempt = $this->quizzes->review($teacher, $quizAttempt, $request->validated()['feedback'] ?? null);

        return response()->json([
            'data' => QuizAttemptResource::make($attempt),
        ]);
    }

    private function teacher(Request $request)
    {
        $teacher = $request->user()->teacher;

        abort_unless($teacher, 403, 'No teacher profile is attached to this account.');

        return $teacher;
    }
}
