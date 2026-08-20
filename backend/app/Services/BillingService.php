<?php

namespace App\Services;

use App\Models\School;
use App\Models\SubscriptionPlan;
use App\Models\User;

class BillingService
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly PaymentService $payments,
        private readonly AuditService $audit,
    ) {}

    /**
     * Billing dashboard payload for a school admin.
     *
     * @return array<string, mixed>
     */
    public function dashboard(School $school): array
    {
        $subscription = $this->subscriptions->current($school);

        return [
            'plan' => $this->subscriptions->plan($school),
            'subscription' => $subscription,
            'status' => $subscription?->status ?? 'none',
            'usage' => $this->subscriptions->usage($school),
            'features' => $this->subscriptions->features($school),
            'payments' => $this->payments->forSchool($school),
            'available_plans' => SubscriptionPlan::query()->where('status', 'active')->get(),
            'currency' => config('synapse.currency', 'XAF'),
        ];
    }

    /**
     * Upgrade (or change) the school's plan after a successful payment.
     *
     * @return array<string, mixed>
     */
    public function upgrade(
        School $school,
        SubscriptionPlan $plan,
        ?User $actor = null,
        ?string $provider = null,
        ?string $method = null,
    ): array {
        $payment = $this->payments->charge($school, $plan->price, $plan->currency, $provider, $method);
        $subscription = $this->subscriptions->changePlan($school, $plan);

        $this->audit->log($school, $actor, 'subscription.upgraded', SubscriptionPlan::class, $plan->id, [
            'payment' => $payment->reference,
        ]);

        return $this->dashboard($school);
    }

    /**
     * Renew the current plan after a successful payment.
     *
     * @return array<string, mixed>
     */
    public function renew(
        School $school,
        ?User $actor = null,
        ?string $provider = null,
        ?string $method = null,
    ): array {
        $plan = $this->subscriptions->plan($school);

        abort_unless($plan, 409, 'No plan is attached to this school.');

        $payment = $this->payments->charge($school, $plan->price, $plan->currency, $provider, $method);
        $this->subscriptions->renew($school);

        $this->audit->log($school, $actor, 'subscription.renewed', SubscriptionPlan::class, $plan->id, [
            'payment' => $payment->reference,
        ]);

        return $this->dashboard($school);
    }
}
