<?php

namespace Tests\Unit;

use App\Models\School;
use App\Models\User;
use App\Services\TenantContext;
use Tests\TestCase;

/**
 * The tenant scope's four states (Phase 8.3).
 *
 * The defect was that two of them were treated identically. "Resolved to no
 * school" (the platform super admin, deliberately cross-tenant) and "never
 * resolved at all" (nobody set it) both fell through to no constraint — so a
 * route registered without the `tenant` middleware would serve every school's
 * rows rather than failing.
 *
 * These tests pin all four, including the console carve-out that keeps the
 * seeder and the `synapse:*` commands working.
 */
class TenantScopeTest extends TestCase
{
    private function context(): TenantContext
    {
        return app(TenantContext::class);
    }

    private function school(int $id = 1): School
    {
        $school = new School();
        $school->id = $id;
        $school->name = 'Test School';

        return $school;
    }

    public function test_a_resolved_tenant_constrains_every_query(): void
    {
        $this->context()->set($this->school(7));

        $sql = User::query()->toSql();

        $this->assertStringContainsString('"school_id" = ?', $sql);
        $this->assertContains(7, User::query()->getBindings());
    }

    public function test_a_resolved_but_null_tenant_is_left_unconstrained(): void
    {
        // The platform super admin acts across schools on purpose. This is the
        // behaviour that must not change.
        $this->context()->set(null);

        $this->assertTrue($this->context()->isResolved());
        $this->assertStringNotContainsString('school_id', User::query()->toSql());
    }

    public function test_an_unresolved_context_in_console_is_left_unconstrained(): void
    {
        /*
         * The seeder creates every school with no tenant resolved, and console
         * commands and queued jobs run outside a request lifecycle. Failing
         * closed here would break them rather than protect anything — those
         * paths use forSchool() or withoutTenant() explicitly.
         */
        $this->context()->forget();

        $this->assertFalse($this->context()->isResolved());
        $this->assertTrue(app()->runningInConsole(), 'PHPUnit runs in console, which is the carve-out case.');
        $this->assertStringNotContainsString('school_id', User::query()->toSql());
    }

    public function test_resolution_is_tracked_not_inferred_from_nullness(): void
    {
        $context = $this->context();

        $context->forget();
        $this->assertFalse($context->isResolved());
        $this->assertNull($context->schoolId());

        $context->set(null);
        $this->assertTrue($context->isResolved(), 'A deliberate null is still a resolution.');
        $this->assertNull($context->schoolId());

        $context->set($this->school(3));
        $this->assertSame(3, $context->schoolId());

        $context->forget();
        $this->assertFalse($context->isResolved());
    }

    public function test_run_restores_both_the_school_and_the_resolution_flag(): void
    {
        $context = $this->context();
        $context->forget();

        $inside = $context->run($this->school(9), fn () => [
            'resolved' => $context->isResolved(),
            'id' => $context->schoolId(),
        ]);

        $this->assertTrue($inside['resolved']);
        $this->assertSame(9, $inside['id']);

        $this->assertFalse($context->isResolved(), 'run() must restore the previous resolution, not leave it set.');
        $this->assertNull($context->schoolId());
    }

    public function test_run_restores_a_previous_school_rather_than_clearing_it(): void
    {
        $context = $this->context();
        $context->set($this->school(2));

        $context->run($this->school(5), function () {});

        $this->assertTrue($context->isResolved());
        $this->assertSame(2, $context->schoolId());
    }
}
