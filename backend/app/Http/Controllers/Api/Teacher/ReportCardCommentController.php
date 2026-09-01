<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreReportCardCommentRequest;
use App\Models\AcademicYear;
use App\Models\ReportCardComment;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Services\AcademicScopeService;
use App\Services\CommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Report-card appreciations, teacher side.
 *
 * The endpoint shape enforces the workflow: `draft` returns text and saves
 * nothing, so a teacher can regenerate freely; `update` records what they
 * actually approved. Nothing generated reaches a report card without passing
 * through the second one.
 */
class ReportCardCommentController extends Controller
{
    public function __construct(
        private readonly CommentService $comments,
        private readonly AcademicScopeService $scope,
    ) {}

    /**
     * Generate a draft. Deliberately does not persist: a draft is a suggestion.
     */
    public function draft(Request $request, Student $student): JsonResponse
    {
        $this->authorize($request, $student);

        $semester = $this->semester($request);

        $draft = $this->comments->draft($student, $semester, $request->user()->locale);

        return response()->json(['data' => $draft]);
    }

    /**
     * The comment currently in force, plus any saved draft for comparison.
     */
    public function show(Request $request, Student $student): JsonResponse
    {
        $this->authorize($request, $student);

        $semester = $this->semester($request);
        $subject = $this->subject($request);
        $year = AcademicYear::current();

        $saved = ReportCardComment::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $year?->id)
            ->where('subject_id', $subject?->id)
            ->where(fn ($query) => $semester
                ? $query->where('semester_id', $semester->id)
                : $query->whereNull('semester_id'))
            ->first();

        return response()->json([
            'data' => [
                'comment' => $saved ? $this->present($saved) : null,
                // What the card will say if the teacher writes nothing.
                'effective' => $saved?->is_locked
                    ? $saved->body
                    : $this->comments->writeFromArray(
                        $this->comments->reportCardFor($student, $semester),
                        $request->user()->locale,
                    ),
                'ai_available' => $this->comments->aiAvailable($student),
            ],
        ]);
    }

    /**
     * Save what the teacher approved, and optionally lock it.
     */
    public function update(StoreReportCardCommentRequest $request, Student $student): JsonResponse
    {
        $this->authorize($request, $student);

        $data = $request->validated();

        $comment = $this->comments->save(
            actor: $request->user(),
            student: $student,
            body: $data['body'],
            subject: $this->subject($request),
            year: AcademicYear::current(),
            semester: $this->semester($request),
            lock: (bool) ($data['lock'] ?? false),
        );

        return response()->json(['data' => $this->present($comment)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ReportCardComment $comment): array
    {
        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'source' => $comment->source,
            'is_locked' => $comment->is_locked,
            'subject_id' => $comment->subject_id,
            'semester_id' => $comment->semester_id,
            'written_by' => $comment->written_by,
            'updated_at' => $comment->updated_at,
        ];
    }

    /**
     * A teacher may only comment on pupils in a class they hold. Admins may
     * comment on any pupil in their school.
     */
    private function authorize(Request $request, Student $student): void
    {
        abort_unless(
            $this->scope->sees($request->user(), $student),
            403,
            'That student is not in one of your classes.',
        );
    }

    private function semester(Request $request): ?Semester
    {
        $id = $request->query('semester_id');

        return $id ? Semester::find($id) : null;
    }

    private function subject(Request $request): ?Subject
    {
        $id = $request->query('subject_id') ?? $request->input('subject_id');

        return $id ? Subject::find($id) : null;
    }
}
