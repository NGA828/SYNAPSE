<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * Override the default morphTo to drop the tenant scope.
     * Sanctum resolves the user from the token during authentication, before the
     * TenantContext has been resolved. If we don't drop the scope here, the
     * User model fails closed and the user cannot be authenticated.
     */
    public function tokenable()
    {
        return $this->belongsTo(User::class, 'tokenable_id')
            ->withoutGlobalScope(TenantScope::class);
    }
}
