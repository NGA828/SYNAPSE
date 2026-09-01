<?php

namespace App\Models\Concerns;

use App\Models\School;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Marks a model as tenant-owned and wires the tenant global scope.
 */
trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Query a specific school explicitly (bypasses the tenant scope).
     */
    public function scopeForSchool(Builder $query, School|int|null $school): Builder
    {
        $id = $school instanceof School ? $school->id : $school;

        return $query->withoutGlobalScope(TenantScope::class)
            ->where($this->qualifyColumn('school_id'), $id);
    }

    /**
     * Drop the tenant scope entirely.
     *
     * Restricted to platform-level code running outside a request lifecycle.
     * Both current callers are console commands (`synapse:prune-notifications`
     * and `synapse:generate-report-cards`), where no tenant exists to scope to.
     *
     * During an HTTP request this refuses rather than returning every school's
     * rows. A request already has a tenant — the `tenant` middleware resolved it
     * — so dropping the scope there is never the right answer, and a query that
     * reaches for this in a controller is a bug worth surfacing immediately.
     *
     * @throws RuntimeException
     */
    public function scopeWithoutTenant(Builder $query): Builder
    {
        if (! app()->runningInConsole()) {
            throw new RuntimeException(
                'withoutTenant() may only be used outside a request lifecycle. '.
                'Use forSchool() to query another school explicitly.',
            );
        }

        return $query->withoutGlobalScope(TenantScope::class);
    }
}
