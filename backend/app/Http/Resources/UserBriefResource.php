<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The minimum identity a person needs to appear in a conversation list or a
 * "start a message" picker — deliberately no email, staff code or any other
 * field the recipient did not ask to share.
 *
 * @mixin User
 */
class UserBriefResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->role,
        ];
    }
}
