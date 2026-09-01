<?php

namespace App\Services;

use App\Http\Resources\AttachmentResource;
use App\Models\AcademicYear;
use App\Models\Attachment;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkSubmission;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\HomeworkPublishedNotification;
use App\Notifications\HomeworkReturnedNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Homework set by teachers, submitted by students, graded by teachers.
 *
 * Two rules hold this together:
 *
 *  1. A teacher may only manage homework inside a class/subject they actually
 *     hold a `TeachingAssignment` for — the same guard the gradebook uses, so
 *     the two can never disagree about who teaches what.
 *  2. A student only ever sees homework for the class they are enrolled in,
 *     and only once the teacher has published it.
 */
class HomeworkService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly AttachmentService $attachments,
    ) {}

    /**
     * Paginated homework belonging to a teacher, newest first.
     *
     * @return LengthAwarePaginator<HomeworkAssignment>
     */
    public function forTeacher(Teacher $teacher, int $perPage = 15): LengthAwarePaginator
    {
        return HomeworkAssignment::query()
            ->with(['subject', 'schoolClass', 'semester'])
            ->withCount(['submissions', 'submissions as graded_count' => fn ($q) => $q->whereNotNull('score')])
            ->where('teacher_id', $teacher->id)
            ->latest('due_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Homework visible to a student: their current class, published only.
     *
     * Each row carries the student's own submission so the UI can render one
     * list without a second round trip.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function forStudent(Student $student, ?AcademicYear $year = null): Collection
    {
        $year ??= AcademicYear::current();

        if (! $year) {
            return collect();
        }

        $class = $student->enrollments()
            ->where('academic_year_id', $year->id)
            ->first()?->schoolClass;

        if (! $class) {
            return collect();
        }

        $homework = HomeworkAssignment::query()
            ->with(['subject', 'schoolClass', 'semester', 'attachments'])
            ->where('class_id', $class->id)
            ->where('academic_year_id', $year->id)
            ->published()
            ->latest('due_at')
            ->get();

        $submissions = HomeworkSubmission::query()
            ->with('attachments')
            ->where('student_id', $student->id)
            ->whereIn('homework_assignment_id', $homework->pluck('id'))
            ->get()
            ->keyBy('homework_assignment_id');

        return $homework->map(fn (HomeworkAssignment $item) => [
            'assignment' => $item,
            'submission' => $submissions->get($item->id),
        ]);
    }

    /**
     * Create homework. The teacher must hold the teaching assignment for the
     * class + subject + year they are targeting.
     */
    public function create(Teacher $teacher, array $data): HomeworkAssignment
    {
        $class = SchoolClass::query()->findOrFail($data['class_id']);
        $subject = Subject::query()->findOrFail($data['subject_id']);

        abort_unless(
            $class->school_id === $teacher->school_id && $subject->school_id === $teacher->school_id,
            422,
            'The selected class or subject belongs to another school.',
        );

        $year = $this->resolveYear($data['academic_year_id'] ?? null);

        $this->assertAssigned($teacher, $class, $subject, $year);

        $semester = isset($data['semester_id']) && $data['semester_id']
            ? Semester::query()->where('academic_year_id', $year->id)->findOrFail($data['semester_id'])
            : null;

        $homework = HomeworkAssignment::create([
            'school_id' => $teacher->school_id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester?->id,
            'title' => $data['title'],
            'instructions' => $data['instructions'] ?? null,
            'max_score' => (int) ($data['max_score'] ?? 20),
            'due_at' => $data['due_at'],
            'is_published' => false,
        ]);

        // Optional brief document(s) the class can download.
        $this->attachments->storeMany(
            $homework,
            $data['attachments'] ?? [],
            $data['actor'],
            User::ROLE_TEACHER,
            Attachment::VISIBILITY_CLASS,
        );

        return $homework->load(['subject', 'schoolClass', 'semester', 'attachments']);
    }

    /**
     * Edit homework. Title, instructions, max score and the deadline stay
     * editable after publishing so a teacher can extend a deadline — but the
     * target class/subject/year are frozen once students can see it.
     */
    public function update(Teacher $teacher, HomeworkAssignment $homework, array $data): HomeworkAssignment
    {
        $this->assertOwns($teacher, $homework);

        $homework->update(array_filter([
            'title' => $data['title'] ?? null,
            'instructions' => $data['instructions'] ?? null,
            'max_score' => isset($data['max_score']) ? (int) $data['max_score'] : null,
            'due_at' => $data['due_at'] ?? null,
        ], fn ($value) => $value !== null));

        // Editing may add brief documents too (the form allows it).
        if (! empty($data['attachments'])) {
            $this->attachments->storeMany(
                $homework,
                $data['attachments'],
                $data['actor'],
                User::ROLE_TEACHER,
                Attachment::VISIBILITY_CLASS,
            );
        }

        return $homework->fresh(['subject', 'schoolClass', 'semester', 'attachments']);
    }

    public function delete(Teacher $teacher, HomeworkAssignment $homework): void
    {
        $this->assertOwns($teacher, $homework);

        $homework->delete();
    }

    /**
     * Publish to the class and notify every enrolled student.
     */
    public function publish(Teacher $teacher, HomeworkAssignment $homework): HomeworkAssignment
    {
        $this->assertOwns($teacher, $homework);

        if ($homework->is_published) {
            return $homework;
        }

        $homework->update(['is_published' => true, 'published_at' => now()]);

        $recipients = $this->enrolledUsers($homework);

        if ($recipients->isNotEmpty()) {
            $this->notifications->notifyMany($recipients, new HomeworkPublishedNotification($homework));
        }

        return $homework->fresh(['subject', 'schoolClass']);
    }

    /**
     * Withdraw from the class without deleting the submissions already made.
     */
    public function unpublish(Teacher $teacher, HomeworkAssignment $homework): HomeworkAssignment
    {
        $this->assertOwns($teacher, $homework);

        $homework->update(['is_published' => false]);

        return $homework->fresh(['subject', 'schoolClass']);
    }

    /**
     * The class roster for one piece of homework, each row carrying that
     * student's submission (or null when they have not submitted).
     *
     * @return array{assignment: HomeworkAssignment, students: Collection<int, array<string, mixed>>, stats: array<string, int>}
     */
    public function submissionsFor(Teacher $teacher, HomeworkAssignment $homework): array
    {
        $this->assertOwns($teacher, $homework);

        $submissions = HomeworkSubmission::query()
            ->with(['student.user', 'attachments'])
            ->where('homework_assignment_id', $homework->id)
            ->get()
            ->keyBy('student_id');

        $students = $this->enrolledStudents($homework)
            ->map(function (Student $student) use ($submissions, $homework) {
                $submission = $submissions->get($student->id);

                return [
                    'student_id' => $student->id,
                    'name' => $student->user?->name,
                    'matricule' => $student->matricule,
                    'submission' => $submission ? [
                        'id' => $submission->id,
                        'content' => $submission->content,
                        'attempts' => $submission->attempts,
                        'submitted_at' => $submission->submitted_at?->toIso8601String(),
                        'is_late' => $submission->is_late,
                        'score' => $submission->score,
                        'feedback' => $submission->feedback,
                        'status' => $submission->status(),
                        'attachments' => AttachmentResource::collection($submission->attachments)->resolve(),
                    ] : null,
                    'status' => $submission?->status() ?? HomeworkSubmission::STATUS_NOT_SUBMITTED,
                    'score' => $submission?->score,
                    'max_score' => $homework->max_score,
                ];
            })
            ->sortBy('name')
            ->values();

        return [
            'assignment' => $homework->load(['subject', 'schoolClass']),
            'students' => $students,
            'stats' => [
                'total' => $students->count(),
                'submitted' => $students->filter(fn ($row) => $row['submission'] !== null)->count(),
                'graded' => $students->filter(fn ($row) => $row['submission']?->isGraded())->count(),
                'late' => $students->filter(fn ($row) => (bool) $row['submission']?->is_late)->count(),
            ],
        ];
    }

    /**
     * Submit — or replace — a student's work.
     *
     * Rejected outright when the homework is unpublished or the deadline has
     * passed; a late flag is never set here because late work is refused.
     */
    public function submit(
        Student $student,
        HomeworkAssignment $homework,
        ?string $content,
        array $files = [],
        ?User $actor = null,
    ): HomeworkSubmission {
        abort_unless($homework->is_published, 403, 'This homework is not available yet.');
        abort_unless(! $homework->isPastDue(), 422, 'The deadline for this homework has passed.');

        // An answer is either written or attached — a blank submission is not
        // a submission. The FormRequest enforces this too.
        abort_if(
            blank($content) && $files === [],
            422,
            'Write your answer or attach a file before submitting.',
        );

        $this->assertEnrolled($student, $homework);

        $submission = DB::transaction(function () use ($student, $homework, $content) {
            $existing = HomeworkSubmission::query()
                ->where('homework_assignment_id', $homework->id)
                ->where('student_id', $student->id)
                ->first();

            // Graded work is final — the student cannot overwrite a mark.
            abort_if($existing?->isGraded(), 422, 'This submission has already been graded and can no longer be changed.');

            if ($existing) {
                $existing->update([
                    'content' => $content,
                    'attempts' => $existing->attempts + 1,
                    'submitted_at' => now(),
                    'is_late' => false,
                ]);

                return $existing->fresh();
            }

            return HomeworkSubmission::create([
                'school_id' => $homework->school_id,
                'homework_assignment_id' => $homework->id,
                'student_id' => $student->id,
                'content' => $content,
                'attempts' => 1,
                'submitted_at' => now(),
                'is_late' => false,
            ]);
        });

        // Files are attached outside the transaction: a storage failure should
        // not roll back a submission row the student has already been told
        // about, and vice versa they are reported by the same request.
        $this->attachments->storeMany(
            $submission,
            $files,
            $actor ?? $student->user,
            User::ROLE_STUDENT,
            Attachment::VISIBILITY_PRIVATE,
        );

        return $submission->fresh('attachments');
    }

    /**
     * Grade a submission and return it to the student.
     */
    public function grade(Teacher $teacher, HomeworkSubmission $submission, float $score, ?string $feedback): HomeworkSubmission
    {
        $homework = $submission->homework;

        abort_unless($homework, 404, 'This submission no longer belongs to any homework.');

        $this->assertOwns($teacher, $homework);

        abort_if($score < 0, 422, 'The score cannot be negative.');
        abort_if(
            $score > (float) $homework->max_score,
            422,
            "The score cannot exceed the maximum of {$homework->max_score}.",
        );

        $submission->update([
            'score' => $score,
            'feedback' => $feedback,
            'graded_by' => $teacher->id,
            'graded_at' => now(),
            'returned_at' => now(),
        ]);

        if ($submission->student?->user) {
            $this->notifications->notify(
                $submission->student->user,
                new HomeworkReturnedNotification($submission->fresh('homework')),
            );
        }

        return $submission->fresh(['homework.subject', 'student.user']);
    }

    /**
     * Counts for the teacher dashboard: what is waiting to be marked, and what
     * is due soon.
     *
     * @return array{awaiting_grading: int, open: int, due_soon: int}
     */
    public function teacherSummary(Teacher $teacher): array
    {
        $published = HomeworkAssignment::query()
            ->where('teacher_id', $teacher->id)
            ->published();

        $ids = (clone $published)->pluck('id');

        $awaiting = HomeworkSubmission::query()
            ->whereIn('homework_assignment_id', $ids)
            ->whereNull('score')
            ->count();

        $open = (clone $published)->where('due_at', '>=', now())->count();

        $dueSoon = (clone $published)
            ->whereBetween('due_at', [now(), now()->addDays(3)])
            ->count();

        return [
            'awaiting_grading' => $awaiting,
            'open' => $open,
            'due_soon' => $dueSoon,
        ];
    }

    /**
     * Counts for the student dashboard.
     *
     * @return array{pending: int, awaiting_grade: int, graded: int}
     */
    public function studentSummary(Student $student): array
    {
        $rows = $this->forStudent($student);

        $pending = 0;
        $awaiting = 0;
        $graded = 0;

        foreach ($rows as $row) {
            /** @var HomeworkAssignment $item */
            $item = $row['assignment'];
            /** @var HomeworkSubmission|null $submission */
            $submission = $row['submission'];

            if ($submission?->isGraded()) {
                $graded++;
            } elseif ($submission) {
                $awaiting++;
            } elseif ($item->isOpenForSubmission()) {
                $pending++;
            }
        }

        return [
            'pending' => $pending,
            'awaiting_grade' => $awaiting,
            'graded' => $graded,
        ];
    }

    /**
     * Students enrolled in the homework's class for its academic year.
     *
     * @return Collection<int, Student>
     */
    private function enrolledStudents(HomeworkAssignment $homework): Collection
    {
        return $homework->schoolClass
            ->students()
            ->with('user')
            ->wherePivot('academic_year_id', $homework->academic_year_id)
            ->get();
    }

    /**
     * The user accounts behind those students, for notification fan-out.
     */
    private function enrolledUsers(HomeworkAssignment $homework): Collection
    {
        return $this->enrolledStudents($homework)
            ->map(fn (Student $student) => $student->user)
            ->filter()
            ->values();
    }

    private function assertEnrolled(Student $student, HomeworkAssignment $homework): void
    {
        $enrolled = $student->enrollments()
            ->where('class_id', $homework->class_id)
            ->where('academic_year_id', $homework->academic_year_id)
            ->exists();

        abort_unless($enrolled, 403, 'You are not enrolled in the class this homework was set for.');
    }

    /**
     * The teacher must hold a TeachingAssignment for this class + subject +
     * year — identical to GradeService::assertAssigned().
     */
    private function assertAssigned(Teacher $teacher, SchoolClass $class, Subject $subject, AcademicYear $year): void
    {
        $assigned = $teacher->teachingAssignments()
            ->where('class_id', $class->id)
            ->where('subject_id', $subject->id)
            ->where('academic_year_id', $year->id)
            ->exists();

        abort_unless($assigned, 403, 'You are not assigned to teach this subject in this class.');
    }

    private function assertOwns(Teacher $teacher, HomeworkAssignment $homework): void
    {
        abort_unless(
            $homework->teacher_id === $teacher->id,
            403,
            'This homework belongs to another teacher.',
        );
    }

    private function resolveYear(?int $yearId): AcademicYear
    {
        $year = $yearId
            ? AcademicYear::query()->find($yearId)
            : AcademicYear::current();

        abort_unless($year, 409, 'No active academic year is configured.');

        return $year;
    }
}
