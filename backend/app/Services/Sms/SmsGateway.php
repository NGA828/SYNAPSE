<?php

namespace App\Services\Sms;

interface SmsGateway
{
    /**
     * Deliver a text message. Implementations must throw on hard failures so
     * the queue can retry.
     *
     * @return array{id: ?string, status: string, provider: string}
     */
    public function send(string $to, string $message): array;

    public function name(): string;
}
