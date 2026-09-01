<?php

namespace App\Models;

use App\Contracts\HasAttachments;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Quiz extends Model implements HasAttachments
{
    use BelongsToSchool, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'teacher_id',
        'subject_id',
        'class_id',
        'academic_year_id',
        'semester_id',
        'title',
        'instructions',
        'max_score',
        'closes_at',
        'time_limit_minutes',
        'attempts_allowed',
        'is_published',
        'published_at',
        'is_locked',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'closes_at' => 'datetime',
            'published_at' => 'datetime',
            'is_published' => 'boolean',
            'is_locked' => 'boolean',
            'max_score' => 'integer',
            'time_limit_minutes' => 'integer',
            'attempts_allowed' => 'integer',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * @return HasMany<QuizQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('sequence')->orderBy('id');
    }

    /**
     * @return HasMany<QuizAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /**
     * An optional paper the class can download before sitting the quiz.
     *
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * @param  Builder<Quiz>  $query
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Sum of the question points — the raw total a perfect paper earns before
     * it is scaled onto `max_score`.
     */
    public function pointsAvailable(): int
    {
        return (int) $this->questions()->sum('points');
    }

    public function isClosed(): bool
    {
        return $this->closes_at !== null && $this->closes_at->isPast();
    }

    /**
     * Whether a student may still start or submit.
     */
    public function isOpen(): bool
    {
        return $this->is_published && ! $this->isClosed();
    }

    public function ownedByTeacher(Teacher $teacher): bool
    {
        return $this->teacher_id === $teacher->id;
    }

    public function readableByStudent(Student $student): bool
    {
        return $student->enrollments()
            ->where('class_id', $this->class_id)
            ->where('academic_year_id', $this->academic_year_id)
            ->exists();
    }
}
