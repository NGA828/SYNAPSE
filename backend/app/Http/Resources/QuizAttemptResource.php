<?php

namespace App\Http\Resources;

use App\Models\QuizAttempt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuizAttempt
 */
class QuizAttemptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quiz_id' => $this->quiz_id,
            'attempt' => $this->attempt,
            'score' => $this->score,
            'max_score' => $this->whenLoaded('quiz', fn () => $this->quiz?->max_score),
            'correct_count' => $this->correct_count,
            'total_questions' => $this->total_questions,
            'percentage' => $this->percentage(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'feedback' => $this->feedback,
            'is_reviewed' => $this->is_reviewed,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),

            // Only the attempt owner and the teacher who set the paper see the
            // raw answer map; the class roster does not.
            'answers' => $this->when(
                $request->routeIs('api.student.quizzes.*') || $request->routeIs('api.teacher.quiz-attempts.*'),
                fn () => $this->answers,
            ),

            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student?->id,
                'name' => $this->student?->user?->name,
                'matricule' => $this->student?->matricule,
            ]),
            'quiz' => $this->whenLoaded('quiz', fn () => [
                'id' => $this->quiz?->id,
                'title' => $this->quiz?->title,
                'max_score' => $this->quiz?->max_score,
                'subject' => $this->quiz?->subject?->name,
            ]),
        ];
    }
}
