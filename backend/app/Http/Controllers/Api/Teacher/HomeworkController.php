<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Concerns\HandlesPagination;
use App\Http\Requests\Teacher\StoreHomeworkRequest;
use App\Http\Requests\Teacher\UpdateHomeworkRequest;
use App\Http\Resources\HomeworkAssignmentResource;
use App\Models\HomeworkAssignment;
use App\Services\HomeworkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HomeworkController extends Controller
{
    use HandlesPagination;

    public function __construct(
        private readonly HomeworkService $homework,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $teacher = $this->teacher($request);

        return HomeworkAssignmentResource::collection(
            $this->homework->forTeacher($teacher, $this->perPage($request)),
        );
    }

    public function show(Request $request, HomeworkAssignment $homeworkAssignment): JsonResponse
    {
        $teacher = $this->teacher($request);

        abort_unless($homeworkAssignment->teacher_id === $teacher->id, 403, 'This homework belongs to another teacher.');

        return response()->json([
            'data' => HomeworkAssignmentResource::make($homeworkAssignment->load(['subject', 'schoolClass', 'semester', 'academicYear'])),
        ]);
    }

    public function store(StoreHomeworkRequest $request): JsonResponse
    {
        $teacher = $this->teacher($request);

        $homework = $this->homework->create($teacher, $this->payload($request));

        return response()->json(['data' => HomeworkAssignmentResource::make($homework)], 201);
    }

    public function update(UpdateHomeworkRequest $request, HomeworkAssignment $homeworkAssignment): JsonResponse
    {
        $teacher = $this->teacher($request);

        return response()->json([
            'data' => HomeworkAssignmentResource::make(
                $this->homework->update($teacher, $homeworkAssignment, $this->payload($request)),
            ),
        ]);
    }

    /**
     * Validated fields plus the uploaded files. `validated()` never contains
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

    public function destroy(Request $request, HomeworkAssignment $homeworkAssignment): JsonResponse
    {
        $this->homework->delete($this->teacher($request), $homeworkAssignment);

        return response()->json(['message' => 'Homework deleted.']);
    }

    public function publish(Request $request, HomeworkAssignment $homeworkAssignment): JsonResponse
    {
        $teacher = $this->teacher($request);

        return response()->json([
            'data' => HomeworkAssignmentResource::make($this->homework->publish($teacher, $homeworkAssignment)),
        ]);
    }

    public function unpublish(Request $request, HomeworkAssignment $homeworkAssignment): JsonResponse
    {
        $teacher = $this->teacher($request);

        return response()->json([
            'data' => HomeworkAssignmentResource::make($this->homework->unpublish($teacher, $homeworkAssignment)),
        ]);
    }

    /**
     * The class roster for one homework, with each student's submission.
     */
    public function submissions(Request $request, HomeworkAssignment $homeworkAssignment): JsonResponse
    {
        $teacher = $this->teacher($request);

        $result = $this->homework->submissionsFor($teacher, $homeworkAssignment);

        return response()->json([
            'assignment' => HomeworkAssignmentResource::make($result['assignment']),
            'students' => $result['students'],
            'stats' => $result['stats'],
        ]);
    }

    private function teacher(Request $request)
    {
        $teacher = $request->user()->teacher;

        abort_unless($teacher, 403, 'No teacher profile is attached to this account.');

        return $teacher;
    }
}
