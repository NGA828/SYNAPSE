<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate an endpoint behind a subscription-plan feature flag.
 *
 * Usage: ->middleware('feature:report_cards')
 */
class EnsureFeature
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->isSuperAdmin()) {
            return $next($request);
        }

        $school = $user->school;

        abort_unless($school, 403, 'No school is associated with your account.');

        abort_unless(
            $this->subscriptions->hasFeature($school, $feature),
            403,
            'This feature is not available on your current plan.',
        );

        return $next($request);
    }
}
