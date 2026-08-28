<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * Keeps the notifications table small: read items older than the retention
 * window are deleted. Unread notifications are never touched.
 */
class PruneNotifications extends Command
{
    protected $signature = 'synapse:prune-notifications {--days=90 : Retention window in days}';

    protected $description = 'Delete read notifications older than the retention window';

    public function handle(NotificationService $notifications): int
    {
        $days = max(1, (int) $this->option('days'));

        $deleted = $notifications->prune($days);

        $this->info("Pruned {$deleted} read notification(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
