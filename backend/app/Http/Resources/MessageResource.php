<?php

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Message
 */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'body' => $this->body,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            // Lets the client right-align its own bubbles without comparing
            // ids against the session user.
            'is_own' => $request->user() !== null
                && (int) $this->sender_id === (int) $request->user()->id,

            'sender' => UserBriefResource::make($this->whenLoaded('sender')),
        ];
    }
}
