<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A report-card appreciation.
 *
 * The row exists so that a human is in the loop: a comment can be drafted, read,
 * edited and locked, and the PDF uses whatever was locked rather than whatever a
 * model produced. `source` keeps provenance so an unedited draft is
 * distinguishable from something a teacher wrote.
 */
class ReportCardComment extends Model
{
    use BelongsToSchool, HasFactory;

    /** Written by a member of staff. */
    public const SOURCE_TEACHER = 'teacher';

    /** Produced by the deterministic writer. */
    public const SOURCE_GENERATED = 'generated';

    /** Produced by an external model and not yet edited. */
    public const SOURCE_AI = 'ai';

    public const SOURCES = [
        self::SOURCE_TEACHER,
        self::SOURCE_GENERATED,
        self::SOURCE_AI,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'student_id',
        'subject_id',
        'academic_year_id',
        'semester_id',
        'body',
        'source',
        'is_locked',
        'written_by',
    ];

    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'written_by');
    }

    public function wasAiDrafted(): bool
    {
        return $this->source === self::SOURCE_AI;
    }
}
