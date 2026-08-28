<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'provider' => $this->provider,
            'method' => $this->method,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'sandbox' => (bool) $this->sandbox,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
            'school' => $this->whenLoaded('school', fn () => [
                'id' => $this->school->id,
                'name' => $this->school->name,
                'slug' => $this->school->slug,
            ]),
            'subscription_id' => $this->subscription_id,
        ];
    }
}
