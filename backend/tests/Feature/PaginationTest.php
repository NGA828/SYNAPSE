<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        Sanctum::actingAs(User::where('email', 'admin@synapse.test')->firstOrFail(), ['*']);
    }

    public function test_student_index_is_paginated(): void
    {
        $this->getJson('/api/admin/students?per_page=2')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'matricule']],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'links',
            ])
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_page_size_is_capped(): void
    {
        config(['synapse.pagination.max_per_page' => 25]);

        $this->getJson('/api/admin/students?per_page=5000')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 25);
    }

    public function test_search_filters_students_by_name(): void
    {
        $response = $this->getJson('/api/admin/students?search=John')->assertOk();

        $names = collect($response->json('data'))->pluck('name');

        $this->assertNotEmpty($names);
        $this->assertTrue($names->every(fn ($name) => str_contains(strtolower((string) $name), 'john')));
    }

    public function test_search_never_crosses_the_tenant_boundary(): void
    {
        $response = $this->getJson('/api/admin/students?search=Mary')->assertOk();

        $this->assertSame([], $response->json('data'));
    }

    public function test_sorting_is_whitelisted(): void
    {
        // An unknown column must fall back to the default sort, not explode.
        $this->getJson('/api/admin/students?sort=password')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_teacher_index_is_paginated(): void
    {
        $this->getJson('/api/admin/teachers')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['total']]);
    }

    public function test_admin_requests_are_paginated_and_filterable(): void
    {
        $this->getJson('/api/admin/requests?status=submitted')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['total']]);
    }
}
