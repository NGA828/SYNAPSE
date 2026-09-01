<?php

namespace App\Http\Resources;

use App\Models\HomeworkAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin HomeworkAssignment
 */
class HomeworkAssignmentResource extends JsonResource
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
            'due_at' => $this->due_at?->toIso8601String(),
            'is_published' => $this->is_published,
            'is_past_due' => $this->isPastDue(),
            'is_open' => $this->isOpenForSubmission(),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
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
            'academic_year' => $this->whenLoaded('academicYear', fn () => [
                'id' => $this->academicYear?->id,
                'name' => $this->academicYear?->name,
            ]),
            // Teacher-side roll-up, present only when the controller asked for
            // the withCount() aggregates.
            'submissions_count' => $this->when(isset($this->submissions_count), $this->submissions_count),
            'graded_count' => $this->when(isset($this->graded_count), $this->graded_count),
            // Brief documents the teacher attached for the class to download.
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
