<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Default driver: writes the message to the application log. Safe for local
 * development and for schools that have not bought SMS credit yet.
 */
class LogSmsGateway implements SmsGateway
{
    public function name(): string
    {
        return 'log';
    }

    public function send(string $to, string $message): array
    {
        Log::channel(config('logging.default'))->info('[SMS] to '.$to.': '.$message);

        return ['id' => null, 'status' => 'logged', 'provider' => $this->name()];
    }
}
