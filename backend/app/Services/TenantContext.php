<?php

namespace App\Services;

use App\Models\School;
use Closure;

/**
 * Holds the school/tenant resolved for the current request.
 *
 * Set by the `tenant` (IdentifyTenant) middleware for authenticated school
 * users; left null for the platform super admin, whose queries intentionally
 * span tenants.
 */
class TenantContext
{
    private ?School $school = null;

    public function set(?School $school): void
    {
        $this->school = $school;
    }

    public function school(): ?School
    {
        return $this->school;
    }

    public function id(): ?int
    {
        return $this->school?->id;
    }

    /**
     * Run a callback within a given school context, restoring the previous
     * context afterwards. Used by the super admin to act inside one school.
     */
    public function run(?School $school, Closure $callback): mixed
    {
        $previous = $this->school;
        $this->set($school);

        try {
            return $callback();
        } finally {
            $this->set($previous);
        }
    }

    public static function current(): ?School
    {
        return app(self::class)->school();
    }

    public static function id(): ?int
    {
        return app(self::class)->id();
    }
}
