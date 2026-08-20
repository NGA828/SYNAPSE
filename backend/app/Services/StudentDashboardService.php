<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\DocumentRequest;
use App\Models\Student;

class StudentDashboardService
{
    public function __construct(
        private readonly GradeService $gradeService,
        private readonly TimetableService $timetableService,
        private readonly AnnouncementService $announcementService,
    ) {}

    /**
     * Compose the student dashboard: profile, current class, grades summary,
     * the week's timetable and recent announcements.
     *
     * @return array<string, mixed>
     */
    public function dashboard(Student $student): array
    {
        $year = AcademicYear::current();

        $academic = $this->gradeService->studentGrades($student, $year);
        $class = $academic['class'];

        $timetable = $class
            ? $this->timetableService->entriesFor($class, $year)
            : collect();

        $announcements = $student->user
            ? $this->announcementService->forUser($student->user)->take(3)
            : collect();

        $pendingRequests = DocumentRequest::query()
            ->where('student_id', $student->id)
            ->whereIn('status', DocumentRequest::OPEN_STATUSES)
            ->count();

        return [
            'student' => $student,
            'class' => $class,
            'academic_year' => $year,
            'summary' => [
                'average' => $academic['average'],
                'subjects' => $academic['grades']->count(),
                'pending_requests' => $pendingRequests,
                'announcements' => $announcements->count(),
            ],
            'grades' => $academic['grades'],
            'timetable' => $timetable,
            'announcements' => $announcements,
        ];
    }
}
