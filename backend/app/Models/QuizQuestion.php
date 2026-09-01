<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizQuestion extends Model
{
    use BelongsToSchool, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'quiz_id',
        'school_id',
        'prompt',
        'options',
        'correct_option',
        'points',
        'sequence',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'correct_option' => 'integer',
            'points' => 'integer',
            'sequence' => 'integer',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /**
     * Whether a chosen option index is the right one.
     *
     * Anything outside the option range — including null for a skipped
     * question — is simply wrong, never an error.
     */
    public function isCorrect(?int $choice): bool
    {
        return $choice !== null && $choice === $this->correct_option;
    }

    /**
     * The text of the correct option, for the post-submission review.
     */
    public function correctAnswer(): ?string
    {
        return $this->options[$this->correct_option] ?? null;
    }
}
