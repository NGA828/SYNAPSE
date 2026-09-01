<?php

namespace App\Models\Scopes;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope that restricts tenant-owned models to the current school.
 *
 * There are three states, and conflating the last two is what made this scope
 * fail open. A tenant is resolved to a school, resolved to nothing (the
 * platform super admin, who acts across schools deliberately), or never
 * resolved at all.
 *
 * The old version treated "resolved to nothing" and "never resolved"
 * identically — both fell through to no constraint, returning every school's
 * rows. That is safe today only because all 150 authenticated routes happen to
 * apply the `tenant` middleware; the next route registered without it would
 * silently serve cross-tenant data instead of failing. So an unresolved context
 * now fails **closed** during an HTTP request.
 *
 * Console is deliberately exempt. The seeder creates every school with no
 * tenant resolved, and `synapse:*` commands and queued jobs run outside a
 * request lifecycle; failing closed there would break them rather than protect
 * anything. Those paths use `forSchool()` or `withoutTenant()` explicitly.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if (! $context->isResolved()) {
            if (! app()->runningInConsole()) {
                // Nothing to scope to, so match nothing. A row whose school_id
                // is null cannot belong to any tenant.
                $builder->whereNull($model->qualifyColumn('school_id'));
            }

            return;
        }

        $schoolId = $context->schoolId();

        if ($schoolId !== null) {
            $builder->where($model->qualifyColumn('school_id'), $schoolId);
        }
    }
}
