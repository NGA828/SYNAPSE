<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * The authenticated user's tenant context: school, branding, plan,
     * subscription status, feature flags and usage vs limits.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return response()->json([
                'school' => null,
                'plan' => null,
                'subscription' => null,
                'status' => 'platform',
                'features' => config('synapse.features'),
                'usage' => null,
            ]);
        }

        $school = $user->school;

        abort_unless($school, 403, 'No school is associated with your account.');

        return response()->json([
            'school' => $school,
            'plan' => $this->subscriptions->plan($school),
            'subscription' => $this->subscriptions->current($school),
            'status' => $this->subscriptions->status($school),
            'features' => $this->subscriptions->features($school),
            'usage' => $this->subscriptions->usage($school),
        ]);
    }
}
