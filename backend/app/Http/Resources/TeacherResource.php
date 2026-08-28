<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Teacher
 */
class TeacherResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'staff_no' => $this->staff_no,
            'user_id' => $this->user_id,
            'assignments_count' => $this->when(
                $this->teaching_assignments_count !== null,
                fn () => (int) $this->teaching_assignments_count,
            ),
            'created_at' => $this->created_at,
        ];
    }
}
