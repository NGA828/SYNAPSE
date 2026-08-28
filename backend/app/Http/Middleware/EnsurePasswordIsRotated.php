<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks provisioned accounts that still carry their one-time password.
 *
 * The client is expected to redirect to the "choose a new password" screen on
 * seeing the `password_change_required` code. Only logout, profile reads and
 * the change-password endpoint itself stay reachable.
 */
class EnsurePasswordIsRotated
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->must_change_password) {
            return response()->json([
                'message' => 'You must choose a new password before continuing.',
                'code' => 'password_change_required',
            ], 403);
        }

        return $next($request);
    }
}
