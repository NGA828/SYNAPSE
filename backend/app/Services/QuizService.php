<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Attachment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\QuizPublishedNotification;
use App\Notifications\QuizReviewedNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Auto-marked quizzes.
 *
 * The authorisation rules are the same two that hold homework together — a
 * teacher only writes for a class/subject they hold a `TeachingAssignment`
 * for, and a student only sees published work for their own class. On top of
 * that sit three rules specific to marking:
 *
 *  1. **A student never receives the answer key.** The paper endpoint omits
 *     `correct_option` entirely rather than relying on the client to hide it.
 *  2. **A closed quiz cannot be sat.** Enforced on submission, so an idling
 *     browser cannot beat the deadline.
 *  3. **Once anyone has sat the paper the questions freeze.** Editing a
 *     question afterwards would make earlier marks incomparable, so `publish`
 *     locks the paper as soon as an attempt exists.
 */
class QuizService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly AttachmentService $attachments,
    ) {}

    /**
     * Paginated quizzes belonging to a teacher.
     *
     * @return LengthAwarePaginator<Quiz>
     */
    public function forTeacher(Teacher $teacher, int $perPage = 15): LengthAwarePaginator
    {
        return Quiz::query()
            ->with(['subject', 'schoolClass', 'semester'])
            ->withCount(['questions', 'attempts as attempts_count' => fn ($q) => $q->whereNotNull('submitted_at')])
            ->where('teacher_id', $teacher->id)
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Quizzes visible to a student, each paired with their latest attempt so
     * the list needs no second round trip.
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

        $quizzes = Quiz::query()
            ->with(['subject', 'schoolClass', 'attachments'])
            ->withCount('questions')
            ->where('class_id', $class->id)
            ->where('academic_year_id', $year->id)
            ->published()
            ->latest('id')
            ->get();

        $own = QuizAttempt::query()
            ->where('student_id', $student->id)
            ->whereIn('quiz_id', $quizzes->pluck('id'))
            ->whereNotNull('submitted_at')
            ->get()
            ->groupBy('quiz_id');

        // Best attempt per quiz is what the list shows; the count of rows is
        // how many of the student's attempts are spent.
        $best = $own->map(fn (Collection $rows) => $rows->sortByDesc('score')->first());
        $used = $own->map(fn (Collection $rows) => $rows->count());

        return $quizzes->map(fn (Quiz $quiz) => [
            'quiz' => $quiz,
            'attempt' => $best->get($quiz->id),
            'attempts_used' => $used->get($quiz->id, 0),
        ]);
    }

    // ------------------------------------------------------------- authoring

    public function create(Teacher $teacher, array $data): Quiz
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

        $this->assertNoDuplicateTitle($class->id, $subject->id, $year->id, $data['title']);

        $semester = isset($data['semester_id']) && $data['semester_id']
            ? Semester::query()->where('academic_year_id', $year->id)->findOrFail($data['semester_id'])
            : null;

        $quiz = DB::transaction(function () use ($teacher, $class, $subject, $year, $semester, $data) {
            $quiz = Quiz::create([
                'school_id' => $teacher->school_id,
                'teacher_id' => $teacher->id,
                'subject_id' => $subject->id,
                'class_id' => $class->id,
                'academic_year_id' => $year->id,
                'semester_id' => $semester?->id,
                'title' => $data['title'],
                'instructions' => $data['instructions'] ?? null,
                'max_score' => (int) ($data['max_score'] ?? config('synapse.grading.scale')),
                'closes_at' => $data['closes_at'] ?? null,
                'time_limit_minutes' => $data['time_limit_minutes'] ?? null,
                'attempts_allowed' => (int) ($data['attempts_allowed'] ?? config('synapse.quizzes.attempts')),
                'is_published' => false,
            ]);

            $this->replaceQuestions($quiz, $data['questions'] ?? []);

            return $quiz;
        });

        $this->attachments->storeMany(
            $quiz,
            $data['attachments'] ?? [],
            $data['actor'],
            User::ROLE_TEACHER,
            Attachment::VISIBILITY_CLASS,
        );

        return $quiz->load(['subject', 'schoolClass', 'semester', 'questions', 'attachments']);
    }

    public function update(Teacher $teacher, Quiz $quiz, array $data): Quiz
    {
        $this->assertOwns($teacher, $quiz);

        abort_if(
            $quiz->is_locked && array_key_exists('questions', $data) && $data['questions'] !== [],
            422,
            'Students have already sat this quiz, so its questions can no longer be changed.',
        );

        if (isset($data['title']) && $data['title'] !== $quiz->title) {
            $this->assertNoDuplicateTitle($quiz->class_id, $quiz->subject_id, $quiz->academic_year_id, $data['title'], $quiz->id);
        }

        DB::transaction(function () use ($quiz, $data) {
            $quiz->update(array_filter([
                'title' => $data['title'] ?? null,
                'instructions' => $data['instructions'] ?? null,
                'max_score' => isset($data['max_score']) ? (int) $data['max_score'] : null,
                'closes_at' => $data['closes_at'] ?? null,
                'time_limit_minutes' => isset($data['time_limit_minutes']) ? (int) $data['time_limit_minutes'] : null,
                'attempts_allowed' => isset($data['attempts_allowed']) ? (int) $data['attempts_allowed'] : null,
            ], fn ($value) => $value !== null));

            if (! $quiz->is_locked && array_key_exists('questions', $data)) {
                $this->replaceQuestions($quiz, $data['questions'] ?? []);
            }
        });

        if (! empty($data['attachments'])) {
            $this->attachments->storeMany(
                $quiz,
                $data['attachments'],
                $data['actor'],
                User::ROLE_TEACHER,
                Attachment::VISIBILITY_CLASS,
            );
        }

        return $quiz->fresh(['subject', 'schoolClass', 'semester', 'questions', 'attachments']);
    }

    public function delete(Teacher $teacher, Quiz $quiz): void
    {
        $this->assertOwns($teacher, $quiz);

        foreach ($quiz->attachments()->get() as $attachment) {
            $this->attachments->delete($attachment);
        }

        $quiz->delete();
    }

    /**
     * Publish to the class. A paper with no questions, or with a question whose
     * answer key points outside its own options, is refused — publishing those
     * would hand every student an unmarkable quiz.
     */
    public function publish(Teacher $teacher, Quiz $quiz): Quiz
    {
        $this->assertOwns($teacher, $quiz);

        if ($quiz->is_published) {
            return $quiz;
        }

        $questions = $quiz->questions()->get();

        abort_if(
            $questions->isEmpty(),
            422,
            'Add at least one question before publishing this quiz.',
        );

        abort_if(
            $questions->contains(fn (QuizQuestion $question) => ! isset($question->options[$question->correct_option])),
            422,
            'Every question needs a correct answer selected.',
        );

        abort_if($quiz->pointsAvailable() === 0, 422, 'The questions must carry at least one point in total.');

        $quiz->update(['is_published' => true, 'published_at' => now()]);

        $recipients = $this->enrolledUsers($quiz);

        if ($recipients->isNotEmpty()) {
            $this->notifications->notifyMany($recipients, new QuizPublishedNotification($quiz));
        }

        return $quiz->fresh(['subject', 'schoolClass', 'questions']);
    }

    /**
     * Withdraw the quiz. Attempts already made are kept.
     */
    public function unpublish(Teacher $teacher, Quiz $quiz): Quiz
    {
        $this->assertOwns($teacher, $quiz);

        $quiz->update(['is_published' => false]);

        return $quiz->fresh(['subject', 'schoolClass', 'questions']);
    }

    // -------------------------------------------------------------- sitting

    /**
     * The paper a student is about to sit.
     *
     * `correct_option` is never selected, so the answer key cannot reach the
     * browser however the client behaves.
     *
     * @return array<string, mixed>
     */
    public function paperFor(Student $student, Quiz $quiz): array
    {
        abort_unless($quiz->is_published, 403, 'This quiz is not available yet.');

        $this->assertEnrolled($student, $quiz);

        abort_if($quiz->isClosed(), 403, 'This quiz has closed.');

        $remaining = $quiz->attempts_allowed - $this->attemptsUsed($student, $quiz);

        abort_if($remaining <= 0, 422, 'You have already used every attempt for this quiz.');

        $questions = QuizQuestion::query()
            ->where('quiz_id', $quiz->id)
            ->orderBy('sequence')
            ->orderBy('id')
            ->get(['id', 'quiz_id', 'prompt', 'options', 'points', 'sequence']);

        return [
            'quiz' => $quiz->load(['subject', 'schoolClass', 'attachments']),
            'questions' => $questions,
            'attempts_remaining' => $remaining,
            'points_available' => $questions->sum('points'),
        ];
    }

    /**
     * Mark and store an attempt.
     *
     * Auto-marking is exact: an option index either equals the stored key or it
     * does not. The raw score is scaled onto the quiz's own `max_score` so the
     * mark sits on the same 0–20 scale as every other grade in the school.
     *
     * @param  array<int, int|null>  $answers  question id => chosen option index
     */
    public function submit(Student $student, Quiz $quiz, array $answers): QuizAttempt
    {
        abort_unless($quiz->is_published, 403, 'This quiz is not available yet.');
        abort_if($quiz->isClosed(), 422, 'This quiz has closed.');

        $this->assertEnrolled($student, $quiz);

        $used = $this->attemptsUsed($student, $quiz);

        abort_if(
            $used >= $quiz->attempts_allowed,
            422,
            'You have already used every attempt for this quiz.',
        );

        $questions = $quiz->questions()->get()->keyBy('id');

        abort_if($questions->isEmpty(), 422, 'This quiz has no questions.');

        $attempt = DB::transaction(function () use ($student, $quiz, $answers, $questions, $used) {
            $correct = 0;
            $earned = 0;

            foreach ($questions as $id => $question) {
                $choice = $answers[$id] ?? null;

                // A non-integer or out-of-range answer is simply wrong.
                if ($question->isCorrect(is_numeric($choice) ? (int) $choice : null)) {
                    $correct++;
                    $earned += $question->points;
                }
            }

            /*
             * Scale the earned points onto the quiz's own mark. Dividing by the
             * point total rather than the question count is what makes a
             * 5-point question worth five times a 1-point one.
             */
            $points = $questions->sum('points');
            $score = $points > 0
                ? round($earned / $points * $quiz->max_score, 2)
                : 0.0;

            return QuizAttempt::create([
                'quiz_id' => $quiz->id,
                'student_id' => $student->id,
                'school_id' => $quiz->school_id,
                // Store only the questions that belong to this paper.
                'answers' => $questions->keys()
                    ->mapWithKeys(fn ($id) => [$id => $answers[$id] ?? null])
                    ->all(),
                'correct_count' => $correct,
                'total_questions' => $questions->count(),
                'score' => $score,
                'attempt' => $used + 1,
                'started_at' => now(),
                'submitted_at' => now(),
                'is_reviewed' => false,
            ]);
        });

        // Sitting a paper locks it: the questions can no longer move under
        // students who have already answered them.
        if (! $quiz->is_locked) {
            $quiz->update(['is_locked' => true]);
        }

        return $attempt->fresh('quiz.subject');
    }

    /**
     * Per-question review of a submitted attempt.
     *
     * Only ever called once the attempt is submitted, so revealing the key
     * here cannot help a student who has yet to sit the paper.
     *
     * @return array<string, mixed>
     */
    public function reviewFor(Student $student, QuizAttempt $attempt): array
    {
        abort_unless($attempt->student_id === $student->id, 403, 'This attempt belongs to another student.');
        abort_unless($attempt->isSubmitted(), 403, 'This attempt has not been submitted.');

        $quiz = $attempt->quiz;

        $this->assertEnrolled($student, $quiz);

        $questions = $quiz->questions()->get();

        return [
            'attempt' => $attempt,
            'quiz' => $quiz->load(['subject', 'schoolClass']),
            'questions' => $questions->map(fn (QuizQuestion $question) => [
                'id' => $question->id,
                'prompt' => $question->prompt,
                'options' => $question->options,
                'points' => $question->points,
                'sequence' => $question->sequence,
                'chosen' => $attempt->answers[$question->id] ?? null,
                'correct_option' => $question->correct_option,
                'is_correct' => $question->isCorrect(
                    isset($attempt->answers[$question->id]) && is_numeric($attempt->answers[$question->id])
                        ? (int) $attempt->answers[$question->id]
                        : null,
                ),
            ])->values(),
        ];
    }

    /**
     * Teacher commentary on an auto-marked attempt, and the release of the
     * result to the student.
     */
    public function review(Teacher $teacher, QuizAttempt $attempt, ?string $feedback): QuizAttempt
    {
        $quiz = $attempt->quiz;

        abort_unless($quiz, 404, 'This attempt no longer belongs to any quiz.');

        $this->assertOwns($teacher, $quiz);

        abort_unless($attempt->isSubmitted(), 422, 'This attempt has not been submitted yet.');

        $attempt->update([
            'feedback' => $feedback,
            'is_reviewed' => true,
            'reviewed_at' => now(),
            'reviewed_by' => $teacher->id,
        ]);

        if ($attempt->student?->user) {
            $this->notifications->notify(
                $attempt->student->user,
                new QuizReviewedNotification($attempt->fresh('quiz')),
            );
        }

        return $attempt->fresh(['quiz.subject', 'student.user']);
    }

    // ------------------------------------------------------------- results

    /**
     * The class roster for one quiz, with per-question breakdown so a teacher
     * can see which item everyone missed.
     *
     * @return array<string, mixed>
     */
    public function resultsFor(Teacher $teacher, Quiz $quiz): array
    {
        $this->assertOwns($teacher, $quiz);

        $questions = $quiz->questions()->get();

        $attempts = QuizAttempt::query()
            ->with('student.user')
            ->where('quiz_id', $quiz->id)
            ->whereNotNull('submitted_at')
            ->get()
            // Best attempt per student is what counts towards their mark.
            ->groupBy('student_id')
            ->map(fn (Collection $rows) => $rows->sortByDesc('score')->first());

        $enrolled = $this->enrolledStudents($quiz);

        $students = $enrolled
            ->map(function (Student $student) use ($attempts, $quiz) {
                $attempt = $attempts->get($student->id);

                return [
                    'student_id' => $student->id,
                    'name' => $student->user?->name,
                    'matricule' => $student->matricule,
                    'score' => $attempt?->score,
                    'max_score' => $quiz->max_score,
                    'correct_count' => $attempt?->correct_count,
                    'total_questions' => $attempt?->total_questions,
                    'percentage' => $attempt?->percentage(),
                    'attempts' => $attempt?->attempt,
                    'is_reviewed' => (bool) $attempt?->is_reviewed,
                    'submitted_at' => $attempt?->submitted_at?->toIso8601String(),
                    'attempt_id' => $attempt?->id,
                    'status' => $attempt ? 'submitted' : 'not_attempted',
                ];
            })
            ->sortBy('name')
            ->values();

        $scores = $students->pluck('score')->filter(fn ($value) => $value !== null);

        return [
            'quiz' => $quiz->load(['subject', 'schoolClass']),
            'students' => $students,
            'questions' => $questions->map(fn (QuizQuestion $question) => [
                'id' => $question->id,
                'prompt' => $question->prompt,
                'points' => $question->points,
                'sequence' => $question->sequence,
                'correct_count' => $attempts->filter(function (QuizAttempt $attempt) use ($question) {
                    $choice = $attempt->answers[$question->id] ?? null;

                    return $question->isCorrect(is_numeric($choice) ? (int) $choice : null);
                })->count(),
            ])->values(),
            'stats' => [
                'total' => $students->count(),
                'submitted' => $scores->count(),
                'average' => $scores->isEmpty() ? null : round($scores->avg(), 2),
                'highest' => $scores->isEmpty() ? null : round((float) $scores->max(), 2),
                'lowest' => $scores->isEmpty() ? null : round((float) $scores->min(), 2),
                'pass_rate' => $scores->isEmpty()
                    ? null
                    : round(
                        $scores->filter(fn ($value) => $value >= config('synapse.grading.pass_mark'))->count()
                            / $scores->count() * 100,
                        1,
                    ),
            ],
        ];
    }

    /**
     * @return array{total: int, published: int, awaiting_review: int}
     */
    public function teacherSummary(Teacher $teacher): array
    {
        $all = Quiz::query()->where('teacher_id', $teacher->id);
        $ids = (clone $all)->pluck('id');

        return [
            'total' => (clone $all)->count(),
            'published' => (clone $all)->published()->count(),
            'awaiting_review' => QuizAttempt::query()
                ->whereIn('quiz_id', $ids)
                ->whereNotNull('submitted_at')
                ->where('is_reviewed', false)
                ->count(),
        ];
    }

    /**
     * @return array{available: int, completed: int, average: float|null}
     */
    public function studentSummary(Student $student): array
    {
        $rows = $this->forStudent($student);

        $scores = $rows
            ->pluck('attempt')
            ->filter()
            ->pluck('score')
            ->filter(fn ($value) => $value !== null);

        return [
            'available' => $rows->count(),
            'completed' => $scores->count(),
            'average' => $scores->isEmpty() ? null : round($scores->avg(), 2),
        ];
    }

    // ------------------------------------------------------------- helpers

    private function attemptsUsed(Student $student, Quiz $quiz): int
    {
        return QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->whereNotNull('submitted_at')
            ->count();
    }

    /**
     * Rebuild a paper from the submitted question list. Only ever reached while
     * the quiz is unlocked, so no student's answers are invalidated.
     *
     * @param  list<array<string, mixed>>  $questions
     */
    private function replaceQuestions(Quiz $quiz, array $questions): void
    {
        $quiz->questions()->delete();

        $sequence = 0;

        foreach ($questions as $question) {
            $sequence++;

            QuizQuestion::create([
                'quiz_id' => $quiz->id,
                'school_id' => $quiz->school_id,
                'prompt' => $question['prompt'],
                'options' => array_values($question['options'] ?? []),
                'correct_option' => (int) ($question['correct_option'] ?? 0),
                'points' => (int) ($question['points'] ?? 1),
                'sequence' => (int) ($question['sequence'] ?? $sequence),
            ]);
        }
    }

    private function assertNoDuplicateTitle(int $classId, int $subjectId, int $yearId, string $title, ?int $exceptId = null): void
    {
        $duplicate = Quiz::query()
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('academic_year_id', $yearId)
            ->where('title', $title)
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->exists();

        abort_if($duplicate, 422, 'A quiz with this title already exists for that class and subject.');
    }

    /**
     * @return Collection<int, Student>
     */
    private function enrolledStudents(Quiz $quiz): Collection
    {
        return $quiz->schoolClass
            ->students()
            ->with('user')
            ->wherePivot('academic_year_id', $quiz->academic_year_id)
            ->get();
    }

    private function enrolledUsers(Quiz $quiz): Collection
    {
        return $this->enrolledStudents($quiz)
            ->map(fn (Student $student) => $student->user)
            ->filter()
            ->values();
    }

    private function assertEnrolled(Student $student, Quiz $quiz): void
    {
        $enrolled = $student->enrollments()
            ->where('class_id', $quiz->class_id)
            ->where('academic_year_id', $quiz->academic_year_id)
            ->exists();

        abort_unless($enrolled, 403, 'You are not enrolled in the class this quiz was set for.');
    }

    private function assertAssigned(Teacher $teacher, SchoolClass $class, Subject $subject, AcademicYear $year): void
    {
        $assigned = $teacher->teachingAssignments()
            ->where('class_id', $class->id)
            ->where('subject_id', $subject->id)
            ->where('academic_year_id', $year->id)
            ->exists();

        abort_unless($assigned, 403, 'You are not assigned to teach this subject in this class.');
    }

    private function assertOwns(Teacher $teacher, Quiz $quiz): void
    {
        abort_unless($quiz->teacher_id === $teacher->id, 403, 'This quiz belongs to another teacher.');
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
