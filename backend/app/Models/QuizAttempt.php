<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttempt extends Model
{
    use BelongsToSchool, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'quiz_id',
        'student_id',
        'school_id',
        'answers',
        'correct_count',
        'total_questions',
        'score',
        'attempt',
        'started_at',
        'submitted_at',
        'feedback',
        'is_reviewed',
        'reviewed_at',
        'reviewed_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'correct_count' => 'integer',
            'total_questions' => 'integer',
            'score' => 'float',
            'attempt' => 'integer',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'is_reviewed' => 'boolean',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'reviewed_by');
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    /**
     * Percentage correct, used for the class analytics and the at-risk view.
     */
    public function percentage(): ?float
    {
        if ($this->total_questions === 0) {
            return null;
        }

        return round($this->correct_count / $this->total_questions * 100, 1);
    }
}
