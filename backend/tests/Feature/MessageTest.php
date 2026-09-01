<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Direct messaging — who may contact whom, the ordering invariant that keeps
 * one pair on one thread, read receipts, and the tenant boundary.
 *
 * The student-to-student restriction is asserted here rather than left implicit,
 * because it is a safeguarding decision the product intends to keep.
 */
class MessageTest extends TestCase
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

    private function john(): User
    {
        return User::where('email', 'student@synapse.test')->firstOrFail();
    }

    private function mary(): User
    {
        return User::where('email', 'mary@synapse.test')->firstOrFail();
    }

    private function david(): User
    {
        return User::where('email', 'teacher@synapse.test')->firstOrFail();
    }

    private function chen(): User
    {
        return User::where('email', 'admin@synapse.test')->firstOrFail();
    }

    // ---------------------------------------------------------------- contacts

    public function test_a_student_may_only_contact_staff(): void
    {
        $this->actAs('student@synapse.test');

        $response = $this->getJson('/api/messages/recipients');

        $response->assertOk();

        $roles = collect($response->json('data'))->pluck('role')->unique()->all();

        $this->assertNotEmpty($roles);
        $this->assertEquals([], array_diff($roles, [User::ROLE_TEACHER, User::ROLE_ADMIN]));
    }

    public function test_a_student_is_not_offered_other_students(): void
    {
        $this->actAs('student@synapse.test');

        $ids = collect($this->getJson('/api/messages/recipients')->json('data'))->pluck('id')->all();

        $this->assertNotContains($this->mary()->id, $ids);
        $this->assertNotContains($this->john()->id, $ids, 'A user should not be offered themselves.');
    }

    public function test_the_recipient_list_excludes_the_platform_super_admin(): void
    {
        $this->actAs('student@synapse.test');

        $ids = collect($this->getJson('/api/messages/recipients')->json('data'))->pluck('id')->all();

        $this->assertNotContains(
            User::where('role', User::ROLE_SUPER_ADMIN)->value('id'),
            $ids,
        );
    }

    public function test_the_recipient_list_leaks_no_contact_details(): void
    {
        $this->actAs('student@synapse.test');

        $first = collect($this->getJson('/api/messages/recipients')->json('data'))->first();

        $this->assertArrayNotHasKey('email', $first);
        $this->assertEquals(['id', 'name', 'role'], array_keys($first));
    }

    public function test_a_teacher_may_contact_a_student(): void
    {
        $this->actAs('teacher@synapse.test');

        $ids = collect($this->getJson('/api/messages/recipients')->json('data'))->pluck('id')->all();

        $this->assertContains($this->john()->id, $ids);
    }

    // -------------------------------------------------------------- threads

    public function test_a_student_can_open_a_thread_with_a_teacher(): void
    {
        $this->actAs('student@synapse.test');

        $response = $this->postJson('/api/messages', ['user_id' => $this->david()->id]);

        $response->assertCreated();
        $this->assertSame('Mr. David', $response->json('data.participant.name'));
    }

    public function test_the_pair_is_stored_ordered_so_both_directions_share_one_thread(): void
    {
        $this->actAs('student@synapse.test');
        $this->postJson('/api/messages', ['user_id' => $this->david()->id])->assertCreated();

        $conversation = Conversation::firstOrFail();

        $this->assertLessThan($conversation->participant_b_id, $conversation->participant_a_id);

        // The same pair from the other side resolves to the same row.
        $this->actAs('teacher@synapse.test');
        $again = $this->postJson('/api/messages', ['user_id' => $this->john()->id]);

        $again->assertCreated();
        $this->assertSame($conversation->id, $again->json('data.id'));
        $this->assertSame(1, Conversation::count());
    }

    public function test_a_student_cannot_open_a_thread_with_another_student(): void
    {
        $this->actAs('student@synapse.test');

        $this->postJson('/api/messages', ['user_id' => $this->mary()->id])
            ->assertStatus(403);

        $this->assertSame(0, Conversation::count());
    }

    public function test_a_user_cannot_open_a_thread_with_themselves(): void
    {
        $this->actAs('student@synapse.test');

        $this->postJson('/api/messages', ['user_id' => $this->john()->id])
            ->assertStatus(422);
    }

    public function test_a_user_cannot_open_a_thread_across_schools(): void
    {
        $this->actAs('student@synapse.test');

        $this->postJson('/api/messages', [
            'user_id' => User::where('email', 'teacher.saintalbert@synapse.test')->value('id'),
        ])->assertStatus(403);
    }

    // ------------------------------------------------------------- messages

    private function thread(): Conversation
    {
        $this->actAs('student@synapse.test');

        return Conversation::find(
            $this->postJson('/api/messages', ['user_id' => $this->david()->id])->json('data.id'),
        );
    }

    public function test_a_message_can_be_sent_and_is_delivered_to_the_recipient(): void
    {
        $conversation = $this->thread();

        $response = $this->postJson("/api/messages/{$conversation->id}", [
            'body' => 'When is the essay due?',
        ]);

        $response->assertCreated();
        $this->assertSame('When is the essay due?', $response->json('data.body'));
        $this->assertTrue($response->json('data.is_own'));
        $this->assertNull($response->json('data.read_at'));

        $this->assertNotNull($conversation->fresh()->last_message_at);

        $this->assertSame(
            1,
            Notification::where('user_id', $this->david()->id)
                ->where('type', 'message')
                ->count(),
        );
    }

    public function test_an_empty_message_is_rejected(): void
    {
        $conversation = $this->thread();

        $this->postJson("/api/messages/{$conversation->id}", ['body' => '   '])
            ->assertStatus(422);
    }

    public function test_an_oversized_message_is_rejected(): void
    {
        $conversation = $this->thread();

        $this->postJson("/api/messages/{$conversation->id}", ['body' => str_repeat('a', 2001)])
            ->assertStatus(422);
    }

    public function test_a_thread_is_returned_in_chronological_order(): void
    {
        $conversation = $this->thread();

        $this->postJson("/api/messages/{$conversation->id}", ['body' => 'first']);

        $this->actAs('teacher@synapse.test');
        $this->postJson("/api/messages/{$conversation->id}", ['body' => 'second']);

        $body = $this->getJson("/api/messages/{$conversation->id}")->json('data');

        $this->assertSame(['first', 'second'], array_column($body, 'body'));
    }

    public function test_opening_a_thread_marks_the_other_participants_messages_read(): void
    {
        $conversation = $this->thread();
        $this->postJson("/api/messages/{$conversation->id}", ['body' => 'hello']);

        $this->actAs('teacher@synapse.test');

        $this->assertSame(1, $this->getJson('/api/messages/unread')->json('unread'));

        $this->getJson("/api/messages/{$conversation->id}")->assertOk();

        $this->assertSame(0, $this->getJson('/api/messages/unread')->json('unread'));
        $this->assertNotNull(Message::where('conversation_id', $conversation->id)->value('read_at'));
    }

    public function test_reading_a_thread_does_not_mark_your_own_messages_read(): void
    {
        $conversation = $this->thread();
        $this->postJson("/api/messages/{$conversation->id}", ['body' => 'hello']);

        $this->getJson("/api/messages/{$conversation->id}")->assertOk();

        $this->assertNull(Message::where('conversation_id', $conversation->id)->value('read_at'));
    }

    public function test_a_non_participant_cannot_read_or_write_a_thread(): void
    {
        $conversation = $this->thread();

        $this->actAs('sarah@synapse.test');

        $this->getJson("/api/messages/{$conversation->id}")->assertStatus(403);
        $this->postJson("/api/messages/{$conversation->id}", ['body' => 'intruding'])->assertStatus(403);
    }

    public function test_a_conversation_from_another_school_is_not_found_rather_than_forbidden(): void
    {
        $conversation = $this->thread();

        // A 403 here would confirm the record exists to another tenant.
        $this->actAs('teacher.saintalbert@synapse.test');

        $this->getJson("/api/messages/{$conversation->id}")->assertNotFound();
    }

    public function test_conversations_are_listed_newest_first_with_the_other_participant(): void
    {
        $this->actAs('student@synapse.test');

        $withDavid = $this->postJson('/api/messages', ['user_id' => $this->david()->id])->json('data.id');
        $withChen = $this->postJson('/api/messages', ['user_id' => $this->chen()->id])->json('data.id');

        $this->postJson("/api/messages/{$withDavid}", ['body' => 'older']);
        $this->postJson("/api/messages/{$withChen}", ['body' => 'newer']);

        // Set the ordering rather than sleeping on the clock: both messages can
        // legitimately land in the same second.
        Conversation::find($withChen)->update(['last_message_at' => now()->addMinute()]);

        $rows = $this->getJson('/api/messages')->json('data');

        $this->assertSame($withChen, $rows[0]['id']);
        $this->assertSame('Mrs. Chen', $rows[0]['participant']['name']);
    }

    public function test_the_unread_count_covers_every_conversation(): void
    {
        $this->actAs('student@synapse.test');
        $withChen = $this->postJson('/api/messages', ['user_id' => $this->chen()->id])->json('data.id');
        $this->postJson("/api/messages/{$withChen}", ['body' => 'question']);

        $this->actAs('admin@synapse.test');

        $this->assertSame(1, $this->getJson('/api/messages/unread')->json('unread'));
    }

    public function test_a_guest_cannot_reach_messaging_at_all(): void
    {
        $this->getJson('/api/messages')->assertUnauthorized();
        $this->postJson('/api/messages', ['user_id' => 1])->assertUnauthorized();
    }
}
