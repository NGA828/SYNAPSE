<?php

namespace App\Http\Resources;

use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Quiz
 */
class QuizResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'instructions' => $this->instructions,
            'max_score' => $this->max_score,
            'closes_at' => $this->closes_at?->toIso8601String(),
            'time_limit_minutes' => $this->time_limit_minutes,
            'attempts_allowed' => $this->attempts_allowed,
            'is_published' => $this->is_published,
            'is_locked' => $this->is_locked,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            // Present on the teacher list; a student list carries the count
            // from its own withCount.
            'questions_count' => $this->when(
                $this->questions_count !== null || $this->relationLoaded('questions'),
                fn () => $this->questions_count ?? $this->questions->count(),
            ),
            'attempts_count' => $this->when(isset($this->attempts_count), fn () => $this->attempts_count),

            'is_open' => $this->isOpen(),
            'is_closed' => $this->isClosed(),

            'subject' => $this->whenLoaded('subject', fn () => [
                'id' => $this->subject?->id,
                'name' => $this->subject?->name,
                'code' => $this->subject?->code,
            ]),
            'class' => $this->whenLoaded('schoolClass', fn () => [
                'id' => $this->schoolClass?->id,
                'name' => $this->schoolClass?->name,
            ]),
            'semester' => $this->whenLoaded('semester', fn () => [
                'id' => $this->semester?->id,
                'name' => $this->semester?->name,
            ]),

            // Questions only when the caller asked for the paper — and the
            // student route never loads the relation with its answer key.
            'questions' => QuizQuestionResource::collection($this->whenLoaded('questions')),

            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
