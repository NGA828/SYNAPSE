<?php

namespace App\Jobs;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\User;
use App\Notifications\ReportCardReadyNotification;
use App\Services\DocumentService;
use App\Services\NotificationService;
use App\Services\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bulk report-card generation for a whole class.
 *
 * Rendering 60 PDFs inside an HTTP request would time out, so the admin
 * endpoint dispatches this job and the students are notified as each card
 * becomes available.
 */
class GenerateClassReportCardsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 2;

    public function __construct(
        public readonly int $schoolId,
        public readonly int $classId,
        public readonly ?int $semesterId = null,
        public readonly ?int $actorId = null,
        public readonly bool $notifyStudents = true,
    ) {
        $this->onQueue('documents');
    }

    public function handle(
        DocumentService $documents,
        NotificationService $notifications,
        TenantContext $tenant,
    ): void {
        $school = School::find($this->schoolId);

        if (! $school) {
            return;
        }

        // A queued job has no HTTP request, so the tenant must be set by hand
        // before any tenant-scoped query runs.
        $tenant->set($school);

        $class = SchoolClass::query()->forSchool($school)->find($this->classId);
        $semester = $this->semesterId ? Semester::find($this->semesterId) : null;
        $actor = $this->actorId ? User::find($this->actorId) : null;

        if (! $class) {
            return;
        }

        $yearId = $semester?->academic_year_id ?? \App\Models\AcademicYear::current()?->id;

        $class->students()
            ->when($yearId, fn ($query) => $query->wherePivot('academic_year_id', $yearId))
            ->with('user')
            ->chunkById(25, function ($students) use ($documents, $notifications, $semester, $actor) {
                foreach ($students as $student) {
                    try {
                        $document = $documents->generateReportCard($student, $semester, $actor);

                        if ($this->notifyStudents) {
                            $notifications->notify(
                                $student->user,
                                new ReportCardReadyNotification($document, $semester?->name ?? 'the full year'),
                            );
                        }
                    } catch (Throwable $e) {
                        Log::error('Report card generation failed', [
                            'student_id' => $student->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }, 'students.id', 'id');
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['report-cards', 'school:'.$this->schoolId, 'class:'.$this->classId];
    }
}
