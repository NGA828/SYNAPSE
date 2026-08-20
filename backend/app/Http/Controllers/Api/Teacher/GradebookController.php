<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreGradesRequest;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Subject;
use App\Services\GradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradebookController extends Controller
{
    public function __construct(
        private readonly GradeService $gradeService,
    ) {}

    /**
     * Read the gradebook for a teacher's assignment (optionally a semester).
     *
     * Gated by the `teaching.assignment` middleware (route level).
     */
    public function index(Request $request, SchoolClass $schoolClass, Subject $subject): JsonResponse
    {
        $teacher = $request->user()->teacher;

        abort_unless($teacher, 403, 'No teacher profile is attached to this account.');

        return response()->json(
            $this->gradeService->gradebook($teacher, $schoolClass, $subject, null, $this->semester($request)),
        );
    }

    /**
     * Save grades for a teacher's assignment.
     */
    public function store(
        StoreGradesRequest $request,
        SchoolClass $schoolClass,
        Subject $subject,
    ): JsonResponse {
        $teacher = $request->user()->teacher;

        abort_unless($teacher, 403, 'No teacher profile is attached to this account.');

        $gradebook = $this->gradeService->save(
            $teacher,
            $schoolClass,
            $subject,
            $request->validated()['grades'],
            null,
            $this->semester($request),
        );

        return response()->json($gradebook);
    }

    private function semester(Request $request): ?Semester
    {
        $id = $request->query('semester_id');

        return $id ? Semester::query()->findOrFail((int) $id) : null;
    }
}
