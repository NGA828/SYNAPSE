<?php

namespace Tests\Feature;

use App\Contracts\CommentWriter;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\ReportCardComment;
use App\Models\Student;
use App\Models\User;
use App\Services\CommentService;
use App\Services\Pdf\ReportCardPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Report-card comments (Phase 6.2).
 *
 * Two invariants carry this file. First, a comment describes numbers that
 * GradeService computed — it never derives one. Second, generated text is a
 * draft: it reaches a report card only after a person has saved and locked it.
 */
class ReportCardCommentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function actAs(string $email): User
    {
        $user = User::where('email', $email)->firstOrFail();

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function student(string $email = 'student@synapse.test'): Student
    {
        return User::where('email', $email)->firstOrFail()->student;
    }

    private function reportCard(Student $student): array
    {
        return app(CommentService::class)->reportCardFor($student);
    }

    // ---------------------------------------------------------- the improvement

    public function test_two_pupils_with_the_same_average_get_different_comments(): void
    {
        // John has four subjects; Peter has one. Their spreads differ, so the
        // text must differ — under the old four-sentence ladder it could not.
        $john = app(CommentService::class)->writeFromArray($this->reportCard($this->student('student@synapse.test')));
        $peter = app(CommentService::class)->writeFromArray($this->reportCard($this->student('peter@synapse.test')));

        $this->assertNotSame($john, $peter);
        $this->assertNotEmpty($john);
    }

    public function test_the_comment_cites_a_real_subject_from_the_card(): void
    {
        $student = $this->student('student@synapse.test');
        $comment = app(CommentService::class)->writeFromArray($this->reportCard($student));

        $subjects = collect($this->reportCard($student)['grades'])->pluck('subject')->all();

        $namedAny = collect($subjects)->contains(fn (string $subject) => str_contains($comment, $subject));

        $this->assertTrue($namedAny, "Comment names none of the card's subjects: {$comment}");
    }

    public function test_the_average_in_the_comment_is_the_computed_average(): void
    {
        $student = $this->student('student@synapse.test');
        $card = $this->reportCard($student);

        $comment = app(CommentService::class)->writeFromArray($card);

        $this->assertStringContainsString(number_format((float) $card['average'], 2), $comment);
    }

    // ------------------------------------------------------------ the presenter

    public function test_the_report_card_uses_a_generated_comment_by_default(): void
    {
        $student = $this->student('student@synapse.test');

        $presented = app(ReportCardPresenter::class)->present($this->reportCard($student));

        $this->assertNotEmpty($presented['comment']);
    }

    public function test_a_locked_comment_replaces_the_generated_one_on_the_card(): void
    {
        $student = $this->student('student@synapse.test');

        app(CommentService::class)->save(
            actor: User::where('email', 'teacher@synapse.test')->firstOrFail(),
            student: $student,
            body: 'A steady term with real improvement in written work.',
            lock: true,
        );

        $presented = app(ReportCardPresenter::class)->present($this->reportCard($student));

        $this->assertSame('A steady term with real improvement in written work.', $presented['comment']);
    }

    public function test_an_unlocked_draft_does_not_override_the_generated_comment(): void
    {
        $student = $this->student('student@synapse.test');

        $generated = app(CommentService::class)->writeFromArray($this->reportCard($student));

        app(CommentService::class)->save(
            actor: User::where('email', 'teacher@synapse.test')->firstOrFail(),
            student: $student,
            body: 'Still thinking about the wording.',
            lock: false,
        );

        $presented = app(ReportCardPresenter::class)->present($this->reportCard($student));

        $this->assertSame($generated, $presented['comment'], 'A draft is not an approval.');
    }

    // ---------------------------------------------------------------- endpoints

    public function test_a_teacher_can_draft_a_comment(): void
    {
        $student = $this->student('student@synapse.test');

        $this->actAs('teacher@synapse.test');

        $response = $this->postJson("/api/teacher/students/{$student->id}/report-card-comment/draft")
            ->assertOk();

        $this->assertNotEmpty($response->json('data.body'));
        $this->assertSame('generated', $response->json('data.source'), 'AI is unconfigured, so the draft is deterministic.');
        $this->assertFalse($response->json('data.ai_available'));
    }

    public function test_drafting_saves_nothing(): void
    {
        $student = $this->student('student@synapse.test');

        $before = ReportCardComment::query()->count();

        $this->actAs('teacher@synapse.test');
        $this->postJson("/api/teacher/students/{$student->id}/report-card-comment/draft")->assertOk();

        $this->assertSame($before, ReportCardComment::query()->count(), 'A draft is a suggestion, not a record.');
    }

    public function test_a_teacher_can_save_and_lock_a_comment(): void
    {
        $student = $this->student('student@synapse.test');

        $this->actAs('teacher@synapse.test');

        $this->putJson("/api/teacher/students/{$student->id}/report-card-comment", [
            'body' => 'Consistent effort across the term; keep the revision timetable.',
            'lock' => true,
        ])->assertOk()
            ->assertJsonPath('data.source', 'teacher')
            ->assertJsonPath('data.is_locked', true);

        $this->assertSame(1, ReportCardComment::query()->count());
    }

    public function test_saving_a_comment_is_audited_and_marked_not_ai(): void
    {
        $student = $this->student('student@synapse.test');

        $this->actAs('teacher@synapse.test');

        $this->putJson("/api/teacher/students/{$student->id}/report-card-comment", [
            'body' => 'Well handled.',
            'lock' => true,
        ])->assertOk();

        $log = AuditLog::query()->where('action', 'report_card_comment.locked')->first();

        $this->assertNotNull($log);
        $this->assertFalse($log->metadata['ai_generated']);
    }

    public function test_an_empty_comment_is_rejected(): void
    {
        $student = $this->student('student@synapse.test');

        $this->actAs('teacher@synapse.test');

        $this->putJson("/api/teacher/students/{$student->id}/report-card-comment", ['body' => '   '])
            ->assertStatus(422);
    }

    public function test_an_overlong_comment_is_rejected(): void
    {
        $student = $this->student('student@synapse.test');

        $this->actAs('teacher@synapse.test');

        $this->putJson("/api/teacher/students/{$student->id}/report-card-comment", [
            'body' => str_repeat('a', (int) config('synapse.comments.max_length', 400) + 1),
        ])->assertStatus(422);
    }

    // -------------------------------------------------------- access control

    public function test_a_teacher_cannot_comment_on_a_pupil_outside_their_classes(): void
    {
        // Move Peter into a class David does not teach.
        $peter = $this->student('peter@synapse.test');

        Enrollment::query()
            ->where('student_id', $peter->id)
            ->where('academic_year_id', AcademicYear::current()->id)
            ->update(['class_id' => SchoolClass::where('name', 'Level 1A')->value('id')]);

        $this->actAs('teacher@synapse.test');

        $this->postJson("/api/teacher/students/{$peter->id}/report-card-comment/draft")->assertForbidden();
        $this->getJson("/api/teacher/students/{$peter->id}/report-card-comment")->assertForbidden();
        $this->putJson("/api/teacher/students/{$peter->id}/report-card-comment", ['body' => 'x'])->assertForbidden();
    }

    public function test_a_student_cannot_write_their_own_comment(): void
    {
        $student = $this->student('student@synapse.test');

        $this->actAs('student@synapse.test');

        $this->postJson("/api/teacher/students/{$student->id}/report-card-comment/draft")->assertForbidden();
    }

    public function test_a_guest_cannot_draft_a_comment(): void
    {
        $student = $this->student('student@synapse.test');

        $this->postJson("/api/teacher/students/{$student->id}/report-card-comment/draft")->assertUnauthorized();
    }

    // --------------------------------------------------------- provider safety

    public function test_a_provider_failure_falls_back_instead_of_failing_the_card(): void
    {
        config()->set('ai.enabled', true);
        config()->set('ai.driver', 'http');
        config()->set('ai.key', 'test-key');
        config()->set('ai.model', 'test-model');
        config()->set('ai.fallback_on_error', true);

        Http::fake(['*' => Http::response(['error' => 'upstream'], 500)]);

        $writer = app(CommentWriter::class);
        $comment = $writer->write(app(CommentService::class)->evidence($this->student()));

        $this->assertNotEmpty($comment, 'A report card must still render.');
        $this->assertStringContainsString('Overall average', $comment);
    }

    public function test_a_generated_comment_is_truncated_to_the_configured_ceiling(): void
    {
        config()->set('ai.enabled', true);
        config()->set('ai.driver', 'http');
        config()->set('ai.key', 'test-key');
        config()->set('ai.model', 'test-model');
        config()->set('ai.max_words', 8);

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => str_repeat('word ', 200)]]],
        ])]);

        $comment = app(CommentWriter::class)
            ->write(app(CommentService::class)->evidence($this->student()));

        $this->assertLessThanOrEqual(9, count(preg_split('/\s+/', trim($comment))));
    }

    public function test_the_student_identity_is_never_sent_to_a_provider(): void
    {
        config()->set('ai.enabled', true);
        config()->set('ai.driver', 'http');
        config()->set('ai.key', 'test-key');
        config()->set('ai.model', 'test-model');

        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'A good term.']]]])]);

        $student = $this->student('student@synapse.test');

        app(CommentWriter::class)->write(app(CommentService::class)->evidence($student));

        Http::assertSent(function ($request) use ($student) {
            $body = json_encode($request->data());

            $this->assertStringNotContainsString((string) $student->user->name, $body);
            $this->assertStringNotContainsString((string) $student->matricule, $body);

            return true;
        });
    }

    public function test_the_deterministic_writer_is_used_when_ai_is_disabled(): void
    {
        config()->set('ai.enabled', false);
        config()->set('ai.driver', 'http');
        config()->set('ai.key', 'test-key');
        config()->set('ai.model', 'test-model');

        Http::fake();

        $this->assertSame(
            'deterministic',
            app(CommentWriter::class)->name(),
        );

        Http::assertNothingSent();
    }

    public function test_ai_drafting_requires_the_plan_feature(): void
    {
        config()->set('ai.enabled', true);
        config()->set('ai.driver', 'http');
        config()->set('ai.key', 'test-key');
        config()->set('ai.model', 'test-model');

        $student = $this->student('student@synapse.test');
        $school = $student->school;

        // The seeded Professional plan does not carry ai_assistant.
        $this->assertFalse(app(CommentService::class)->aiAvailable($student));

        $plan = $school->subscription->plan;
        $plan->update(['features' => array_merge($plan->features, ['ai_assistant'])]);

        $this->assertTrue(app(CommentService::class)->aiAvailable($student->fresh()));
    }
}
