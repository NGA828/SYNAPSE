<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

/**
 * Tenant isolation (Phase 8.3).
 *
 * The scenario these tests exist for: somebody registers a new route and forgets
 * the `tenant` middleware. Before this phase, `TenantScope` failed **open** in
 * that case — no context, no constraint, every school's rows. Today all 150
 * authenticated routes happen to apply the middleware, so the hole is closed by
 * luck rather than by construction. This makes it closed by construction.
 *
 * The suite forces the application to believe it is serving HTTP, because the
 * fail-closed branch is deliberately gated on that: console is exempt so the
 * seeder and the `synapse:*` commands keep working.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        /*
         * PHPUnit runs in console, and `Application::runningInConsole()` checks
         * this env value before falling back to the SAPI. Setting it before the
         * application boots makes the scope take its HTTP branch.
         */
        $_SERVER['APP_RUNNING_IN_CONSOLE'] = 'false';
        putenv('APP_RUNNING_IN_CONSOLE=false');

        parent::setUp();

        $this->assertFalse(
            app()->runningInConsole(),
            'This suite must run as though it were serving HTTP, or the fail-closed branch is never reached.',
        );

        $this->seed();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['APP_RUNNING_IN_CONSOLE']);
        putenv('APP_RUNNING_IN_CONSOLE');

        parent::tearDown();
    }

    /**
     * A route with authentication but no `tenant` middleware — the regression
     * this phase prevents.
     */
    private function registerUnscopedRoute(): void
    {
        Route::middleware('auth:sanctum')
            ->get('/__test/unscoped-users', fn () => User::query()->pluck('email'))
            ->name('test.unscoped');

        Route::middleware('auth:sanctum')
            ->get('/__test/unscoped-students', fn () => Student::query()->count())
            ->name('test.unscoped.students');

        Route::middleware('auth:sanctum')
            ->get('/__test/without-tenant', fn () => User::query()->withoutTenant()->count())
            ->name('test.without_tenant');
    }

    private function otherSchool(): School
    {
        return School::where('slug', 'saintalbert')->firstOrFail();
    }

    private function actAs(string $email): User
    {
        $user = User::where('email', $email)->firstOrFail();

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    // ------------------------------------------------------- the regression

    public function test_a_route_missing_the_tenant_middleware_leaks_nothing(): void
    {
        $this->registerUnscopedRoute();

        $this->actAs('admin@synapse.test');

        $emails = $this->getJson('/__test/unscoped-users')->assertOk()->json();

        $this->assertIsArray($emails);
        $this->assertSame([], $emails, 'An unscoped route must fail closed, not return every school.');
    }

    public function test_a_route_missing_the_tenant_middleware_counts_nothing(): void
    {
        $this->registerUnscopedRoute();

        $this->actAs('admin@synapse.test');

        $this->getJson('/__test/unscoped-students')->assertOk()->assertJson(0);
    }

    public function test_the_seed_actually_contains_more_than_one_school(): void
    {
        /*
         * Without this, the two tests above would pass on an empty database and
         * prove nothing — the leak is only observable if there is something to
         * leak. `School` is the tenant itself and carries no tenant scope, and
         * `forSchool()` reaches past the scope without tripping the request-lifecycle
         * guard that `withoutTenant()` now enforces.
         */
        $this->assertGreaterThan(1, School::query()->count());

        $aics = School::where('slug', 'aics')->firstOrFail();
        $saintAlbert = $this->otherSchool();

        $this->assertGreaterThan(0, Student::query()->forSchool($aics)->count());
        $this->assertGreaterThan(0, Student::query()->forSchool($saintAlbert)->count());
    }

    // ------------------------------------------------- what must still work

    public function test_a_properly_middleware_d_route_still_sees_its_own_school(): void
    {
        $this->actAs('admin@synapse.test');

        $this->getJson('/api/admin/students')->assertOk();
    }

    public function test_a_tenant_scoped_query_still_returns_its_own_rows(): void
    {
        $user = $this->actAs('admin@synapse.test');

        app(TenantContext::class)->set($user->school);

        $this->assertGreaterThan(0, User::query()->count(), 'The seeded school has users.');

        $emails = User::query()->pluck('email')->all();

        $this->assertContains('admin@synapse.test', $emails);
        $this->assertNotContains('admin.saintalbert@synapse.test', $emails);
    }

    public function test_the_platform_super_admin_still_spans_tenants(): void
    {
        $superAdmin = User::where('email', 'superadmin@synapse.test')->firstOrFail();

        Sanctum::actingAs($superAdmin, ['*']);

        /*
         * IdentifyTenant resolves a super admin to a deliberate null, which now
         * counts as a resolution — so the scope stays open for them, by design.
         * If that distinction were lost, this would fail closed and the platform
         * admin would see an empty school list.
         */
        $this->getJson('/api/super-admin/dashboard')->assertOk();

        $this->assertNull(app(TenantContext::class)->schoolId());
        $this->assertTrue(app(TenantContext::class)->isResolved());
    }

    // ------------------------------------------------------ the second hatch

    public function test_without_tenant_refuses_during_a_request(): void
    {
        $this->registerUnscopedRoute();

        $this->actAs('admin@synapse.test');

        $response = $this->getJson('/__test/without-tenant');

        $this->assertSame(500, $response->status());
        $this->assertInstanceOf(RuntimeException::class, $response->exception);
        $this->assertStringContainsString('outside a request lifecycle', $response->exception->getMessage());
    }

    public function test_for_school_reaches_another_school_without_tripping_the_guard(): void
    {
        /*
         * The console branch of `withoutTenant()` cannot be exercised here — this
         * suite forces HTTP mode for every test. `TenantScopeTest` covers it.
         * `forSchool()` is the request-safe alternative and must keep working.
         */
        $saintAlbert = $this->otherSchool();

        $this->assertGreaterThan(0, Student::query()->forSchool($saintAlbert)->count());
    }

    // ------------------------------------------------------------- tenancy

    public function test_one_school_cannot_read_another_through_a_scoped_route(): void
    {
        $this->actAs('admin.saintalbert@synapse.test');

        $students = $this->getJson('/api/admin/students')->assertOk()->json('data');

        $aics = School::where('slug', 'aics')->firstOrFail();
        $aicsMatricules = Student::query()->forSchool($aics)->pluck('matricule')->all();

        $this->assertNotEmpty($students, 'The admin should see their own pupils.');
        $this->assertNotEmpty($aicsMatricules, 'And the other school should have some to leak.');

        foreach ($students as $student) {
            $this->assertNotContains($student['matricule'] ?? null, $aicsMatricules);
        }
    }
}
