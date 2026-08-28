<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\AuditLog
 */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'entity_type' => class_basename((string) $this->entity_type),
            'entity_id' => $this->entity_id,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'role' => $this->user?->role,
            ]),
            'school' => $this->whenLoaded('school', fn () => [
                'id' => $this->school?->id,
                'name' => $this->school?->name,
            ]),
        ];
    }
}
