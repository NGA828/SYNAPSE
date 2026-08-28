<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionReminderNotification;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Nightly billing sweep:
 *
 *  1. marks subscriptions whose end date has passed as expired (and locks the
 *     school out of academic features through EnforceSubscription);
 *  2. warns admins whose trial or paid period ends soon;
 *  3. sends a single "expired" dunning notice on the day it lapses.
 */
class SweepSubscriptions extends Command
{
    protected $signature = 'synapse:sweep-subscriptions
                            {--dry-run : Report what would change without writing}';

    protected $description = 'Expire lapsed subscriptions and send trial/renewal reminders';

    public function handle(NotificationService $notifications): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $today = Carbon::today();
        $expired = 0;
        $reminded = 0;

        Subscription::query()
            ->with('school')
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIAL])
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', $today)
            ->chunkById(100, function ($lapsed) use (&$expired, $dryRun, $notifications) {
                foreach ($lapsed as $subscription) {
                    $school = $subscription->school;

                    if (! $school) {
                        continue;
                    }

                    $this->line("  · expiring {$school->name} (subscription #{$subscription->id})");

                    if ($dryRun) {
                        $expired++;

                        continue;
                    }

                    $subscription->forceFill(['status' => Subscription::STATUS_EXPIRED])->save();
                    $school->forceFill(['subscription_status' => 'expired'])->save();

                    $notifications->notifyRole(
                        $school,
                        User::ROLE_ADMIN,
                        new SubscriptionReminderNotification($school, SubscriptionReminderNotification::STAGE_EXPIRED),
                    );

                    $expired++;
                }
            });

        foreach ((array) config('synapse.renewal_reminder_days', [7, 3, 1]) as $days) {
            $target = $today->copy()->addDays((int) $days);

            Subscription::query()
                ->with('school')
                ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIAL])
                ->whereDate('end_date', $target)
                ->chunkById(100, function ($ending) use (&$reminded, $days, $dryRun, $notifications) {
                    foreach ($ending as $subscription) {
                        $school = $subscription->school;

                        if (! $school) {
                            continue;
                        }

                        $stage = $subscription->status === Subscription::STATUS_TRIAL
                            ? SubscriptionReminderNotification::STAGE_TRIAL_ENDING
                            : SubscriptionReminderNotification::STAGE_EXPIRING;

                        $this->line("  · reminding {$school->name} — {$days} day(s) left");

                        if (! $dryRun) {
                            $notifications->notifyRole(
                                $school,
                                User::ROLE_ADMIN,
                                new SubscriptionReminderNotification($school, $stage, (int) $days),
                            );
                        }

                        $reminded++;
                    }
                });
        }

        $this->info(($dryRun ? '[dry run] ' : '')."Expired: {$expired} · Reminders sent: {$reminded}");

        return self::SUCCESS;
    }
}
