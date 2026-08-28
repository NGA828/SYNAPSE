<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Document
 */
class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'verification_code' => $this->verification_code,
            'request_id' => $this->request_id,
            'student_id' => $this->student_id,
            'meta' => $this->meta,
            'created_at' => $this->created_at,
        ];
    }
}
