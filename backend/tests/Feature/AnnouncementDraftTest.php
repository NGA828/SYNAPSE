<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AnnouncementDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Announcement drafting (Phase 7.1).
 *
 * The invariant this file protects: drafting cannot publish. `AnnouncementService`
 * is untouched by the drafting path, so a draft creates no row, notifies nobody
 * and cannot reach the audience fan-out. An administrator always reads it first.
 */
class AnnouncementDraftTest extends TestCase
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

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'subject' => 'the mid-term examinations begin on Monday',
            'key_points' => ['Bring your student card', 'Phones are not permitted'],
            'action_required' => 'Collect your timetable from the office',
            'date_text' => 'Monday 14 September',
            'venue' => 'the main hall',
            'audience' => 'all',
            'tone' => 'formal',
        ], $overrides);
    }

    // --------------------------------------------------------------- the happy path

    public function test_an_admin_can_draft_an_announcement(): void
    {
        $this->actAs('admin@synapse.test');

        $response = $this->postJson('/api/admin/announcements/draft', $this->payload())
            ->assertOk();

        $this->assertNotEmpty($response->json('data.title'));
        $this->assertNotEmpty($response->json('data.body'));
        $this->assertSame('deterministic', $response->json('data.source'), 'AI is unconfigured by default.');
        $this->assertFalse($response->json('data.ai_available'));
    }

    public function test_the_draft_is_written_in_the_administrator_locale(): void
    {
        $admin = $this->actAs('admin@synapse.test');
        $admin->update(['locale' => 'fr']);

        $this->postJson('/api/admin/announcements/draft', $this->payload())
            ->assertOk()
            ->assertJsonPath('data.locale', 'fr');

        $response = $this->postJson('/api/admin/announcements/draft', $this->payload())->json('data.body');

        $this->assertStringContainsString('Chers élèves', $response);
    }

    public function test_an_explicit_locale_overrides_the_account_setting(): void
    {
        $admin = $this->actAs('admin@synapse.test');
        $admin->update(['locale' => 'en']);

        $this->postJson('/api/admin/announcements/draft', $this->payload(locale: 'fr'))
            ->assertOk()
            ->assertJsonPath('data.locale', 'fr');
    }

    // ------------------------------------------- drafting must not publish anything

    public function test_drafting_creates_no_announcement(): void
    {
        $this->actAs('admin@synapse.test');

        $before = Announcement::query()->count();

        $this->postJson('/api/admin/announcements/draft', $this->payload())->assertOk();
        $this->postJson('/api/admin/announcements/draft', $this->payload())->assertOk();

        $this->assertSame($before, Announcement::query()->count(), 'A draft is not an announcement.');
    }

    public function test_drafting_notifies_nobody(): void
    {
        Notification::fake();

        $this->actAs('admin@synapse.test');

        $this->postJson('/api/admin/announcements/draft', $this->payload())->assertOk();

        Notification::assertNothingSent();
    }

    public function test_drafting_is_audited_and_marked_not_ai(): void
    {
        $this->actAs('admin@synapse.test');

        $this->postJson('/api/admin/announcements/draft', $this->payload())->assertOk();

        $log = AuditLog::query()->where('action', 'announcement.drafted')->first();

        $this->assertNotNull($log, 'Drafting should leave a provenance trail.');
        $this->assertFalse($log->metadata['ai_generated']);
        $this->assertSame('deterministic', $log->metadata['drafter']);
    }

    /**
     * The point of the whole feature: the text that comes back must be
     * publishable as-is. A draft that fails `StoreAnnouncementRequest` would be
     * worse than no draft, because the admin would trust it.
     */
    public function test_the_draft_can_be_published_without_editing(): void
    {
        $this->actAs('admin@synapse.test');

        $draft = $this->postJson('/api/admin/announcements/draft', $this->payload())
            ->assertOk()
            ->json('data');

        $this->postJson('/api/admin/announcements', [
            'title' => $draft['title'],
            'body' => $draft['body'],
            'audience' => 'all',
        ])->assertCreated();

        $this->assertSame(1, Announcement::query()->count());
    }

    public function test_even_an_overlong_brief_drafts_something_publishable(): void
    {
        $this->actAs('admin@synapse.test');

        $draft = $this->postJson('/api/admin/announcements/draft', $this->payload(
            key_points: [str_repeat('A very long point about arrangements and expectations. ', 12)],
        ))->assertOk()->json('data');

        $this->assertLessThanOrEqual(5000, mb_strlen($draft['body']));

        $this->postJson('/api/admin/announcements', [
            'title' => $draft['title'],
            'body' => $draft['body'],
            'audience' => 'all',
        ])->assertCreated();
    }

    // ------------------------------------------------------------------ validation

    public function test_a_subject_is_required(): void
    {
        $this->actAs('admin@synapse.test');

        $this->postJson('/api/admin/announcements/draft', $this->payload(subject: ''))
            ->assertStatus(422)
            ->assertJsonValidationErrors('subject');
    }

    public function test_an_unknown_audience_is_rejected(): void
    {
        $this->actAs('admin@synapse.test');

        $this->postJson('/api/admin/announcements/draft', $this->payload(audience: 'parents'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('audience');
    }

    public function test_an_unknown_tone_is_rejected(): void
    {
        $this->actAs('admin@synapse.test');

        $this->postJson('/api/admin/announcements/draft', $this->payload(tone: 'sarcastic'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('tone');
    }

    public function test_a_locale_outside_the_supported_pair_is_rejected(): void
    {
        $this->actAs('admin@synapse.test');

        $this->postJson('/api/admin/announcements/draft', $this->payload(locale: 'de'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('locale');
    }

    public function test_an_absurd_number_of_key_points_is_rejected(): void
    {
        $this->actAs('admin@synapse.test');

        $this->postJson('/api/admin/announcements/draft', $this->payload(
            key_points: array_fill(0, 11, 'A point'),
        ))->assertStatus(422)->assertJsonValidationErrors('key_points');
    }

    // --------------------------------------------------------------- access control

    public function test_a_teacher_cannot_draft_an_announcement(): void
    {
        $this->actAs('teacher@synapse.test');

        $this->postJson('/api/admin/announcements/draft', $this->payload())->assertForbidden();
    }

    public function test_a_student_cannot_draft_an_announcement(): void
    {
        $this->actAs('student@synapse.test');

        $this->postJson('/api/admin/announcements/draft', $this->payload())->assertForbidden();
    }

    public function test_a_guest_cannot_draft_an_announcement(): void
    {
        $this->postJson('/api/admin/announcements/draft', $this->payload())->assertUnauthorized();
    }

    // -------------------------------------------------------------- provider safety

    public function test_a_provider_failure_falls_back_to_the_deterministic_draft(): void
    {
        config()->set('ai.enabled', true);
        config()->set('ai.driver', 'http');
        config()->set('ai.key', 'test-key');
        config()->set('ai.model', 'test-model');
        config()->set('ai.fallback_on_error', true);

        Http::fake(['*' => Http::response(['error' => 'upstream'], 500)]);

        $admin = $this->actAs('admin@synapse.test');
        $admin->school->subscription->plan->update([
            'features' => array_merge($admin->school->subscription->plan->features, ['ai_assistant']),
        ]);

        $response = $this->postJson('/api/admin/announcements/draft', $this->payload())->assertOk();

        $this->assertNotEmpty($response->json('data.body'));
        $this->assertStringContainsString('This is to inform you that', $response->json('data.body'));
    }

    public function test_a_malformed_provider_response_falls_back(): void
    {
        config()->set('ai.enabled', true);
        config()->set('ai.driver', 'http');
        config()->set('ai.key', 'test-key');
        config()->set('ai.model', 'test-model');

        // Valid HTTP, but not the JSON object the drafter expects.
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => 'Here is your announcement!']]],
        ])]);

        $admin = $this->actAs('admin@synapse.test');
        $admin->school->subscription->plan->update([
            'features' => array_merge($admin->school->subscription->plan->features, ['ai_assistant']),
        ]);

        $response = $this->postJson('/api/admin/announcements/draft', $this->payload())->assertOk();

        $this->assertNotEmpty($response->json('data.body'));
    }

    public function test_a_provider_completion_is_truncated_to_the_word_ceiling(): void
    {
        config()->set('ai.enabled', true);
        config()->set('ai.driver', 'http');
        config()->set('ai.key', 'test-key');
        config()->set('ai.model', 'test-model');
        config()->set('ai.announcements.max_words', 12);

        $completion = json_encode([
            'title' => 'Examinations',
            'body' => trim(str_repeat('word ', 300)),
        ]);

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => $completion]]],
        ])]);

        $admin = $this->actAs('admin@synapse.test');
        $admin->school->subscription->plan->update([
            'features' => array_merge($admin->school->subscription->plan->features, ['ai_assistant']),
        ]);

        $body = $this->postJson('/api/admin/announcements/draft', $this->payload())->json('data.body');

        $this->assertLessThanOrEqual(13, count(preg_split('/\s+/', trim($body))));
    }

    public function test_no_school_identity_is_sent_to_a_provider(): void
    {
        config()->set('ai.enabled', true);
        config()->set('ai.driver', 'http');
        config()->set('ai.key', 'test-key');
        config()->set('ai.model', 'test-model');

        $completion = json_encode([
            'title' => 'Examinations',
            'body' => 'The examinations begin on Monday.',
        ]);

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => $completion]]],
        ])]);

        $admin = $this->actAs('admin@synapse.test');
        $school = $admin->school;
        $school->subscription->plan->update([
            'features' => array_merge($school->subscription->plan->features, ['ai_assistant']),
        ]);

        $this->postJson('/api/admin/announcements/draft', $this->payload())->assertOk();

        Http::assertSent(function ($request) use ($school, $admin) {
            $body = (string) json_encode($request->data());

            $this->assertStringNotContainsString($school->name, $body);
            $this->assertStringNotContainsString((string) $school->slug, $body);
            $this->assertStringNotContainsString($admin->name, $body);
            $this->assertStringNotContainsString($admin->email, $body);

            return true;
        });
    }

    public function test_the_deterministic_drafter_is_used_when_the_plan_lacks_the_flag(): void
    {
        config()->set('ai.enabled', true);
        config()->set('ai.driver', 'http');
        config()->set('ai.key', 'test-key');
        config()->set('ai.model', 'test-model');

        Http::fake();

        $admin = $this->actAs('admin@synapse.test');

        // The seeded Professional plan does not carry ai_assistant.
        $this->assertFalse(app(AnnouncementDraftService::class)->aiAvailable($admin));

        $this->postJson('/api/admin/announcements/draft', $this->payload())
            ->assertOk()
            ->assertJsonPath('data.source', 'deterministic');

        Http::assertNothingSent();
    }

    public function test_a_provider_is_used_when_the_plan_carries_the_flag(): void
    {
        config()->set('ai.enabled', true);
        config()->set('ai.driver', 'http');
        config()->set('ai.key', 'test-key');
        config()->set('ai.model', 'test-model');

        $completion = json_encode([
            'title' => 'Examinations',
            'body' => 'The mid-term examinations begin on Monday.',
        ]);

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => $completion]]],
        ])]);

        $admin = $this->actAs('admin@synapse.test');
        $admin->school->subscription->plan->update([
            'features' => array_merge($admin->school->subscription->plan->features, ['ai_assistant']),
        ]);

        $this->assertTrue(app(AnnouncementDraftService::class)->aiAvailable($admin->fresh()));

        $this->postJson('/api/admin/announcements/draft', $this->payload())
            ->assertOk()
            ->assertJsonPath('data.source', 'http');

        Http::assertSentCount(1);
    }
}
