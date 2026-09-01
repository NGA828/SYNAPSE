<?php

namespace App\Http\Resources;

use App\Models\HomeworkSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin HomeworkSubmission
 */
class HomeworkSubmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'homework_assignment_id' => $this->homework_assignment_id,
            'student_id' => $this->student_id,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student?->id,
                'name' => $this->student?->user?->name,
                'matricule' => $this->student?->matricule,
            ]),
            'content' => $this->when(
                // The answer text is for the author and for the teacher marking
                // it — never in a bulk list response.
                $request->routeIs('api.student.homework.*')
                    || $request->routeIs('api.teacher.homework-submissions.*')
                    || $request->query('include_content') === '1',
                $this->content,
            ),
            'attempts' => $this->attempts,
            'status' => $this->status(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'is_late' => $this->is_late,
            'score' => $this->score,
            'feedback' => $this->feedback,
            'graded_at' => $this->graded_at?->toIso8601String(),
            // Files the student attached to this submission.
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
