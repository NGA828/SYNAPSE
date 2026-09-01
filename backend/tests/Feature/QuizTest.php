<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Attachment;
use App\Models\Enrollment;
use App\Models\Quiz;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Auto-marked quizzes — authoring, the answer-key boundary, marking, and the
 * authorisation and tenancy rules around them.
 *
 * Every test builds its own fixture rather than relying on seeded rows.
 */
class QuizTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function actAs(string|User $identity): User
    {
        $user = $identity instanceof User
            ? $identity
            : User::where('email', $identity)->firstOrFail();

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    /** Mr. David (TCH-001) teaches English and History in Level 3A. */
    private function david(): Teacher
    {
        return Teacher::where('staff_no', 'TCH-001')->firstOrFail();
    }

    private function john(): Student
    {
        return Student::where('matricule', 'ST2026045')->firstOrFail();
    }

    private function level3a(): SchoolClass
    {
        return SchoolClass::where('name', 'Level 3A')->firstOrFail();
    }

    private function english(): Subject
    {
        return Subject::where('code', 'ENG')->firstOrFail();
    }

    private function year(): AcademicYear
    {
        return AcademicYear::current();
    }

    /**
     * Two 5-point questions, so a half-correct paper earns exactly half the
     * quiz's max_score. That is what makes the scaling assertion meaningful.
     *
     * @return list<array<string, mixed>>
     */
    private function questions(): array
    {
        return [
            ['prompt' => 'Two plus two?', 'options' => ['3', '4', '5'], 'correct_option' => 1, 'points' => 5],
            ['prompt' => 'Capital of Cameroon?', 'options' => ['Douala', 'Buea', 'Yaoundé'], 'correct_option' => 2, 'points' => 5],
        ];
    }

    private function createViaApi(array $overrides = []): array
    {
        $this->actAs('teacher@synapse.test');

        return $this->postJson('/api/teacher/quizzes', array_merge([
            'class_id' => $this->level3a()->id,
            'subject_id' => $this->english()->id,
            'title' => 'API quiz '.uniqid(),
            'instructions' => 'Choose one option per question.',
            'max_score' => 20,
            'questions' => $this->questions(),
        ], $overrides))->assertCreated()->json('data');
    }

    private function makeQuiz(array $overrides = []): Quiz
    {
        return Quiz::create(array_merge([
            'school_id' => $this->level3a()->school_id,
            'teacher_id' => $this->david()->id,
            'subject_id' => $this->english()->id,
            'class_id' => $this->level3a()->id,
            'academic_year_id' => $this->year()->id,
            'semester_id' => Semester::query()->where('academic_year_id', $this->year()->id)->value('id'),
            'title' => 'Fixture quiz '.uniqid(),
            'max_score' => 20,
            'attempts_allowed' => 1,
            'is_published' => true,
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    /** @param  list<array<string, mixed>>  $questions */
    private function attachQuestions(Quiz $quiz, array $questions): void
    {
        foreach ($questions as $index => $question) {
            $quiz->questions()->create([
                'school_id' => $quiz->school_id,
                'sequence' => $index + 1,
                ...$question,
            ]);
        }
    }

    // ------------------------------------------------------------- authoring

    public function test_a_quiz_is_created_as_an_unpublished_draft_with_its_questions(): void
    {
        $data = $this->createViaApi();

        $this->assertFalse($data['is_published']);
        $this->assertSame(2, $data['questions_count']);
        // The author sees the key.
        $this->assertSame(2, $data['questions'][1]['correct_option']);
        $this->assertSame('Yaoundé', $data['questions'][1]['correct_answer']);
    }

    public function test_a_teacher_cannot_set_a_quiz_in_a_subject_they_do_not_teach(): void
    {
        $this->actAs('teacher@synapse.test');

        $this->postJson('/api/teacher/quizzes', [
            'class_id' => $this->level3a()->id,
            'subject_id' => Subject::where('code', 'MAT')->value('id'),
            'title' => 'Not my subject',
            'max_score' => 20,
        ])->assertForbidden();
    }

    public function test_a_duplicate_title_for_the_same_class_and_subject_is_rejected(): void
    {
        $data = $this->createViaApi();

        $this->actAs('teacher@synapse.test');

        $this->postJson('/api/teacher/quizzes', [
            'class_id' => $this->level3a()->id,
            'subject_id' => $this->english()->id,
            'title' => $data['title'],
            'max_score' => 20,
        ])->assertStatus(422)->assertJsonValidationErrors('title');
    }

    public function test_an_answer_key_outside_the_options_is_rejected(): void
    {
        $this->actAs('teacher@synapse.test');

        $this->postJson('/api/teacher/quizzes', [
            'class_id' => $this->level3a()->id,
            'subject_id' => $this->english()->id,
            'title' => 'Bad key '.uniqid(),
            'max_score' => 20,
            'questions' => [
                ['prompt' => 'Pick one', 'options' => ['a', 'b'], 'correct_option' => 5],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('questions.0.correct_option');
    }

    public function test_an_empty_paper_cannot_be_published(): void
    {
        $quiz = $this->makeQuiz(['is_published' => false, 'published_at' => null]);

        $this->actAs('teacher@synapse.test');

        $this->postJson("/api/teacher/quizzes/{$quiz->id}/publish")
            ->assertStatus(422);
    }

    public function test_publishing_makes_the_quiz_visible_to_the_class(): void
    {
        $quiz = $this->makeQuiz(['is_published' => false, 'published_at' => null]);
        $this->attachQuestions($quiz, $this->questions());

        $this->actAs('student@synapse.test');
        $this->assertNotContains($quiz->id, $this->studentQuizIds());

        $this->actAs('teacher@synapse.test');
        $this->postJson("/api/teacher/quizzes/{$quiz->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.is_published', true);

        $this->actAs('student@synapse.test');
        $this->assertContains($quiz->id, $this->studentQuizIds());
    }

    // ---------------------------------------------------- the answer key

    public function test_the_student_paper_never_contains_the_answer_key(): void
    {
        $quiz = $this->makeQuiz();
        $this->attachQuestions($quiz, $this->questions());

        $this->actAs('student@synapse.test');

        $response = $this->getJson("/api/student/quizzes/{$quiz->id}/paper")->assertOk();

        // Structural checks, then a raw sweep so a new field cannot leak it.
        foreach ($response->json('questions') as $question) {
            $this->assertArrayNotHasKey('correct_option', $question);
            $this->assertArrayNotHasKey('correct_answer', $question);
            $this->assertArrayHasKey('options', $question);
        }

        $raw = $response->getContent();
        $this->assertStringNotContainsString('correct_option', $raw);
        $this->assertStringNotContainsString('correct_answer', $raw);
        $this->assertStringNotContainsString('is_correct', $raw);
    }

    public function test_the_student_quiz_list_never_contains_the_answer_key(): void
    {
        $quiz = $this->makeQuiz();
        $this->attachQuestions($quiz, $this->questions());

        $this->actAs('student@synapse.test');

        $this->assertStringNotContainsString('correct_option', $this->getJson('/api/student/quizzes')->assertOk()->getContent());
    }

    public function test_the_review_reveals_the_key_only_after_submission(): void
    {
        $quiz = $this->makeQuiz();
        $this->attachQuestions($quiz, $this->questions());
        $questions = $quiz->questions;

        $this->actAs('student@synapse.test');
        $attemptId = $this->postJson("/api/student/quizzes/{$quiz->id}/submit", [
            'answers' => [$questions[0]->id => 1, $questions[1]->id => 0],
        ])->assertCreated()->json('data.id');

        $review = $this->getJson("/api/student/quiz-attempts/{$attemptId}/review")->assertOk();

        $this->assertSame(2, $review->json('questions.1.correct_option'));
        $this->assertFalse($review->json('questions.1.is_correct'));
        $this->assertTrue($review->json('questions.0.is_correct'));
    }

    public function test_a_student_cannot_read_another_students_attempt(): void
    {
        $quiz = $this->makeQuiz();
        $this->attachQuestions($quiz, $this->questions());
        $questions = $quiz->questions;

        $this->actAs('student@synapse.test');
        $attemptId = $this->postJson("/api/student/quizzes/{$quiz->id}/submit", [
            'answers' => [$questions[0]->id => 1],
        ])->assertCreated()->json('data.id');

        $this->actAs('mary@synapse.test');
        $this->getJson("/api/student/quiz-attempts/{$attemptId}/review")->assertForbidden();
    }

    // -------------------------------------------------------- auto-marking

    public function test_a_paper_is_marked_from_the_points_earned_not_the_question_count(): void
    {
        $quiz = $this->makeQuiz();
        $this->attachQuestions($quiz, $this->questions());
        $questions = $quiz->questions;

        $this->actAs('student@synapse.test');

        // One of the two 5-point questions right: 5 of 10 points = 10 of 20.
        $attempt = $this->postJson("/api/student/quizzes/{$quiz->id}/submit", [
            'answers' => [$questions[0]->id => 1, $questions[1]->id => 0],
        ])->assertCreated()->json('data');

        $this->assertSame(1, $attempt['correct_count']);
        $this->assertSame(2, $attempt['total_questions']);
        $this->assertEqualsWithDelta(10.0, $attempt['score'], 0.01);
        $this->assertEqualsWithDelta(50.0, $attempt['percentage'], 0.01);
    }

    public function test_a_perfect_paper_scores_the_full_mark(): void
    {
        $quiz = $this->makeQuiz();
        $this->attachQuestions($quiz, $this->questions());
        $questions = $quiz->questions;

        $this->actAs('student@synapse.test');

        $attempt = $this->postJson("/api/student/quizzes/{$quiz->id}/submit", [
            'answers' => [$questions[0]->id => 1, $questions[1]->id => 2],
        ])->assertCreated()->json('data');

        $this->assertSame(2, $attempt['correct_count']);
        $this->assertEqualsWithDelta(20.0, $attempt['score'], 0.01);
    }

    public function test_an_unanswered_question_is_wrong_rather_than_an_error(): void
    {
        $quiz = $this->makeQuiz();
        $this->attachQuestions($quiz, $this->questions());
        $questions = $quiz->questions;

        $this->actAs('student@synapse.test');

        $attempt = $this->postJson("/api/student/quizzes/{$quiz->id}/submit", [
            'answers' => [$questions[0]->id => 1],
        ])->assertCreated()->json('data');

        $this->assertSame(1, $attempt['correct_count']);
        $this->assertEqualsWithDelta(10.0, $attempt['score'], 0.01);
    }

    public function test_a_student_cannot_exceed_the_attempt_limit(): void
    {
        $quiz = $this->makeQuiz();
        $this->attachQuestions($quiz, $this->questions());
        $questions = $quiz->questions;

        $this->actAs('student@synapse.test');

        $this->postJson("/api/student/quizzes/{$quiz->id}/submit", [
            'answers' => [$questions[0]->id => 1],
        ])->assertCreated();

        $this->postJson("/api/student/quizzes/{$quiz->id}/submit", [
            'answers' => [$questions[0]->id => 1],
        ])->assertStatus(422);

        $this->getJson("/api/student/quizzes/{$quiz->id}/paper")->assertStatus(422);
    }

    public function test_a_closed_quiz_cannot_be_sat(): void
    {
        $quiz = $this->makeQuiz(['closes_at' => now()->subHour()]);
        $this->attachQuestions($quiz, $this->questions());

        $this->actAs('student@synapse.test');

        $this->getJson("/api/student/quizzes/{$quiz->id}/paper")->assertForbidden();
    }

    public function test_an_unpublished_quiz_is_refused_to_students(): void
    {
        $quiz = $this->makeQuiz(['is_published' => false, 'published_at' => null]);
        $this->attachQuestions($quiz, $this->questions());

        $this->actAs('student@synapse.test');

        $this->getJson("/api/student/quizzes/{$quiz->id}/paper")->assertForbidden();
    }

    public function test_a_student_cannot_sit_a_quiz_set_for_another_class(): void
    {
        $level2a = SchoolClass::where('name', 'Level 2A')->firstOrFail();
        $theirs = $this->makeQuiz(['class_id' => $level2a->id]);
        $this->attachQuestions($theirs, $this->questions());

        // Enrolled in Level 2A this year, so only class membership can stop them.
        $outsider = $this->enrolledInLevel2a();

        $this->actAs($outsider->user);
        $this->getJson("/api/student/quizzes/{$theirs->id}/paper")->assertOk();

        $this->actAs('student@synapse.test');
        $this->getJson("/api/student/quizzes/{$theirs->id}/paper")->assertForbidden();
    }

    public function test_sitting_a_paper_locks_its_questions(): void
    {
        $quiz = $this->makeQuiz();
        $this->attachQuestions($quiz, $this->questions());
        $questions = $quiz->questions;

        $this->actAs('student@synapse.test');
        $this->postJson("/api/student/quizzes/{$quiz->id}/submit", [
            'answers' => [$questions[0]->id => 1],
        ])->assertCreated();

        $this->assertTrue($quiz->fresh()->is_locked);

        $this->actAs('teacher@synapse.test');
        $this->putJson("/api/teacher/quizzes/{$quiz->id}", [
            'questions' => $this->questions(),
        ])->assertStatus(422);
    }

    // ------------------------------------------------------------- results

    public function test_the_teacher_sees_class_results_and_per_question_breakdown(): void
    {
        $quiz = $this->makeQuiz();
        $this->attachQuestions($quiz, $this->questions());
        $questions = $quiz->questions;

        $this->actAs('student@synapse.test');
        $this->postJson("/api/student/quizzes/{$quiz->id}/submit", [
            'answers' => [$questions[0]->id => 1, $questions[1]->id => 0],
        ])->assertCreated();

        $this->actAs('teacher@synapse.test');

        $results = $this->getJson("/api/teacher/quizzes/{$quiz->id}/results")->assertOk();

        $this->assertSame(3, $results->json('stats.total'));
        $this->assertSame(1, $results->json('stats.submitted'));
        $this->assertEqualsWithDelta(10.0, $results->json('stats.average'), 0.01);
        $this->assertCount(2, $results->json('questions'));
        $this->assertSame(1, $results->json('questions.0.correct_count'));
        $this->assertSame(0, $results->json('questions.1.correct_count'));
    }

    public function test_a_teacher_can_add_feedback_and_the_student_is_notified(): void
    {
        $quiz = $this->makeQuiz();
        $this->attachQuestions($quiz, $this->questions());
        $questions = $quiz->questions;

        $this->actAs('student@synapse.test');
        $attemptId = $this->postJson("/api/student/quizzes/{$quiz->id}/submit", [
            'answers' => [$questions[0]->id => 1],
        ])->assertCreated()->json('data.id');

        $this->actAs('teacher@synapse.test');

        $this->postJson("/api/teacher/quiz-attempts/{$attemptId}/review", [
            'feedback' => 'Half marks — revisit question two.',
        ])->assertOk()->assertJsonPath('data.is_reviewed', true);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->john()->user_id,
            'type' => 'quiz_reviewed',
        ]);
    }

    public function test_a_teacher_cannot_touch_another_teachers_quiz(): void
    {
        $quiz = $this->makeQuiz();

        $this->actAs('sarah@synapse.test');

        $this->getJson("/api/teacher/quizzes/{$quiz->id}")->assertForbidden();
        $this->putJson("/api/teacher/quizzes/{$quiz->id}", ['title' => 'Hijacked'])->assertForbidden();
        $this->deleteJson("/api/teacher/quizzes/{$quiz->id}")->assertForbidden();
        $this->postJson("/api/teacher/quizzes/{$quiz->id}/publish")->assertForbidden();
        $this->getJson("/api/teacher/quizzes/{$quiz->id}/results")->assertForbidden();
    }

    // ----------------------------------------------------------- tenancy

    public function test_quizzes_never_leak_across_tenants(): void
    {
        $quiz = $this->makeQuiz();
        $this->attachQuestions($quiz, $this->questions());

        $this->actAs('teacher.saintalbert@synapse.test');

        $this->getJson('/api/teacher/quizzes')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/teacher/quizzes/{$quiz->id}")->assertNotFound();
        $this->getJson("/api/teacher/quizzes/{$quiz->id}/results")->assertNotFound();
    }

    public function test_another_schools_student_sees_no_quizzes(): void
    {
        $quiz = $this->makeQuiz();
        $this->attachQuestions($quiz, $this->questions());

        $this->actAs('student.saintalbert@synapse.test');

        $this->getJson('/api/student/quizzes')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/student/quizzes/{$quiz->id}/paper")->assertNotFound();
    }

    public function test_a_quiz_attachment_cannot_be_downloaded_by_another_school(): void
    {
        $this->actAs('teacher@synapse.test');

        $quizId = $this->postJson('/api/teacher/quizzes', [
            'class_id' => $this->level3a()->id,
            'subject_id' => $this->english()->id,
            'title' => 'With a paper '.uniqid(),
            'max_score' => 20,
            'questions' => $this->questions(),
            'attachments' => [UploadedFile::fake()->create('paper.pdf', 12, 'application/pdf')],
        ])->assertCreated()->json('data.id');

        $attachmentId = Attachment::query()
            ->where('attachable_type', Quiz::class)
            ->where('attachable_id', $quizId)
            ->value('id');

        $this->assertNotNull($attachmentId);

        $this->actAs('teacher.saintalbert@synapse.test');
        $this->get("/api/attachments/{$attachmentId}/download")->assertNotFound();

        $this->actAs('student.saintalbert@synapse.test');
        $this->get("/api/attachments/{$attachmentId}/download")->assertNotFound();
    }

    public function test_an_unauthenticated_request_is_refused(): void
    {
        $this->getJson('/api/teacher/quizzes')->assertUnauthorized();
        $this->getJson('/api/student/quizzes')->assertUnauthorized();
    }

    // --------------------------------------------------------------- helpers

    /**
     * @return list<int>
     */
    private function studentQuizIds(): array
    {
        return collect($this->getJson('/api/student/quizzes')->assertOk()->json('data'))
            ->pluck('quiz.id')
            ->all();
    }

    /**
     * A student enrolled in Level 2A for the current year. The seeded Level 2A
     * enrolment belongs to the previous year, so this keeps the
     * class-membership assertion independent of the academic year.
     */
    private function enrolledInLevel2a(): Student
    {
        $level2a = SchoolClass::where('name', 'Level 2A')->firstOrFail();

        $user = User::create([
            'school_id' => $level2a->school_id,
            'name' => 'Other Class Student',
            'email' => 'quizclass@synapse.test',
            'password' => 'password',
            'role' => User::ROLE_STUDENT,
        ]);

        $student = Student::create([
            'school_id' => $level2a->school_id,
            'user_id' => $user->id,
            'matricule' => 'ST2026901',
        ]);

        Enrollment::create([
            'school_id' => $level2a->school_id,
            'student_id' => $student->id,
            'class_id' => $level2a->id,
            'academic_year_id' => $this->year()->id,
        ]);

        return $student->load('user');
    }
}
