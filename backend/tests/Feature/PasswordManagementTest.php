<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PasswordManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_forgot_password_sends_a_reset_notification(): void
    {
        Notification::fake();

        $this->postJson('/api/forgot-password', ['email' => 'admin@synapse.test'])
            ->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertSentTo(
            User::where('email', 'admin@synapse.test')->first(),
            ResetPasswordNotification::class,
        );
    }

    public function test_forgot_password_does_not_leak_whether_an_account_exists(): void
    {
        Notification::fake();

        $known = $this->postJson('/api/forgot-password', ['email' => 'admin@synapse.test']);
        $unknown = $this->postJson('/api/forgot-password', ['email' => 'nobody@nowhere.test']);

        $known->assertOk();
        $unknown->assertOk();
        $this->assertSame($known->json('message'), $unknown->json('message'));
    }

    public function test_a_valid_token_resets_the_password_and_revokes_tokens(): void
    {
        $user = User::where('email', 'student@synapse.test')->firstOrFail();
        $user->createToken('old-device');

        $token = app('auth.password.broker')->createToken($user);

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-secret-99',
            'password_confirmation' => 'new-secret-99',
        ])->assertOk();

        $user->refresh();

        $this->assertTrue(Hash::check('new-secret-99', $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        $this->postJson('/api/reset-password', [
            'email' => 'student@synapse.test',
            'token' => 'not-a-real-token',
            'password' => 'new-secret-99',
            'password_confirmation' => 'new-secret-99',
        ])->assertStatus(422);
    }

    public function test_changing_the_password_requires_the_current_one(): void
    {
        $user = User::where('email', 'teacher@synapse.test')->firstOrFail();
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/password', [
            'current_password' => 'wrong-password',
            'password' => 'another-secret-1',
            'password_confirmation' => 'another-secret-1',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');
    }

    public function test_a_user_can_change_their_password(): void
    {
        $user = User::where('email', 'teacher@synapse.test')->firstOrFail();
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/password', [
            'current_password' => 'password123',
            'password' => 'another-secret-1',
            'password_confirmation' => 'another-secret-1',
        ])->assertOk();

        $this->assertTrue(Hash::check('another-secret-1', $user->fresh()->password));
    }

    public function test_an_account_with_a_temporary_password_is_locked_out_until_it_rotates(): void
    {
        $user = User::where('email', 'student@synapse.test')->firstOrFail();
        $user->forceFill(['must_change_password' => true])->save();

        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/student/dashboard')
            ->assertStatus(403)
            ->assertJsonPath('code', 'password_change_required');

        // …but the change-password endpoint itself stays reachable.
        $this->postJson('/api/password', [
            'current_password' => 'password123',
            'password' => 'rotated-secret-7',
            'password_confirmation' => 'rotated-secret-7',
        ])->assertOk();

        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_profile_updates_persist_notification_preferences(): void
    {
        $user = User::where('email', 'teacher@synapse.test')->firstOrFail();
        Sanctum::actingAs($user, ['*']);

        $this->patchJson('/api/profile', [
            'phone' => '+237600000123',
            'notify_sms' => true,
            'locale' => 'fr',
        ])->assertOk();

        $user->refresh();

        $this->assertSame('+237600000123', $user->phone);
        $this->assertTrue($user->notify_sms);
        $this->assertSame('fr', $user->locale);
    }
}
