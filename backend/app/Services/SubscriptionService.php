<?php

namespace App\Services;

use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Teacher;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    /**
     * The school's current plan (from the denormalised snapshot).
     */
    public function plan(School $school): ?SubscriptionPlan
    {
        return $school->subscriptionPlan
            ?? $school->subscriptions()->latest()->first()?->plan;
    }

    /**
     * The school's most recent subscription, refreshing trial expiry lazily.
     */
    public function current(School $school): ?Subscription
    {
        $subscription = $school->subscriptions()->latest()->first();

        if ($subscription && $subscription->status === Subscription::STATUS_TRIAL
            && $subscription->end_date?->isPast()) {
            $subscription->update(['status' => Subscription::STATUS_EXPIRED]);
            $this->syncSchool($school, $subscription);
        }

        return $subscription?->fresh();
    }

    /**
     * Whether the school may use the platform.
     */
    public function isActive(School $school): bool
    {
        $subscription = $this->current($school);

        return $subscription !== null && $subscription->isActive();
    }

    public function status(School $school): string
    {
        return $this->current($school)?->status ?? 'none';
    }

    /**
     * Feature flags available to the school (from its plan).
     *
     * @return list<string>
     */
    public function features(School $school): array
    {
        return $this->plan($school)?->features ?? [];
    }

    public function hasFeature(School $school, string $feature): bool
    {
        return $this->plan($school)?->hasFeature($feature) ?? false;
    }

    /**
     * Usage snapshot vs plan limits.
     *
     * @return array<string, mixed>
     */
    public function usage(School $school): array
    {
        $plan = $this->plan($school);

        return [
            'students' => Student::query()->forSchool($school)->count(),
            'teachers' => Teacher::query()->forSchool($school)->count(),
            'classes' => SchoolClass::query()->forSchool($school)->count(),
            'limits' => [
                'students' => $plan?->max_students,
                'teachers' => $plan?->max_teachers,
                'classes' => $plan?->max_classes,
            ],
        ];
    }

    /**
     * Throw when a plan limit would be exceeded by creating one more record.
     *
     * @param  'students'|'teachers'|'classes'  $entity
     */
    public function assertCanCreate(School $school, string $entity): void
    {
        $usage = $this->usage($school);
        $limit = $usage['limits'][$entity] ?? null;

        if ($limit === null) {
            return;
        }

        if ($usage[$entity] >= $limit) {
            throw ValidationException::withMessages([
                'subscription' => [
                    "You have reached the {$entity} limit of your current plan. Upgrade your subscription to add more.",
                ],
            ]);
        }
    }

    /**
     * Start a free trial for a newly onboarded school.
     */
    public function startTrial(School $school, SubscriptionPlan $plan): Subscription
    {
        $subscription = Subscription::create([
            'school_id' => $school->id,
            'plan_id' => $plan->id,
            'status' => Subscription::STATUS_TRIAL,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays((int) config('synapse.trial_days', 14))->toDateString(),
            'billing_interval' => $plan->billing_interval,
            'amount' => $plan->price,
            'currency' => $plan->currency,
        ]);

        $this->syncSchool($school, $subscription);

        return $subscription;
    }

    /**
     * Activate (or extend) a subscription after a successful payment.
     */
    public function subscribe(School $school, SubscriptionPlan $plan, ?Subscription $previous = null): Subscription
    {
        $start = $previous?->end_date?->isFuture() ? $previous->end_date : now();

        $subscription = Subscription::create([
            'school_id' => $school->id,
            'plan_id' => $plan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addMonth()->toDateString(),
            'billing_interval' => $plan->billing_interval,
            'amount' => $plan->price,
            'currency' => $plan->currency,
        ]);

        $this->syncSchool($school, $subscription);

        return $subscription;
    }

    /**
     * Renew the current plan for one more billing interval.
     */
    public function renew(School $school): Subscription
    {
        $plan = $this->plan($school);

        abort_unless($plan, 409, 'No plan is attached to this school.');

        return $this->subscribe($school, $plan, $this->current($school));
    }

    /**
     * Switch to a different plan.
     */
    public function changePlan(School $school, SubscriptionPlan $plan): Subscription
    {
        return $this->subscribe($school, $plan, $this->current($school));
    }

    /**
     * Suspend or cancel a subscription without deleting data.
     */
    public function setStatus(School $school, string $status): Subscription
    {
        $subscription = $this->current($school);

        abort_unless($subscription, 409, 'No subscription found for this school.');

        $subscription->update(['status' => $status]);
        $this->syncSchool($school, $subscription);

        return $subscription;
    }

    /**
     * Mirror the active subscription onto the denormalised school snapshot.
     */
    private function syncSchool(School $school, Subscription $subscription): void
    {
        $school->update([
            'subscription_plan_id' => $subscription->plan_id,
            'subscription_status' => $subscription->status,
            'subscription_started_at' => $subscription->start_date,
            'subscription_expires_at' => $subscription->end_date,
            'status' => match ($subscription->status) {
                Subscription::STATUS_TRIAL => School::STATUS_TRIAL,
                Subscription::STATUS_ACTIVE => School::STATUS_ACTIVE,
                Subscription::STATUS_SUSPENDED => School::STATUS_SUSPENDED,
                default => School::STATUS_EXPIRED,
            },
        ]);
    }
}
