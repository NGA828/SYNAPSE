<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
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

    public function test_school_a_admin_cannot_see_school_b_students(): void
    {
        $this->actAs('admin@synapse.test');

        $this->getJson('/api/admin/students')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Mary Bih'])
            ->assertJsonFragment(['name' => 'John Doe']);
    }

    public function test_school_a_admin_cannot_see_school_b_teachers(): void
    {
        $this->actAs('admin@synapse.test');

        $this->getJson('/api/admin/teachers')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Mr. Emeka'])
            ->assertJsonFragment(['name' => 'Mr. David']);
    }

    public function test_school_a_teacher_cannot_access_school_b_class(): void
    {
        $this->actAs('teacher@synapse.test');

        $schoolBClass = SchoolClass::query()->forSchool(
            School::where('slug', 'saintalbert')->firstOrFail()
        )->firstOrFail();

        // Cross-tenant class id resolves to 404 because of the tenant scope.
        $this->getJson("/api/teacher/classes/{$schoolBClass->id}/subjects/1/gradebook")
            ->assertNotFound();
    }

    public function test_teacher_cannot_access_unauthorized_subject(): void
    {
        $this->actAs('teacher@synapse.test');

        $school = School::where('slug', 'aics')->firstOrFail();
        $class = SchoolClass::query()->forSchool($school)->where('name', 'Level 3A')->firstOrFail();
        $math = Subject::query()->forSchool($school)->where('name', 'Mathematics')->firstOrFail();

        // Mr. David teaches English in 3A, not Mathematics.
        $this->getJson("/api/teacher/classes/{$class->id}/subjects/{$math->id}/gradebook")
            ->assertForbidden();
    }

    public function test_teacher_cannot_modify_another_teachers_grades(): void
    {
        $this->actAs('teacher@synapse.test');

        $school = School::where('slug', 'aics')->firstOrFail();
        $class = SchoolClass::query()->forSchool($school)->where('name', 'Level 3A')->firstOrFail();
        $math = Subject::query()->forSchool($school)->where('name', 'Mathematics')->firstOrFail();
        $john = Student::query()->forSchool($school)->where('matricule', 'ST2026045')->firstOrFail();

        $this->postJson("/api/teacher/classes/{$class->id}/subjects/{$math->id}/grades", [
            'grades' => [['student_id' => $john->id, 'test1' => 20]],
        ])->assertForbidden();
    }

    public function test_student_cannot_access_cross_school_data(): void
    {
        $this->actAs('student@synapse.test');

        // John (AICS) must not see Saint Albert's announcements.
        $this->getJson('/api/announcements')
            ->assertOk()
            ->assertJsonMissing(['title' => 'Welcome to Saint Albert']);
    }

    public function test_student_cannot_download_another_schools_document(): void
    {
        $this->actAs('student@synapse.test');

        $schoolB = School::where('slug', 'saintalbert')->firstOrFail();
        $foreign = Document::create([
            'school_id' => $schoolB->id,
            'request_id' => null,
            'student_id' => null,
            'title' => 'Foreign doc',
            'file_name' => 'foreign.pdf',
            'mime_type' => 'application/pdf',
            'size' => 0,
            'disk' => 'public',
            'path' => 'documents/foreign.pdf',
        ]);

        $this->getJson("/api/student/documents/{$foreign->id}/download")
            ->assertNotFound();
    }

    public function test_expired_school_is_blocked_from_academics_but_can_see_billing(): void
    {
        $this->actAs('admin.demo@synapse.test');

        $this->getJson('/api/admin/students')
            ->assertForbidden()
            ->assertJsonPath('code', 'subscription_required');

        $this->getJson('/api/admin/billing')->assertOk();
    }

    public function test_super_admin_can_manage_schools(): void
    {
        $this->actAs('superadmin@synapse.test');

        $this->getJson('/api/super-admin/schools')->assertOk();

        $this->postJson('/api/super-admin/schools', [
            'name' => 'New Academy',
            'slug' => 'new-academy',
        ])->assertCreated();
    }

    public function test_plan_student_limit_is_enforced(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Tiny',
            'slug' => 'tiny',
            'price' => 0,
            'billing_interval' => 'monthly',
            'currency' => 'XAF',
            'max_students' => 1,
            'features' => ['basic_academics'],
        ]);

        $this->postJson('/api/onboarding/schools', [
            'school' => ['name' => 'Tiny School', 'slug' => 'tiny-school'],
            'admin' => ['name' => 'Tiny Admin', 'email' => 'tiny@synapse.test', 'password' => 'password123'],
            'plan_id' => $plan->id,
        ])->assertCreated();

        $this->actAs('tiny@synapse.test');

        $school = School::where('slug', 'tiny-school')->firstOrFail();
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Grade 1']);

        $this->postJson('/api/admin/students', [
            'name' => 'First Student', 'email' => 'first@tiny.test', 'password' => 'password123',
            'matricule' => 'T-001', 'class_id' => $class->id,
        ])->assertCreated();

        $this->postJson('/api/admin/students', [
            'name' => 'Second Student', 'email' => 'second@tiny.test', 'password' => 'password123',
            'matricule' => 'T-002', 'class_id' => $class->id,
        ])->assertStatus(422);
    }

    public function test_onboarding_starts_a_trial(): void
    {
        $plan = SubscriptionPlan::where('slug', 'starter')->firstOrFail();

        $this->postJson('/api/onboarding/schools', [
            'school' => ['name' => 'Trial School', 'slug' => 'trial-school'],
            'admin' => ['name' => 'Trial Admin', 'email' => 'trial-admin@synapse.test', 'password' => 'password123'],
            'plan_id' => $plan->id,
        ])->assertCreated();

        $this->actAs('trial-admin@synapse.test');

        $this->getJson('/api/tenant')
            ->assertOk()
            ->assertJsonPath('status', 'trial');
    }
}
