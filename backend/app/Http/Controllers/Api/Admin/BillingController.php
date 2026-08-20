<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct(
        private readonly BillingService $billing,
    ) {}

    /**
     * Billing dashboard: plan, status, usage vs limits, payment history.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->billing->dashboard($request->user()->school));
    }

    /**
     * Upgrade/change plan after a (mock) payment.
     */
    public function upgrade(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'provider' => ['nullable', 'string'],
            'method' => ['nullable', 'string'],
        ]);

        $plan = SubscriptionPlan::findOrFail($data['plan_id']);

        return response()->json(
            $this->billing->upgrade(
                $request->user()->school,
                $plan,
                $request->user(),
                $data['provider'] ?? null,
                $data['method'] ?? null,
            ),
        );
    }

    /**
     * Renew the current plan after a (mock) payment.
     */
    public function renew(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['nullable', 'string'],
            'method' => ['nullable', 'string'],
        ]);

        return response()->json(
            $this->billing->renew(
                $request->user()->school,
                $request->user(),
                $data['provider'] ?? null,
                $data['method'] ?? null,
            ),
        );
    }
}
