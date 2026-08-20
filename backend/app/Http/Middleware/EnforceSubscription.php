<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks academic operations for schools whose subscription is not active
 * or in trial. Billing/dashboard endpoints intentionally bypass this so a
 * school admin can renew and regain access.
 */
class EnforceSubscription
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->isSuperAdmin()) {
            return $next($request);
        }

        $school = $user->school;

        abort_unless($school, 403, 'No school is associated with your account.');

        if (! $this->subscriptions->isActive($school)) {
            return response()->json([
                'message' => 'Your subscription has expired. Please renew your plan to continue using SYNAPSE.',
                'code' => 'subscription_required',
            ], 403);
        }

        return $next($request);
    }
}
