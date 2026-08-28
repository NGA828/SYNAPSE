<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\DocumentRequest
 */
class DocumentRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'type' => $this->type,
            'reason' => $this->reason,
            'status' => $this->status,
            'admin_note' => $this->admin_note,
            'resolved_at' => $this->resolved_at,
            'created_at' => $this->created_at,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'name' => $this->student->user?->name,
                'matricule' => $this->student->matricule,
            ]),
            'documents' => DocumentResource::collection($this->whenLoaded('documents')),
        ];
    }
}
