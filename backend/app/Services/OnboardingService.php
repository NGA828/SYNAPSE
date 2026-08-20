<?php

namespace App\Services;

use App\Models\School;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OnboardingService
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly AuditService $audit,
    ) {}

    /**
     * Register a school, its administrator and a free trial in one
     * transaction. Returns [School, User] on success.
     *
     * @param  array{school: array<string, mixed>, admin: array<string, mixed>, plan_id: int}  $data
     * @return array{school: School, admin: User}
     */
    public function register(array $data): array
    {
        $plan = SubscriptionPlan::query()
            ->where('id', $data['plan_id'])
            ->where('status', 'active')
            ->firstOrFail();

        [$school, $admin] = DB::transaction(function () use ($data, $plan) {
            $school = School::create([
                'name' => $data['school']['name'],
                'slug' => $data['school']['slug'],
                'email' => $data['school']['email'] ?? null,
                'phone' => $data['school']['phone'] ?? null,
                'address' => $data['school']['address'] ?? null,
                'status' => School::STATUS_TRIAL,
                'timezone' => $data['school']['timezone'] ?? 'Africa/Douala',
            ]);

            $admin = User::create([
                'school_id' => $school->id,
                'name' => $data['admin']['name'],
                'email' => $data['admin']['email'],
                'password' => $data['admin']['password'],
                'role' => User::ROLE_ADMIN,
            ]);

            $this->subscriptions->startTrial($school, $plan);

            $this->audit->log($school, $admin, 'school.onboarded', School::class, $school->id, [
                'plan' => $plan->slug,
            ]);

            return [$school, $admin];
        });

        return ['school' => $school, 'admin' => $admin];
    }
}
