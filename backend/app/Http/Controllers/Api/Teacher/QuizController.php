<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Concerns\HandlesPagination;
use App\Http\Requests\Teacher\StoreQuizRequest;
use App\Http\Requests\Teacher\UpdateQuizRequest;
use App\Http\Resources\QuizResource;
use App\Models\Quiz;
use App\Services\QuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class QuizController extends Controller
{
    use HandlesPagination;

    public function __construct(
        private readonly QuizService $quizzes,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $teacher = $this->teacher($request);

        return QuizResource::collection(
            $this->quizzes->forTeacher($teacher, $this->perPage($request)),
        );
    }

    /**
     * The full paper, answer key included — this is the teacher's own quiz.
     */
    public function show(Request $request, Quiz $quiz): JsonResponse
    {
        $teacher = $this->teacher($request);

        abort_unless($quiz->teacher_id === $teacher->id, 403, 'This quiz belongs to another teacher.');

        return response()->json([
            'data' => QuizResource::make($quiz->load(['subject', 'schoolClass', 'semester', 'questions', 'attachments'])),
        ]);
    }

    public function store(StoreQuizRequest $request): JsonResponse
    {
        $teacher = $this->teacher($request);

        $quiz = $this->quizzes->create($teacher, $this->payload($request));

        return response()->json(['data' => QuizResource::make($quiz)], 201);
    }

    public function update(UpdateQuizRequest $request, Quiz $quiz): JsonResponse
    {
        $teacher = $this->teacher($request);

        return response()->json([
            'data' => QuizResource::make($this->quizzes->update($teacher, $quiz, $this->payload($request))),
        ]);
    }

    public function destroy(Request $request, Quiz $quiz): JsonResponse
    {
        $this->quizzes->delete($this->teacher($request), $quiz);

        return response()->json(['message' => 'Quiz deleted.']);
    }

    public function publish(Request $request, Quiz $quiz): JsonResponse
    {
        $teacher = $this->teacher($request);

        return response()->json(['data' => QuizResource::make($this->quizzes->publish($teacher, $quiz))]);
    }

    public function unpublish(Request $request, Quiz $quiz): JsonResponse
    {
        $teacher = $this->teacher($request);

        return response()->json(['data' => QuizResource::make($this->quizzes->unpublish($teacher, $quiz))]);
    }

    /**
     * Class results with per-question breakdown.
     */
    public function results(Request $request, Quiz $quiz): JsonResponse
    {
        $teacher = $this->teacher($request);

        $result = $this->quizzes->resultsFor($teacher, $quiz);

        return response()->json([
            'quiz' => QuizResource::make($result['quiz']),
            'students' => $result['students'],
            'questions' => $result['questions'],
            'stats' => $result['stats'],
        ]);
    }

    /**
     * Validated fields plus uploaded files. `validated()` never contains
     * UploadedFile objects, so they are merged in explicitly.
     *
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        return [
            ...$request->validated(),
            'attachments' => $request->file('attachments', []),
            'actor' => $request->user(),
        ];
    }

    private function teacher(Request $request)
    {
        $teacher = $request->user()->teacher;

        abort_unless($teacher, 403, 'No teacher profile is attached to this account.');

        return $teacher;
    }
}
