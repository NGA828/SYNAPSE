<?php

namespace App\Services\Payments;

interface PaymentGateway
{
    /**
     * Human-readable provider name.
     */
    public function name(): string;

    /**
     * Attempt a charge.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: string, reference: ?string, method: ?string}
     */
    public function charge(array $payload): array;
}
