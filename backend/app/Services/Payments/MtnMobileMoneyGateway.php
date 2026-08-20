<?php

namespace App\Services\Payments;

use RuntimeException;

/**
 * MTN Mobile Money (Cameroon) adapter.
 *
 * Not yet provisioned: real credentials are required before live charging.
 * Until then this gateway refuses to process payments rather than faking
 * success in production.
 */
class MtnMobileMoneyGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'mtn_momo';
    }

    public function charge(array $payload): array
    {
        throw new RuntimeException('MTN Mobile Money is not configured. Add provider credentials to enable live charging.');
    }
}
