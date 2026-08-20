<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant (school) for the request and stores it in the
 * TenantContext so every tenant-owned query is automatically scoped.
 *
 * The school is derived from the AUTHENTICATED user — never from the
 * frontend. Platform super admins run with no tenant (cross-tenant access).
 */
class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $context = app(TenantContext::class);

        if ($user instanceof User && ! $user->isSuperAdmin()) {
            abort_unless($user->school_id, 403, 'No school is associated with your account.');
            $context->set($user->school);
        } else {
            $context->set(null);
        }

        return $next($request);
    }
}
