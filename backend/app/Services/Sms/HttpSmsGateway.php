<?php

namespace App\Services\Sms;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Generic HTTP adapter for the local Cameroonian aggregators (Nexah, SMSVas,
 * Orange SMS API…), which all expose a simple "POST these fields" endpoint.
 *
 * Configure the endpoint and field names in config/services.php so no code
 * change is needed to switch provider.
 */
class HttpSmsGateway implements SmsGateway
{
    public function name(): string
    {
        return (string) config('services.sms_http.name', 'http');
    }

    public function send(string $to, string $message): array
    {
        $config = config('services.sms_http');
        $endpoint = Arr::get($config, 'endpoint');

        if (! $endpoint) {
            throw new RuntimeException('No SMS endpoint configured: set SMS_HTTP_ENDPOINT.');
        }

        $payload = array_merge(Arr::get($config, 'params', []), [
            Arr::get($config, 'to_field', 'to') => $to,
            Arr::get($config, 'message_field', 'message') => $message,
            Arr::get($config, 'from_field', 'sender') => config('synapse.sms.from'),
        ]);

        $request = Http::timeout(15)->retry(2, 200);

        if ($token = Arr::get($config, 'token')) {
            $request = $request->withToken($token);
        }

        $response = Arr::get($config, 'method', 'post') === 'get'
            ? $request->get($endpoint, $payload)
            : $request->asForm()->post($endpoint, $payload);

        if ($response->failed()) {
            throw new RuntimeException('SMS provider rejected the message: '.$response->body());
        }

        return [
            'id' => $response->json(Arr::get($config, 'id_field', 'id')),
            'status' => 'sent',
            'provider' => $this->name(),
        ];
    }
}
