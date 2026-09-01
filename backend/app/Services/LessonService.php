<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Attachment;
use App\Models\Lesson;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\LessonPublishedNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Course materials written by teachers and read by their classes.
 *
 * Same two invariants as homework: a teacher may only publish inside a
 * class/subject they hold a TeachingAssignment for, and a student only sees
 * published lessons for the class they are enrolled in.
 */
class LessonService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly AttachmentService $attachments,
    ) {}

    /**
     * Paginated lessons belonging to a teacher.
     *
     * @return LengthAwarePaginator<Lesson>
     */
    public function forTeacher(Teacher $teacher, int $perPage = 15): LengthAwarePaginator
    {
        return Lesson::query()
            ->with(['subject', 'schoolClass', 'semester', 'attachments'])
            ->where('teacher_id', $teacher->id)
            ->orderByDesc('is_published')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Published lessons for a student's current class, grouped by subject and
     * then by topic so the page reads like a syllabus.
     *
     * @return Collection<string, Collection<string, Collection<int, Lesson>>>
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

        return Lesson::query()
            ->with(['subject', 'schoolClass', 'attachments'])
            ->where('class_id', $class->id)
            ->where('academic_year_id', $year->id)
            ->published()
            ->orderBy('sequence')
            ->latest('published_at')
            ->get()
            ->groupBy(fn (Lesson $lesson) => $lesson->subject?->name ?? 'Other')
            ->map(
                fn (Collection $lessons) => $lessons->groupBy(
                    fn (Lesson $lesson) => $lesson->topic ?: 'General',
                ),
            );
    }

    /**
     * Flat published list for a student, used by the dashboard counters.
     *
     * @return Collection<int, Lesson>
     */
    public function studentLessons(Student $student, ?AcademicYear $year = null): Collection
    {
        return $this->forStudent($student, $year)->flatten();
    }

    public function create(Teacher $teacher, array $data): Lesson
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

        $lesson = Lesson::create([
            'school_id' => $teacher->school_id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'academic_year_id' => $year->id,
            'semester_id' => $semester?->id,
            'title' => $data['title'],
            'topic' => $data['topic'] ?? null,
            'summary' => $data['summary'] ?? null,
            'body' => $data['body'] ?? null,
            'minutes' => $data['minutes'] ?? null,
            'sequence' => (int) ($data['sequence'] ?? 0),
            'is_published' => false,
        ]);

        $this->attachments->storeMany(
            $lesson,
            $data['attachments'] ?? [],
            $data['actor'],
            User::ROLE_TEACHER,
            Attachment::VISIBILITY_CLASS,
        );

        return $lesson->load(['subject', 'schoolClass', 'semester', 'attachments']);
    }

    /**
     * Edit a lesson. Target class/subject/year are frozen once created, so a
     * published lesson cannot be quietly moved to a different group.
     */
    public function update(Teacher $teacher, Lesson $lesson, array $data): Lesson
    {
        $this->assertOwns($teacher, $lesson);

        $lesson->update(array_filter([
            'title' => $data['title'] ?? null,
            'topic' => $data['topic'] ?? null,
            'summary' => $data['summary'] ?? null,
            'body' => $data['body'] ?? null,
            'minutes' => isset($data['minutes']) ? (int) $data['minutes'] : null,
            'sequence' => isset($data['sequence']) ? (int) $data['sequence'] : null,
        ], fn ($value) => $value !== null));

        if (! empty($data['attachments'])) {
            $this->attachments->storeMany(
                $lesson,
                $data['attachments'],
                $data['actor'],
                User::ROLE_TEACHER,
                Attachment::VISIBILITY_CLASS,
            );
        }

        return $lesson->fresh(['subject', 'schoolClass', 'semester', 'attachments']);
    }

    public function delete(Teacher $teacher, Lesson $lesson): void
    {
        $this->assertOwns($teacher, $lesson);

        // Remove the stored files too, not just the rows.
        foreach ($lesson->attachments()->get() as $attachment) {
            $this->attachments->delete($attachment);
        }

        $lesson->delete();
    }

    /**
     * Publish to the class and notify every enrolled student.
     */
    public function publish(Teacher $teacher, Lesson $lesson): Lesson
    {
        $this->assertOwns($teacher, $lesson);

        if ($lesson->is_published) {
            return $lesson;
        }

        $lesson->update(['is_published' => true, 'published_at' => now()]);

        $recipients = $this->enrolledUsers($lesson);

        if ($recipients->isNotEmpty()) {
            $this->notifications->notifyMany($recipients, new LessonPublishedNotification($lesson));
        }

        return $lesson->fresh(['subject', 'schoolClass']);
    }

    public function unpublish(Teacher $teacher, Lesson $lesson): Lesson
    {
        $this->assertOwns($teacher, $lesson);

        $lesson->update(['is_published' => false]);

        return $lesson->fresh(['subject', 'schoolClass']);
    }

    /**
     * Counters for the teacher dashboard.
     *
     * @return array{total: int, published: int, drafts: int}
     */
    public function teacherSummary(Teacher $teacher): array
    {
        $all = Lesson::query()->where('teacher_id', $teacher->id);

        return [
            'total' => (clone $all)->count(),
            'published' => (clone $all)->published()->count(),
            'drafts' => (clone $all)->where('is_published', false)->count(),
        ];
    }

    /**
     * Counters for the student dashboard.
     *
     * @return array{lessons: int, subjects: int, files: int}
     */
    public function studentSummary(Student $student): array
    {
        $lessons = $this->studentLessons($student);

        return [
            'lessons' => $lessons->count(),
            'subjects' => $lessons->pluck('subject_id')->unique()->count(),
            'files' => $lessons->sum(fn (Lesson $lesson) => $lesson->attachments->count()),
        ];
    }

    /**
     * @return Collection<int, User>
     */
    private function enrolledUsers(Lesson $lesson): Collection
    {
        return $lesson->schoolClass
            ->students()
            ->with('user')
            ->wherePivot('academic_year_id', $lesson->academic_year_id)
            ->get()
            ->map(fn (Student $student) => $student->user)
            ->filter()
            ->values();
    }

    /**
     * Identical guard to GradeService::assertAssigned() and
     * HomeworkService::assertAssigned() — one rule for who teaches what.
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

    private function assertOwns(Teacher $teacher, Lesson $lesson): void
    {
        abort_unless($lesson->teacher_id === $teacher->id, 403, 'This lesson belongs to another teacher.');
    }

    private function resolveYear(?int $yearId): AcademicYear
    {
        $year = $yearId ? AcademicYear::query()->find($yearId) : AcademicYear::current();

        abort_unless($year, 409, 'No active academic year is configured.');

        return $year;
    }
}
