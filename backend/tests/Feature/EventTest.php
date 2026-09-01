<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * School events — audience targeting, the draft boundary, and who may publish.
 *
 * The recurring theme is that a record outside your audience behaves as though
 * it does not exist: 404, never 403, so the response cannot confirm it.
 */
class EventTest extends TestCase
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

    private function makeEvent(array $overrides = []): Event
    {
        $admin = User::where('email', 'admin@synapse.test')->firstOrFail();

        return Event::create(array_merge([
            'school_id' => $admin->school_id,
            'user_id' => $admin->id,
            'title' => 'Fixture Event '.uniqid(),
            'type' => Event::TYPE_ASSEMBLY,
            'starts_at' => now()->addWeek(),
            'ends_at' => null,
            'all_day' => false,
            'audience' => Event::AUDIENCE_ALL,
            'is_published' => true,
            'published_at' => now(),
        ], $overrides));
    }

    // ------------------------------------------------------------- visibility

    public function test_a_student_sees_everyone_and_student_events_but_not_teacher_ones(): void
    {
        $this->makeEvent(['title' => 'For All', 'audience' => Event::AUDIENCE_ALL]);
        $this->makeEvent(['title' => 'For Students', 'audience' => Event::AUDIENCE_STUDENTS]);
        $this->makeEvent(['title' => 'For Teachers', 'audience' => Event::AUDIENCE_TEACHERS]);

        $this->actAs('student@synapse.test');

        $titles = collect($this->getJson('/api/events')->json('data'))->pluck('title')->all();

        $this->assertContains('For All', $titles);
        $this->assertContains('For Students', $titles);
        $this->assertNotContains('For Teachers', $titles);
    }

    public function test_a_teacher_sees_everyone_and_teacher_events_but_not_student_ones(): void
    {
        $this->makeEvent(['title' => 'For All', 'audience' => Event::AUDIENCE_ALL]);
        $this->makeEvent(['title' => 'For Students', 'audience' => Event::AUDIENCE_STUDENTS]);
        $this->makeEvent(['title' => 'For Teachers', 'audience' => Event::AUDIENCE_TEACHERS]);

        $this->actAs('teacher@synapse.test');

        $titles = collect($this->getJson('/api/events')->json('data'))->pluck('title')->all();

        $this->assertContains('For All', $titles);
        $this->assertContains('For Teachers', $titles);
        $this->assertNotContains('For Students', $titles);
    }

    public function test_drafts_are_never_listed(): void
    {
        $this->makeEvent(['title' => 'Still Draft', 'is_published' => false, 'published_at' => null]);

        $this->actAs('student@synapse.test');
        $this->assertNotContains(
            'Still Draft',
            collect($this->getJson('/api/events')->json('data'))->pluck('title')->all(),
        );

        $this->actAs('teacher@synapse.test');
        $this->assertNotContains(
            'Still Draft',
            collect($this->getJson('/api/events')->json('data'))->pluck('title')->all(),
        );
    }

    public function test_reading_an_event_outside_your_audience_returns_not_found(): void
    {
        $event = $this->makeEvent(['audience' => Event::AUDIENCE_TEACHERS]);

        $this->actAs('student@synapse.test');

        // 403 would confirm the event exists.
        $this->getJson("/api/events/{$event->id}")->assertNotFound();
    }

    public function test_reading_a_draft_returns_not_found(): void
    {
        $event = $this->makeEvent(['is_published' => false, 'published_at' => null]);

        $this->actAs('student@synapse.test');

        $this->getJson("/api/events/{$event->id}")->assertNotFound();
    }

    public function test_an_event_from_another_school_is_not_found(): void
    {
        $event = $this->makeEvent();

        $this->actAs('teacher.saintalbert@synapse.test');

        $this->getJson("/api/events/{$event->id}")->assertNotFound();
        $this->assertNotContains(
            $event->title,
            collect($this->getJson('/api/events')->json('data'))->pluck('title')->all(),
        );
    }

    public function test_the_read_payload_does_not_disclose_the_author(): void
    {
        $event = $this->makeEvent();

        $this->actAs('student@synapse.test');

        $this->assertArrayNotHasKey('author', $this->getJson("/api/events/{$event->id}")->json('data'));
    }

    public function test_the_event_list_can_be_filtered_by_type(): void
    {
        $this->makeEvent(['title' => 'Sports Fixture', 'type' => Event::TYPE_SPORTS]);
        $this->makeEvent(['title' => 'Exam Fixture', 'type' => Event::TYPE_EXAM]);

        $this->actAs('student@synapse.test');

        $titles = collect($this->getJson('/api/events?type=sports')->json('data'))->pluck('title')->all();

        $this->assertContains('Sports Fixture', $titles);
        $this->assertNotContains('Exam Fixture', $titles);
    }

    public function test_an_unknown_type_filter_is_rejected(): void
    {
        $this->actAs('student@synapse.test');

        $this->getJson('/api/events?type=birthday')->assertStatus(422);
    }

    // ------------------------------------------------------------ authoring

    public function test_only_an_admin_can_create_an_event(): void
    {
        $this->actAs('student@synapse.test');
        $this->postJson('/api/admin/events', $this->validPayload())->assertStatus(403);

        $this->actAs('teacher@synapse.test');
        $this->postJson('/api/admin/events', $this->validPayload())->assertStatus(403);

        $this->assertSame(0, Event::withoutGlobalScopes()->where('title', 'like', 'E2E%')->count());
    }

    public function test_an_admin_creates_an_event_as_a_draft(): void
    {
        $this->actAs('admin@synapse.test');

        $response = $this->postJson('/api/admin/events', $this->validPayload(['title' => 'Founders Day']));

        $response->assertCreated();
        $this->assertFalse($response->json('data.is_published'));
        $this->assertNull($response->json('data.published_at'));
        $this->assertSame('Mrs. Chen', $response->json('data.author.name'));
    }

    public function test_an_event_cannot_end_before_it_starts(): void
    {
        $this->actAs('admin@synapse.test');

        $this->postJson('/api/admin/events', $this->validPayload([
            'starts_at' => now()->addWeek()->toIso8601String(),
            'ends_at' => now()->addDay()->toIso8601String(),
        ]))->assertStatus(422)->assertJsonValidationErrors('ends_at');
    }

    public function test_an_unknown_event_type_is_rejected(): void
    {
        $this->actAs('admin@synapse.test');

        $this->postJson('/api/admin/events', $this->validPayload(['type' => 'birthday']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_an_unknown_audience_is_rejected(): void
    {
        $this->actAs('admin@synapse.test');

        $this->postJson('/api/admin/events', $this->validPayload(['audience' => 'parents']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('audience');
    }

    public function test_an_admin_can_update_an_event(): void
    {
        $event = $this->makeEvent();

        $this->actAs('admin@synapse.test');

        $response = $this->putJson("/api/admin/events/{$event->id}", [
            'title' => 'Renamed Event',
            'location' => 'Hall B',
        ]);

        $response->assertOk();
        $this->assertSame('Renamed Event', $response->json('data.title'));
        $this->assertSame('Hall B', $response->json('data.location'));
    }

    public function test_an_update_cannot_invert_the_window(): void
    {
        $event = $this->makeEvent([
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(2),
        ]);

        $this->actAs('admin@synapse.test');

        $this->putJson("/api/admin/events/{$event->id}", [
            'ends_at' => now()->addDay()->toIso8601String(),
        ])->assertStatus(422);
    }

    // ----------------------------------------------------------- publishing

    public function test_publishing_stamps_the_event_and_notifies_the_whole_school(): void
    {
        $event = $this->makeEvent(['is_published' => false, 'published_at' => null, 'audience' => Event::AUDIENCE_ALL]);

        $this->actAs('admin@synapse.test');

        $response = $this->postJson("/api/admin/events/{$event->id}/publish");

        $response->assertOk();
        $this->assertTrue($response->json('data.is_published'));
        $this->assertNotNull($response->json('data.published_at'));

        $notified = Notification::where('type', 'event')->pluck('user_id')->all();

        $this->assertContains(User::where('email', 'student@synapse.test')->value('id'), $notified);
        $this->assertContains(User::where('email', 'teacher@synapse.test')->value('id'), $notified);
    }

    public function test_publishing_a_student_event_does_not_notify_teachers(): void
    {
        $event = $this->makeEvent([
            'is_published' => false,
            'published_at' => null,
            'audience' => Event::AUDIENCE_STUDENTS,
        ]);

        $this->actAs('admin@synapse.test');
        $this->postJson("/api/admin/events/{$event->id}/publish")->assertOk();

        $notified = Notification::where('type', 'event')->pluck('user_id')->all();

        $this->assertContains(User::where('email', 'student@synapse.test')->value('id'), $notified);
        $this->assertNotContains(User::where('email', 'teacher@synapse.test')->value('id'), $notified);
    }

    public function test_publishing_never_reaches_another_school(): void
    {
        $event = $this->makeEvent(['is_published' => false, 'published_at' => null]);

        $this->actAs('admin@synapse.test');
        $this->postJson("/api/admin/events/{$event->id}/publish")->assertOk();

        $this->assertSame(
            0,
            Notification::where('type', 'event')
                ->where('user_id', User::where('email', 'student.saintalbert@synapse.test')->value('id'))
                ->count(),
        );
    }

    public function test_unpublishing_hides_an_event_again(): void
    {
        $event = $this->makeEvent();

        $this->actAs('admin@synapse.test');
        $this->postJson("/api/admin/events/{$event->id}/unpublish")->assertOk();

        $this->actAs('student@synapse.test');
        $this->getJson("/api/events/{$event->id}")->assertNotFound();
    }

    public function test_the_admin_list_includes_drafts_and_the_author(): void
    {
        $this->makeEvent(['title' => 'Admin Draft', 'is_published' => false, 'published_at' => null]);

        $this->actAs('admin@synapse.test');

        $rows = $this->getJson('/api/admin/events')->json('data');

        $this->assertContains('Admin Draft', collect($rows)->pluck('title')->all());
        $this->assertNotNull(collect($rows)->firstWhere('title', 'Admin Draft')['author']['name'] ?? null);
    }

    public function test_the_admin_list_can_be_searched(): void
    {
        $this->makeEvent(['title' => 'Searchable Founders Day']);
        $this->makeEvent(['title' => 'Unrelated Fixture']);

        $this->actAs('admin@synapse.test');

        $titles = collect($this->getJson('/api/admin/events?search=Searchable')->json('data'))->pluck('title')->all();

        $this->assertContains('Searchable Founders Day', $titles);
        $this->assertNotContains('Unrelated Fixture', $titles);
    }

    public function test_an_admin_can_delete_an_event(): void
    {
        $event = $this->makeEvent();

        $this->actAs('admin@synapse.test');
        $this->deleteJson("/api/admin/events/{$event->id}")->assertOk();

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_an_admin_cannot_touch_another_schools_event(): void
    {
        $event = $this->makeEvent();

        $this->actAs('admin.saintalbert@synapse.test');

        $this->putJson("/api/admin/events/{$event->id}", ['title' => 'Hijacked'])->assertNotFound();
        $this->postJson("/api/admin/events/{$event->id}/publish")->assertNotFound();
        $this->deleteJson("/api/admin/events/{$event->id}")->assertNotFound();
    }

    public function test_a_guest_cannot_reach_events(): void
    {
        $this->getJson('/api/events')->assertUnauthorized();
        $this->postJson('/api/admin/events', $this->validPayload())->assertUnauthorized();
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'E2E Sports Day',
            'description' => 'Bring your running shoes.',
            'type' => Event::TYPE_SPORTS,
            'starts_at' => now()->addWeek()->toIso8601String(),
            'ends_at' => null,
            'all_day' => false,
            'location' => 'Sports Complex',
            'audience' => Event::AUDIENCE_ALL,
        ], $overrides);
    }
}
