<?php

namespace App\Http\Resources;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Attachment
 */
class AttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'size_label' => $this->humanSize(),
            'visibility' => $this->visibility,
            'uploaded_by_role' => $this->uploaded_by_role,
            'created_at' => $this->created_at?->toIso8601String(),
            // The browser builds the download URL from this; the endpoint
            // re-authorizes on every request.
            'download_url' => "/attachments/{$this->id}/download",
        ];
    }
}
