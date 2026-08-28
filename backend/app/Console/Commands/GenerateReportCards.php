<?php

namespace App\Console\Commands;

use App\Jobs\GenerateClassReportCardsJob;
use App\Models\SchoolClass;
use Illuminate\Console\Command;

/**
 * Operator-friendly entry point for bulk report cards, e.g.
 *   php artisan synapse:report-cards 12 --semester=3
 */
class GenerateReportCards extends Command
{
    protected $signature = 'synapse:report-cards
                            {class : The class id}
                            {--semester= : Restrict to a semester id}
                            {--no-notify : Generate silently, without notifying students}';

    protected $description = 'Queue report-card generation for every student in a class';

    public function handle(): int
    {
        $class = SchoolClass::withoutTenant()->find((int) $this->argument('class'));

        if (! $class) {
            $this->error('Class not found.');

            return self::FAILURE;
        }

        GenerateClassReportCardsJob::dispatch(
            schoolId: $class->school_id,
            classId: $class->id,
            semesterId: $this->option('semester') ? (int) $this->option('semester') : null,
            actorId: null,
            notifyStudents: ! $this->option('no-notify'),
        );

        $this->info("Queued report cards for {$class->name}.");

        return self::SUCCESS;
    }
}
