<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use App\Notifications\RequestSubmittedNotification;
use App\Notifications\TemporaryCredentialsNotification;
use App\Services\Sms\SmsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_creating_a_student_sends_one_time_credentials(): void
    {
        Notification::fake();

        Sanctum::actingAs(User::where('email', 'admin@synapse.test')->firstOrFail(), ['*']);

        $this->postJson('/api/admin/students', [
            'name' => 'New Student',
            'email' => 'new.student@synapse.test',
            'matricule' => 'STU-9999',
            'class_id' => \App\Models\SchoolClass::query()->first()->id,
        ])->assertCreated();

        $created = User::where('email', 'new.student@synapse.test')->firstOrFail();

        $this->assertTrue($created->must_change_password, 'A provisioned account must rotate its password.');

        Notification::assertSentTo($created, TemporaryCredentialsNotification::class);
    }

    public function test_no_account_is_ever_created_with_a_shared_default_password(): void
    {
        Sanctum::actingAs(User::where('email', 'admin@synapse.test')->firstOrFail(), ['*']);

        $this->postJson('/api/admin/students', [
            'name' => 'Import Sample',
            'email' => 'import.sample@synapse.test',
            'matricule' => 'STU-8888',
            'class_id' => \App\Models\SchoolClass::query()->first()->id,
        ])->assertCreated();

        $this->postJson('/api/login', [
            'email' => 'import.sample@synapse.test',
            'password' => 'password123',
        ])->assertStatus(422);
    }

    public function test_a_request_notifies_only_the_administrators_of_its_own_school(): void
    {
        Notification::fake();

        Sanctum::actingAs(User::where('email', 'student@synapse.test')->firstOrFail(), ['*']);

        $this->postJson('/api/student/requests', [
            'type' => 'Certificate of Enrollment',
            'reason' => 'Visa application',
        ])->assertCreated();

        $ownAdmin = User::where('email', 'admin@synapse.test')->firstOrFail();
        $otherAdmin = User::where('email', 'admin.saintalbert@synapse.test')->first();

        Notification::assertSentTo($ownAdmin, RequestSubmittedNotification::class);

        if ($otherAdmin) {
            Notification::assertNotSentTo($otherAdmin, RequestSubmittedNotification::class);
        }
    }

    public function test_notifications_are_queued_rather_than_sent_inline(): void
    {
        $this->assertInstanceOf(
            \Illuminate\Contracts\Queue\ShouldQueue::class,
            new TemporaryCredentialsNotification('temp-pass', 'Demo School'),
        );
    }

    public function test_local_phone_numbers_are_normalised_to_e164(): void
    {
        $sms = app(SmsManager::class);

        $this->assertSame('+237677123456', $sms->normalise('677123456'));
        $this->assertSame('+237677123456', $sms->normalise('0677123456'));
        $this->assertSame('+237677123456', $sms->normalise('237677123456'));
        $this->assertSame('+237677123456', $sms->normalise('+237 677 12 34 56'));
        $this->assertNull($sms->normalise(''));
    }

    public function test_the_notification_feed_is_paginated(): void
    {
        Sanctum::actingAs(User::where('email', 'student@synapse.test')->firstOrFail(), ['*']);

        $this->getJson('/api/notifications?per_page=5')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['total'], 'unread_count']);
    }

    public function test_school_admins_can_read_their_own_audit_trail_only(): void
    {
        Sanctum::actingAs(User::where('email', 'admin@synapse.test')->firstOrFail(), ['*']);

        $response = $this->getJson('/api/admin/audit-logs')->assertOk();

        $schoolId = School::where('slug', 'aics')->value('id');

        foreach ($response->json('data') as $entry) {
            $this->assertSame($schoolId, $entry['school']['id'] ?? $schoolId);
        }
    }
}
