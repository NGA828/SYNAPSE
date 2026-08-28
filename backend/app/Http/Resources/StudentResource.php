<?php

namespace App\Http\Resources;

use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Student
 */
class StudentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $yearId = AcademicYear::current()?->id;

        $enrollment = $this->relationLoaded('enrollments')
            ? ($this->enrollments->firstWhere('academic_year_id', $yearId) ?? $this->enrollments->last())
            : null;

        return [
            'id' => $this->id,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'matricule' => $this->matricule,
            'user_id' => $this->user_id,
            'class' => $enrollment?->schoolClass,
            'academic_year' => $enrollment?->academicYear,
            'created_at' => $this->created_at,
        ];
    }
}
