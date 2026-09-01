<?php

namespace App\Http\Resources;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Conversation
 */
class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $other = $this->otherParticipant($request->user()?->id);

        return [
            'id' => $this->id,
            'last_message_at' => $this->last_message_at?->toIso8601String(),

            // The other end, never the caller.
            'participant' => $other
                ? UserBriefResource::make($other)
                : null,

            'unread_count' => $this->when(isset($this->unread_count), fn () => (int) $this->unread_count),
        ];
    }
}
