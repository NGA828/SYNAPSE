<?php

namespace App\Services;

use App\Models\GradeComponent;
use App\Models\School;
use Illuminate\Validation\ValidationException;

class GradeComponentService
{
    /**
     * Components for a school (defaults + per-subject), grouped.
     *
     * @return array{default: mixed, by_subject: mixed}
     */
    public function index(School $school): array
    {
        $defaults = GradeComponent::query()
            ->where('school_id', $school->id)
            ->whereNull('subject_id')
            ->orderBy('sequence')
            ->get();

        $bySubject = GradeComponent::query()
            ->with('subject')
            ->where('school_id', $school->id)
            ->whereNotNull('subject_id')
            ->orderBy('sequence')
            ->get()
            ->groupBy('subject_id');

        return [
            'default' => $defaults,
            'by_subject' => $bySubject,
        ];
    }

    /**
     * Create a component (school default or subject-specific).
     *
     * @param  array{name: string, weight: float, subject_id?: ?int}  $data
     */
    public function create(School $school, array $data): GradeComponent
    {
        return GradeComponent::create([
            'school_id' => $school->id,
            'subject_id' => $data['subject_id'] ?? null,
            'name' => $data['name'],
            'weight' => $data['weight'],
            'sequence' => GradeComponent::query()
                ->where('school_id', $school->id)
                ->where('subject_id', $data['subject_id'] ?? null)
                ->max('sequence') + 1,
        ]);
    }

    public function update(GradeComponent $component, array $data): GradeComponent
    {
        $component->update($data);

        return $component->fresh();
    }

    public function delete(GradeComponent $component): void
    {
        $component->delete();
    }
}
