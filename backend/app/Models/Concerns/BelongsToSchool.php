<?php

namespace App\Models\Concerns;

use App\Models\School;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
     * Drop the tenant scope entirely (platform-level queries).
     */
    public function scopeWithoutTenant(Builder $query): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }
}
