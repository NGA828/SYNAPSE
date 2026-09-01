<?php

namespace App\Http\Resources;

use App\Models\QuizQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The teacher's view of a question — includes the answer key.
 *
 * The student-facing payload is built by QuizService::paperFor(), which selects
 * only the columns a student may see. Keeping the key out of that path entirely
 * means no client-side filter can leak it.
 *
 * @mixin QuizQuestion
 */
class QuizQuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'prompt' => $this->prompt,
            'options' => $this->options,
            'correct_option' => $this->correct_option,
            'correct_answer' => $this->correctAnswer(),
            'points' => $this->points,
            'sequence' => $this->sequence,
        ];
    }
}
