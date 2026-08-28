<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\School
 */
class SchoolResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'code' => $this->code,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'status' => $this->status,
            'primary_color' => $this->primary_color,
            'subscription_status' => $this->subscription_status,
            'subscription_expires_at' => $this->subscription_expires_at,
            'plan' => $this->whenLoaded('plan'),
            'users_count' => $this->when($this->users_count !== null, fn () => (int) $this->users_count),
            'students_count' => $this->when($this->students_count !== null, fn () => (int) $this->students_count),
            'created_at' => $this->created_at,
        ];
    }
}
