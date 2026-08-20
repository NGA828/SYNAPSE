<?php

namespace App\Services\Payments;

use App\Models\Payment;

/**
 * Development-only gateway. It is ALWAYS flagged `sandbox` and must never be
 * enabled in production.
 */
class MockPaymentGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'mock';
    }

    public function charge(array $payload): array
    {
        return [
            'status' => Payment::STATUS_SUCCEEDED,
            'reference' => 'MOCK-'.strtoupper(bin2hex(random_bytes(6))),
            'method' => $payload['method'] ?? 'mock',
        ];
    }
}
