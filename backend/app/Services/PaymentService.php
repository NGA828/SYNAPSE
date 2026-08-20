<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\School;
use App\Services\Payments\CardGateway;
use App\Services\Payments\MockPaymentGateway;
use App\Services\Payments\MtnMobileMoneyGateway;
use App\Services\Payments\OrangeMoneyGateway;
use App\Services\Payments\PaymentGateway;

class PaymentService
{
    /**
     * Resolve a gateway by provider name (mock is dev-only).
     */
    public function gateway(?string $provider = null): PaymentGateway
    {
        return match ($provider) {
            'mtn_momo' => new MtnMobileMoneyGateway(),
            'orange_money' => new OrangeMoneyGateway(),
            'card' => new CardGateway(),
            default => new MockPaymentGateway(),
        };
    }

    /**
     * Charge a school and record the payment.
     *
     * @param  array<string, mixed>  $payload
     */
    public function charge(
        School $school,
        float $amount,
        ?string $currency,
        ?string $provider,
        ?string $method = null,
        ?int $subscriptionId = null,
    ): Payment {
        $gateway = $this->gateway($provider);
        $result = $gateway->charge(['method' => $method]);

        return Payment::create([
            'school_id' => $school->id,
            'subscription_id' => $subscriptionId,
            'provider' => $gateway->name(),
            'method' => $result['method'],
            'amount' => $amount,
            'currency' => $currency ?? config('synapse.currency', 'XAF'),
            'status' => $result['status'],
            'reference' => $result['reference'],
            'sandbox' => $gateway instanceof MockPaymentGateway,
            'paid_at' => $result['status'] === Payment::STATUS_SUCCEEDED ? now() : null,
        ]);
    }

    /**
     * Payments belonging to a school (for its billing dashboard).
     */
    public function forSchool(School $school)
    {
        return $school->payments()->latest()->get();
    }
}
