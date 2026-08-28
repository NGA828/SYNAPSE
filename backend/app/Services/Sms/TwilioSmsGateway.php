<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Twilio REST adapter (works worldwide, including Cameroon MSISDNs).
 *
 * Uses the HTTP client directly so the SDK is not a hard dependency.
 */
class TwilioSmsGateway implements SmsGateway
{
    public function __construct(
        private readonly ?string $sid = null,
        private readonly ?string $token = null,
        private readonly ?string $from = null,
    ) {}

    public function name(): string
    {
        return 'twilio';
    }

    public function send(string $to, string $message): array
    {
        $sid = $this->sid ?: config('services.twilio.sid');
        $token = $this->token ?: config('services.twilio.token');
        $from = $this->from ?: config('services.twilio.from', config('synapse.sms.from'));

        if (! $sid || ! $token || ! $from) {
            throw new RuntimeException('Twilio is not configured: set TWILIO_SID, TWILIO_TOKEN and TWILIO_FROM.');
        }

        $response = Http::asForm()
            ->withBasicAuth($sid, $token)
            ->timeout(15)
            ->retry(2, 200)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'To' => $to,
                'From' => $from,
                'Body' => $message,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Twilio rejected the message: '.$response->body());
        }

        return [
            'id' => $response->json('sid'),
            'status' => $response->json('status', 'queued'),
            'provider' => $this->name(),
        ];
    }
}
