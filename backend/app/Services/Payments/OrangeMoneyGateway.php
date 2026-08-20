<?php

namespace App\Services\Payments;

use RuntimeException;

/**
 * Orange Money (Cameroon) adapter.
 *
 * Not yet provisioned: real credentials are required before live charging.
 * Until then this gateway refuses to process payments rather than faking
 * success in production.
 */
class OrangeMoneyGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'orange_money';
    }

    public function charge(array $payload): array
    {
        throw new RuntimeException('Orange Money is not configured. Add provider credentials to enable live charging.');
    }
}
