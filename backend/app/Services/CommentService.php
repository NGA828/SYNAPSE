<?php

namespace App\Services;

use App\Contracts\CommentWriter;
use App\Models\AcademicYear;
use App\Models\ReportCardComment;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\Ai\CommentEvidence;
use App\Services\Ai\DeterministicCommentWriter;
use Illuminate\Support\Str;

/**
 * Report-card appreciations.
 *
 * The flow is draft → edit → lock → print, and the ordering is the point. A
 * model (or the deterministic writer) produces a *draft*; a teacher reads and
 * edits it; the locked text is what appears on the PDF. Nothing generated ever
 * reaches a report card unapproved.
 *
 * Which writer drafts is decided here rather than at the call site:
 * the deterministic writer always works, and an external model is used only when
 * AI is enabled, configured, and the school's plan carries `ai_assistant`.
 */
class CommentService
{
    public function __construct(
        private readonly GradeService $grades,
        private readonly DeterministicCommentWriter $deterministic,
        private readonly CommentWriter $configured,
        private readonly SubscriptionService $subscriptions,
        private readonly AuditService $audit,
    ) {}

    /**
     * Draft the overall comment for a student. Nothing is saved.
     *
     * @return array{body: string, source: string, ai_available: bool}
     */
    public function draft(Student $student, ?Semester $semester = null, ?string $locale = null): array
    {
        $writer = $this->writer($student);

        return [
            'body' => $writer->write($this->evidence($student, $semester, $locale)),
            'source' => $writer->name() === 'deterministic'
                ? ReportCardComment::SOURCE_GENERATED
                : ReportCardComment::SOURCE_AI,
            'ai_available' => $this->aiAvailable($student),
        ];
    }

    /**
     * The text that belongs on the PDF.
     *
     * A locked comment written by a person wins outright. Otherwise the comment
     * is generated on the fly — which keeps the common case free of rows to
     * maintain, while still letting a teacher override any individual card.
     */
    public function resolve(Student $student, ?AcademicYear $year = null, ?Semester $semester = null, ?string $locale = null): string
    {
        return $this->resolveFromArray(
            $this->grades->reportCard($student, $year, $semester),
            $locale ?? $student->user?->locale,
        );
    }

    /**
     * The same resolution, from a report card that has already been computed.
     *
     * Report-card generation already holds this array; recomputing it here would
     * re-rank the whole class a second time per student, which for a class of
     * sixty is sixty redundant passes over the grade book.
     *
     * @param  array<string, mixed>  $reportCard  Output of GradeService::reportCard()
     */
    public function resolveFromArray(array $reportCard, ?string $locale = null): string
    {
        $student = $reportCard['student'] ?? null;

        if (! $student instanceof Student) {
            return '';
        }

        $semester = $reportCard['semester'] ?? null;

        $locked = ReportCardComment::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', ($reportCard['academic_year'] ?? null)?->id)
            ->where(fn ($query) => $semester instanceof Semester
                ? $query->where('semester_id', $semester->id)
                : $query->whereNull('semester_id'))
            ->whereNull('subject_id')
            ->where('is_locked', true)
            ->first();

        if ($locked) {
            return $locked->body;
        }

        return $this->writeFromArray($reportCard, $locale ?? $student->user?->locale);
    }

    /**
     * Draft from an already-computed report card. Nothing is saved.
     *
     * @param  array<string, mixed>  $reportCard
     */
    public function writeFromArray(array $reportCard, ?string $locale = null): string
    {
        $student = $reportCard['student'] ?? null;
        $evidence = CommentEvidence::fromReportCard($reportCard, $locale);

        return $student instanceof Student
            ? $this->writer($student)->write($evidence)
            : $this->deterministic->write($evidence);
    }

    /**
     * Save a teacher's text. Writing always marks the row as human-authored, so
     * editing an AI draft correctly changes its provenance.
     */
    public function save(
        User $actor,
        Student $student,
        string $body,
        ?Subject $subject = null,
        ?AcademicYear $year = null,
        ?Semester $semester = null,
        bool $lock = false,
    ): ReportCardComment {
        $year ??= AcademicYear::current();

        abort_unless($student->school_id === $actor->school_id, 403, 'That student belongs to another school.');

        $trimmed = trim($body);

        abort_if($trimmed === '', 422, 'A comment cannot be empty.');

        $maxLength = (int) config('synapse.comments.max_length', 400);

        abort_if(
            Str::length($trimmed) > $maxLength,
            422,
            "A comment cannot exceed {$maxLength} characters.",
        );

        $comment = ReportCardComment::query()->updateOrCreate(
            [
                'student_id' => $student->id,
                'subject_id' => $subject?->id,
                'academic_year_id' => $year?->id,
                'semester_id' => $semester?->id,
            ],
            [
                'school_id' => $student->school_id,
                'body' => $trimmed,
                // A person has now vouched for this text.
                'source' => ReportCardComment::SOURCE_TEACHER,
                'is_locked' => $lock,
                'written_by' => $actor->id,
            ],
        );

        $this->audit->log(
            $student->school,
            $actor,
            $lock ? 'report_card_comment.locked' : 'report_card_comment.saved',
            'report_card_comment',
            $comment->id,
            [
                'student_id' => $student->id,
                'subject_id' => $subject?->id,
                'semester_id' => $semester?->id,
                'ai_generated' => false,
                'characters' => Str::length($trimmed),
            ],
        );

        return $comment;
    }

    /**
     * Record an unedited generated draft. Kept separate from `save()` because
     * provenance differs: nobody has approved this text yet.
     */
    public function recordDraft(
        Student $student,
        string $body,
        string $source,
        ?AcademicYear $year = null,
        ?Semester $semester = null,
        ?Subject $subject = null,
    ): ReportCardComment {
        $year ??= AcademicYear::current();

        return ReportCardComment::query()->updateOrCreate(
            [
                'student_id' => $student->id,
                'subject_id' => $subject?->id,
                'academic_year_id' => $year?->id,
                'semester_id' => $semester?->id,
            ],
            [
                'school_id' => $student->school_id,
                'body' => trim($body),
                'source' => $source,
                // A draft is never locked: locking is a human action.
                'is_locked' => false,
            ],
        );
    }

    public function evidence(Student $student, ?Semester $semester = null, ?string $locale = null): CommentEvidence
    {
        return CommentEvidence::fromReportCard(
            $this->reportCardFor($student, $semester),
            $locale ?? $student->user?->locale,
        );
    }

    /**
     * @return array<string, mixed> Output of GradeService::reportCard()
     */
    public function reportCardFor(Student $student, ?Semester $semester = null): array
    {
        return $this->grades->reportCard($student, null, $semester);
    }

    /**
     * Whether this school may draft with an external model.
     *
     * The plan flag gates the *provider*, not the feature: every school gets
     * evidence-based comments from the deterministic writer, and `ai_assistant`
     * is what upgrades the phrasing.
     */
    public function aiAvailable(Student $student): bool
    {
        if (! config('ai.enabled') || ! config('ai.key') || ! config('ai.model')) {
            return false;
        }

        if (config('ai.driver', 'deterministic') !== 'http') {
            return false;
        }

        $school = $student->school;

        return $school !== null && $this->subscriptions->hasFeature($school, 'ai_assistant');
    }

    private function writer(Student $student): CommentWriter
    {
        return $this->aiAvailable($student) ? $this->configured : $this->deterministic;
    }
}
