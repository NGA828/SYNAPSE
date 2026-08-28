<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Resolves the configured SMS driver and centralises delivery + logging.
 */
class SmsManager
{
    public function driver(?string $name = null): SmsGateway
    {
        return match ($name ?? config('synapse.sms.driver', 'log')) {
            'twilio' => new TwilioSmsGateway(),
            'http' => new HttpSmsGateway(),
            default => new LogSmsGateway(),
        };
    }

    /**
     * Send a message, normalising the destination number first.
     *
     * @return array{id: ?string, status: string, provider: string}
     */
    public function send(string $to, string $message): array
    {
        $number = $this->normalise($to);

        if ($number === null) {
            Log::warning('[SMS] skipped: unusable phone number', ['raw' => $to]);

            return ['id' => null, 'status' => 'skipped', 'provider' => 'none'];
        }

        try {
            return $this->driver()->send($number, $message);
        } catch (Throwable $e) {
            Log::error('[SMS] delivery failed', ['to' => $number, 'error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Accepts 6XXXXXXXX / 2376XXXXXXXX / +2376XXXXXXXX and returns E.164.
     */
    public function normalise(?string $number, ?string $countryCode = null): ?string
    {
        if (! $number) {
            return null;
        }

        $digits = preg_replace('/[^0-9+]/', '', $number) ?? '';
        $code = ltrim($countryCode ?? (string) config('synapse.sms.country_code', '237'), '+');

        if (str_starts_with($digits, '+')) {
            return strlen($digits) >= 9 ? $digits : null;
        }

        $digits = ltrim($digits, '0');

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, $code)) {
            return '+'.$digits;
        }

        return '+'.$code.$digits;
    }
}
