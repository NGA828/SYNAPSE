<?php

namespace App\Models\Scopes;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope that restricts tenant-owned models to the current school.
 *
 * When no tenant is resolved (platform super admin, or public/onboarding
 * requests) the scope is a no-op so cross-tenant administration remains
 * possible through explicit `forSchool()` scoping.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $schoolId = TenantContext::id();

        if ($schoolId !== null) {
            $builder->where($model->qualifyColumn('school_id'), $schoolId);
        }
    }
}
