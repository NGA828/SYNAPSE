<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SubscriptionSweepTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function schoolWithSubscription(string $status, string $endDate): Subscription
    {
        $school = School::where('slug', 'aics')->firstOrFail();

        return Subscription::query()->updateOrCreate(
            ['school_id' => $school->id],
            [
                'plan_id' => $school->subscription_plan_id,
                'status' => $status,
                'start_date' => now()->subMonth(),
                'end_date' => $endDate,
                'amount' => 25000,
                'currency' => 'XAF',
            ],
        );
    }

    public function test_a_lapsed_subscription_is_expired_and_the_admin_is_notified(): void
    {
        Notification::fake();

        $subscription = $this->schoolWithSubscription('active', now()->subDay()->toDateString());

        $this->artisan('synapse:sweep-subscriptions')->assertSuccessful();

        $this->assertSame(Subscription::STATUS_EXPIRED, $subscription->fresh()->status);

        Notification::assertSentTo(
            User::where('email', 'admin@synapse.test')->firstOrFail(),
            SubscriptionReminderNotification::class,
        );
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $subscription = $this->schoolWithSubscription('active', now()->subDay()->toDateString());

        $this->artisan('synapse:sweep-subscriptions --dry-run')->assertSuccessful();

        $this->assertSame('active', $subscription->fresh()->status);
    }

    public function test_a_school_close_to_expiry_gets_a_reminder_but_stays_active(): void
    {
        Notification::fake();

        $subscription = $this->schoolWithSubscription('active', now()->addDays(7)->toDateString());

        $this->artisan('synapse:sweep-subscriptions')->assertSuccessful();

        $this->assertSame('active', $subscription->fresh()->status);

        Notification::assertSentTo(
            User::where('email', 'admin@synapse.test')->firstOrFail(),
            SubscriptionReminderNotification::class,
        );
    }

    public function test_read_notifications_are_pruned_and_unread_ones_are_kept(): void
    {
        $user = User::where('email', 'student@synapse.test')->firstOrFail();

        $old = \App\Models\Notification::create([
            'school_id' => $user->school_id,
            'user_id' => $user->id,
            'type' => 'general',
            'title' => 'Old and read',
            'read_at' => now()->subYear(),
        ]);
        $old->forceFill(['created_at' => now()->subYear()])->save();

        $unread = \App\Models\Notification::create([
            'school_id' => $user->school_id,
            'user_id' => $user->id,
            'type' => 'general',
            'title' => 'Old but unread',
        ]);
        $unread->forceFill(['created_at' => now()->subYear()])->save();

        $this->artisan('synapse:prune-notifications --days=30')->assertSuccessful();

        $this->assertNull(\App\Models\Notification::withoutTenant()->find($old->id));
        $this->assertNotNull(\App\Models\Notification::withoutTenant()->find($unread->id));
    }
}
