<?php

namespace App\Models;

use App\Contracts\HasAttachments;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Lesson extends Model implements HasAttachments
{
    use BelongsToSchool, HasFactory;

    /**
     * Words-per-minute used to turn a lesson body into a reading-time estimate.
     */
    private const READING_WPM = 200;

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
        'topic',
        'summary',
        'body',
        'minutes',
        'sequence',
        'is_published',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'sequence' => 'integer',
            'minutes' => 'integer',
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
     * Slides, notes and worksheets. Class-visible, so every enrolled student
     * can download them.
     *
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * @param  Builder<Lesson>  $query
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Reading-time estimate for a body the teacher did not time themselves.
     */
    public function estimatedMinutes(): ?int
    {
        if ($this->minutes) {
            return $this->minutes;
        }

        $words = str_word_count(strip_tags((string) $this->body));

        return $words > 0 ? max(1, (int) ceil($words / self::READING_WPM)) : null;
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
