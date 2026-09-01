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

class HomeworkAssignment extends Model implements HasAttachments
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
        'due_at',
        'is_published',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'published_at' => 'datetime',
            'is_published' => 'boolean',
            'max_score' => 'integer',
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

    public function submissions(): HasMany
    {
        return $this->hasMany(HomeworkSubmission::class);
    }

    /**
     * Files the teacher attached to the brief (PDF/Word) for the class to
     * download.
     *
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Only homework a student/teacher should actually see.
     *
     * @param  Builder<HomeworkAssignment>  $query
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function isPastDue(): bool
    {
        return $this->due_at !== null && $this->due_at->isPast();
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

    /**
     * Whether a student may still submit or replace their work.
     */
    public function isOpenForSubmission(): bool
    {
        return $this->is_published && ! $this->isPastDue();
    }
}
