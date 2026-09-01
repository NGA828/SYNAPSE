<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreQuizAttemptRequest;
use App\Http\Resources\QuizAttemptResource;
use App\Http\Resources\QuizResource;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\QuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    public function __construct(
        private readonly QuizService $quizzes,
    ) {}

    /**
     * Published quizzes for the student's class, each with their best attempt
     * and how many attempts are left.
     */
    public function index(Request $request): JsonResponse
    {
        $student = $this->student($request);

        $rows = $this->quizzes->forStudent($student)->map(fn (array $row) => [
            'quiz' => QuizResource::make($row['quiz'])->resolve(),
            'attempt' => $row['attempt']
                ? QuizAttemptResource::make($row['attempt'])->resolve()
                : null,
            'attempts_used' => $row['attempts_used'],
        ]);

        return response()->json([
            'data' => $rows->values(),
            'summary' => $this->quizzes->studentSummary($student),
        ]);
    }

    /**
     * The paper to sit.
     *
     * Deliberately does not use QuizResource for the questions: that resource
     * carries `correct_option`, and the whole point of this endpoint is that the
     * answer key never reaches the browser. The service selects only the safe
     * columns.
     */
    public function paper(Request $request, Quiz $quiz): JsonResponse
    {
        $student = $this->student($request);

        $paper = $this->quizzes->paperFor($student, $quiz);

        return response()->json([
            'quiz' => QuizResource::make($paper['quiz'])->resolve(),
            'questions' => $paper['questions']->map(fn ($question) => [
                'id' => $question->id,
                'prompt' => $question->prompt,
                'options' => $question->options,
                'points' => $question->points,
                'sequence' => $question->sequence,
            ])->values(),
            'attempts_remaining' => $paper['attempts_remaining'],
            'points_available' => $paper['points_available'],
        ]);
    }

    /**
     * Submit the answer sheet; the mark comes back immediately.
     */
    public function submit(StoreQuizAttemptRequest $request, Quiz $quiz): JsonResponse
    {
        $student = $this->student($request);

        $answers = collect($request->validated()['answers'] ?? [])
            ->mapWithKeys(fn ($choice, $questionId) => [(int) $questionId => $choice])
            ->all();

        $attempt = $this->quizzes->submit($student, $quiz, $answers);

        return response()->json([
            'data' => QuizAttemptResource::make($attempt->load('quiz')),
        ], 201);
    }

    /**
     * Per-question review of a submitted attempt, with the answer key.
     */
    public function review(Request $request, QuizAttempt $quizAttempt): JsonResponse
    {
        $student = $this->student($request);

        $review = $this->quizzes->reviewFor($student, $quizAttempt);

        return response()->json([
            'attempt' => QuizAttemptResource::make($review['attempt']),
            'quiz' => QuizResource::make($review['quiz'])->resolve(),
            'questions' => $review['questions'],
        ]);
    }

    private function student(Request $request)
    {
        $student = $request->user()->student;

        abort_unless($student, 403, 'No student profile is attached to this account.');

        return $student;
    }
}
