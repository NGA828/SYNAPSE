<?php

namespace App\Models;

use App\Contracts\HasAttachments;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class HomeworkSubmission extends Model implements HasAttachments
{
    use BelongsToSchool, HasFactory;

    /**
     * Submission lifecycle, derived rather than stored so the two can never
     * drift apart. Order matters — the UI renders these as a stepper.
     */
    public const STATUS_NOT_SUBMITTED = 'not_submitted';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_LATE = 'late';

    public const STATUS_GRADED = 'graded';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'homework_assignment_id',
        'student_id',
        'content',
        'attempts',
        'submitted_at',
        'is_late',
        'score',
        'feedback',
        'graded_by',
        'graded_at',
        'returned_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'float',
            'attempts' => 'integer',
            'is_late' => 'boolean',
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function homework(): BelongsTo
    {
        return $this->belongsTo(HomeworkAssignment::class, 'homework_assignment_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'graded_by');
    }

    /**
     * Files the student attached to their submission. Private: only they and
     * the teacher who set the work can read them.
     *
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function isGraded(): bool
    {
        return $this->score !== null;
    }

    /**
     * The single source of truth for a submission's display status.
     */
    public function status(): string
    {
        return match (true) {
            $this->isGraded() => self::STATUS_GRADED,
            $this->is_late => self::STATUS_LATE,
            default => self::STATUS_SUBMITTED,
        };
    }

    /**
     * The teacher who set the parent homework owns this submission's files.
     */
    public function ownedByTeacher(Teacher $teacher): bool
    {
        return $this->homework?->ownedByTeacher($teacher) ?? false;
    }

    /**
     * A submission is never a class-wide document; only its author and the
     * owning teacher read it (AttachmentService handles the author case).
     */
    public function readableByStudent(Student $student): bool
    {
        return false;
    }
}
