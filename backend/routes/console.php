<?php

use App\Console\Commands\PruneNotifications;
use App\Console\Commands\SweepSubscriptions;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
|
| Run `php artisan schedule:work` (dev) or add the one-liner cron entry
| `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1` in
| production. Everything below is idempotent and safe to retry.
|
*/

// Billing: expire lapsed subscriptions, warn schools before they lapse.
Schedule::command(SweepSubscriptions::class)
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer();

// Housekeeping.
Schedule::command(PruneNotifications::class, ['--days=90'])
    ->weeklyOn(7, '03:00')
    ->withoutOverlapping();

Schedule::command('auth:clear-resets')->daily();

Schedule::command('queue:prune-batches --hours=48')->daily();
