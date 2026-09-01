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

    /*
    | A null school means two very different things, and conflating them is what
    | made TenantScope fail open. Either a tenant was resolved and there isn't
    | one — the platform super admin, who acts across schools deliberately — or
    | nobody ever resolved one, which in an HTTP request means a route was
    | registered without the `tenant` middleware.
    */
    private bool $resolved = false;

    public function set(?School $school): void
    {
        $this->school = $school;
        $this->resolved = true;
    }

    /**
     * Whether a tenant has been resolved for this lifecycle, as opposed to no
     * tenant having been set at all.
     */
    public function isResolved(): bool
    {
        return $this->resolved;
    }

    /**
     * Clear the context, returning it to "never resolved". Used between
     * requests by long-running workers, and by tests.
     */
    public function forget(): void
    {
        $this->school = null;
        $this->resolved = false;
    }

    public function school(): ?School
    {
        return $this->school;
    }

    public function schoolId(): ?int
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
        $previousResolved = $this->resolved;
        $this->set($school);

        try {
            return $callback();
        } finally {
            $this->school = $previous;
            $this->resolved = $previousResolved;
        }
    }

    public static function current(): ?School
    {
        return app(self::class)->school();
    }

    public static function id(): ?int
    {
        return app(self::class)->schoolId();
    }

    public static function isResolved(): bool
    {
        return app(self::class)->isResolved();
    }
}
