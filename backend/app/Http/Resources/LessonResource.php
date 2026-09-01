<?php

namespace App\Http\Resources;

use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lesson
 */
class LessonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'topic' => $this->topic,
            'summary' => $this->summary,
            // The full text is only sent when the caller opened the lesson;
            // list views get the summary alone.
            'body' => $this->when(
                $request->routeIs('*.show') || $request->query('include_body') === '1',
                $this->body,
            ),
            'minutes' => $this->estimatedMinutes(),
            'sequence' => $this->sequence,
            'is_published' => $this->is_published,
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
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
