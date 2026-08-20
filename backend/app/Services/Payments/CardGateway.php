<?php

namespace App\Services\Payments;

use RuntimeException;

/**
 * International card payments. Requires a PCI-compliant provider (e.g. Stripe)
 * to be configured; refuses to charge until then.
 */
class CardGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'card';
    }

    public function charge(array $payload): array
    {
        throw new RuntimeException('Card payments are not configured. Add a payment provider to enable live charging.');
    }
}
