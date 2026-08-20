<?php

namespace App\Services;

use App\Models\School;
use App\Models\Subscription;
use App\Models\User;

class SchoolService
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    public function create(array $data, ?User $actor = null): School
    {
        $school = School::create($data);

        $this->audit->log($school, $actor, 'school.created', School::class, $school->id, [
            'name' => $school->name,
        ]);

        return $school;
    }

    public function update(School $school, array $data, ?User $actor = null): School
    {
        $school->update($data);

        $this->audit->log($school, $actor, 'school.updated', School::class, $school->id);

        return $school->fresh();
    }

    /**
     * Change a school's platform status without deleting its data. The
     * denormalised subscription status is kept in sync.
     */
    public function setStatus(School $school, string $status, ?User $actor = null): School
    {
        $school->update(['status' => $status]);

        $subscription = $school->subscriptions()->latest()->first();

        $mapped = match ($status) {
            School::STATUS_ACTIVE => Subscription::STATUS_ACTIVE,
            School::STATUS_TRIAL => Subscription::STATUS_TRIAL,
            School::STATUS_SUSPENDED => Subscription::STATUS_SUSPENDED,
            default => Subscription::STATUS_EXPIRED,
        };

        $subscription?->update(['status' => $mapped]);
        $school->update(['subscription_status' => $mapped]);

        $this->audit->log($school, $actor, 'school.status', School::class, $school->id, ['status' => $status]);

        return $school->fresh();
    }

    /**
     * Platform-wide statistics for the super admin dashboard.
     *
     * @return array<string, mixed>
     */
    public function platformStats(): array
    {
        $total = School::count();

        return [
            'schools' => [
                'total' => $total,
                'active' => School::where('status', School::STATUS_ACTIVE)->count(),
                'trial' => School::where('status', School::STATUS_TRIAL)->count(),
                'suspended' => School::where('status', School::STATUS_SUSPENDED)->count(),
                'expired' => School::where('status', School::STATUS_EXPIRED)->count(),
            ],
            'users' => [
                'total' => User::whereNot('role', User::ROLE_SUPER_ADMIN)->count(),
                'students' => User::where('role', User::ROLE_STUDENT)->count(),
                'teachers' => User::where('role', User::ROLE_TEACHER)->count(),
                'admins' => User::where('role', User::ROLE_ADMIN)->count(),
            ],
            'subscriptions' => [
                'active' => Subscription::where('status', Subscription::STATUS_ACTIVE)->count(),
                'trial' => Subscription::where('status', Subscription::STATUS_TRIAL)->count(),
                'expired' => Subscription::where('status', Subscription::STATUS_EXPIRED)->count(),
                'suspended' => Subscription::where('status', Subscription::STATUS_SUSPENDED)->count(),
            ],
            'revenue' => $this->revenue(),
        ];
    }

    /**
     * Recurring revenue estimate (active + trial subscriptions).
     */
    private function revenue(): array
    {
        $active = Subscription::query()->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIAL])->get();

        return [
            'mrr' => round((float) $active->sum('amount'), 2),
            'currency' => config('synapse.currency', 'XAF'),
        ];
    }

    /**
     * Users belonging to a school (for super admin inspection).
     */
    public function users(School $school)
    {
        return User::query()->forSchool($school)->get()->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ]);
    }
}
