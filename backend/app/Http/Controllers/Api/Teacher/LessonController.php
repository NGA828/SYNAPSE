<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Concerns\HandlesPagination;
use App\Http\Requests\Teacher\StoreLessonRequest;
use App\Http\Requests\Teacher\UpdateLessonRequest;
use App\Http\Resources\LessonResource;
use App\Models\Lesson;
use App\Services\LessonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LessonController extends Controller
{
    use HandlesPagination;

    public function __construct(
        private readonly LessonService $lessons,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $teacher = $this->teacher($request);

        return LessonResource::collection(
            $this->lessons->forTeacher($teacher, $this->perPage($request)),
        );
    }

    public function show(Request $request, Lesson $lesson): JsonResponse
    {
        $teacher = $this->teacher($request);

        abort_unless($lesson->teacher_id === $teacher->id, 403, 'This lesson belongs to another teacher.');

        return response()->json([
            'data' => LessonResource::make($lesson->load(['subject', 'schoolClass', 'semester', 'attachments'])),
        ]);
    }

    public function store(StoreLessonRequest $request): JsonResponse
    {
        $teacher = $this->teacher($request);

        $lesson = $this->lessons->create($teacher, $this->payload($request));

        return response()->json(['data' => LessonResource::make($lesson)], 201);
    }

    public function update(UpdateLessonRequest $request, Lesson $lesson): JsonResponse
    {
        $teacher = $this->teacher($request);

        return response()->json([
            'data' => LessonResource::make($this->lessons->update($teacher, $lesson, $this->payload($request))),
        ]);
    }

    public function destroy(Request $request, Lesson $lesson): JsonResponse
    {
        $this->lessons->delete($this->teacher($request), $lesson);

        return response()->json(['message' => 'Lesson deleted.']);
    }

    public function publish(Request $request, Lesson $lesson): JsonResponse
    {
        $teacher = $this->teacher($request);

        return response()->json(['data' => LessonResource::make($this->lessons->publish($teacher, $lesson))]);
    }

    public function unpublish(Request $request, Lesson $lesson): JsonResponse
    {
        $teacher = $this->teacher($request);

        return response()->json(['data' => LessonResource::make($this->lessons->unpublish($teacher, $lesson))]);
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
