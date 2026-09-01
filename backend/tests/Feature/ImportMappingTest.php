<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * CSV import mapping and the dry-run preview (Phase 7.2).
 *
 * The defect behind this phase: `ImportService::parseCsv()` lowercases the header
 * and matches by exact string, so a French export produced a file of nulls and a
 * wall of "name is required" errors for a spreadsheet that was perfectly
 * readable. These tests cover the mapping, and — just as importantly — that the
 * preview writes nothing and that a confirmed mapping actually imports.
 */
class ImportMappingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function actAs(string $email): User
    {
        $user = User::where('email', $email)->firstOrFail();

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    /**
     * A French-headed student export of the kind the old parser could not read.
     *
     * @return array<int, array<string, mixed>>
     */
    private function frenchRows(): array
    {
        return [
            ['Nom' => 'Ngo Bassa Élodie', 'Courriel' => 'ELODIE@AICS.cm', 'Matricule' => 'AICS-9001', 'Classe' => 'Level 3A', 'Téléphone' => '690123456'],
            ['Nom' => 'Mbarga Jean', 'Courriel' => 'jean@aics.cm', 'Matricule' => 'AICS-9002', 'Classe' => 'level 3a', 'Téléphone' => '+237 690 111 222'],
        ];
    }

    // ------------------------------------------------------------------ preview

    public function test_an_admin_can_preview_a_french_headed_import(): void
    {
        $this->actAs('admin@synapse.test');

        $response = $this->postJson('/api/admin/import/preview', [
            'type' => 'students',
            'rows' => $this->frenchRows(),
        ])->assertOk();

        $data = $response->json('data');

        $this->assertSame([
            'name' => 'Nom',
            'email' => 'Courriel',
            'matricule' => 'Matricule',
            'class' => 'Classe',
            'phone' => 'Téléphone',
        ], $data['mapping']);

        $this->assertSame([], $data['unmapped']);
        $this->assertSame('deterministic', $data['source']);
    }

    public function test_the_preview_resolves_class_labels_to_ids(): void
    {
        $this->actAs('admin@synapse.test');

        $data = $this->postJson('/api/admin/import/preview', [
            'type' => 'students',
            'rows' => $this->frenchRows(),
        ])->assertOk()->json('data');

        $classId = $data['rows'][0]['values']['class_id'];

        $this->assertNotNull($classId, 'Level 3A should resolve to a real class.');
        $this->assertSame('Level 3A', $data['rows'][0]['class']['matched']);

        // Case and spacing differ between the two rows but name the same class.
        $this->assertSame($classId, $data['rows'][1]['values']['class_id']);
    }

    public function test_the_preview_normalises_values(): void
    {
        $this->actAs('admin@synapse.test');

        $data = $this->postJson('/api/admin/import/preview', [
            'type' => 'students',
            'rows' => $this->frenchRows(),
        ])->assertOk()->json('data');

        $this->assertSame('+237690123456', $data['rows'][0]['values']['phone']);
        $this->assertSame('+237690123456', $data['rows'][1]['values']['phone']);
        $this->assertSame('elodie@aics.cm', $data['rows'][0]['values']['email']);

        // Normalisation must not strip the accents out of a name.
        $this->assertSame('Ngo Bassa Élodie', $data['rows'][0]['values']['name']);
    }

    public function test_a_clean_preview_reports_every_row_importable(): void
    {
        $this->actAs('admin@synapse.test');

        $data = $this->postJson('/api/admin/import/preview', [
            'type' => 'students',
            'rows' => $this->frenchRows(),
        ])->assertOk()->json('data');

        $this->assertSame(2, $data['summary']['total']);
        $this->assertSame(2, $data['summary']['importable']);
        $this->assertSame(0, $data['summary']['needs_attention']);
    }

    public function test_the_preview_writes_nothing(): void
    {
        $this->actAs('admin@synapse.test');

        $before = Student::query()->count();

        $this->postJson('/api/admin/import/preview', [
            'type' => 'students',
            'rows' => $this->frenchRows(),
        ])->assertOk();

        $this->assertSame($before, Student::query()->count(), 'A preview must not create accounts.');
    }

    public function test_an_unknown_class_is_reported_rather_than_guessed(): void
    {
        $this->actAs('admin@synapse.test');

        $data = $this->postJson('/api/admin/import/preview', [
            'type' => 'students',
            'rows' => [['Nom' => 'Test Pupil', 'Courriel' => 't@aics.cm', 'Classe' => 'Level 9Z']],
        ])->assertOk()->json('data');

        $this->assertNull($data['rows'][0]['values']['class_id']);
        $this->assertSame(1, $data['summary']['needs_attention']);
        $this->assertStringContainsString('No class', $data['rows'][0]['warnings'][0]);
    }

    public function test_an_ambiguous_class_matches_nothing(): void
    {
        $admin = $this->actAs('admin@synapse.test');

        // Two classes both containing "Level 3": a pick here would put a pupil in
        // the wrong class, so the resolver refuses and says why.
        \App\Models\SchoolClass::create(['school_id' => $admin->school_id, 'name' => 'Level 3 Extra']);

        $data = $this->postJson('/api/admin/import/preview', [
            'type' => 'students',
            'rows' => [['Nom' => 'Test Pupil', 'Courriel' => 't@aics.cm', 'Classe' => 'Level 3']],
        ])->assertOk()->json('data');

        $this->assertNull($data['rows'][0]['values']['class_id']);
        $this->assertTrue($data['rows'][0]['class']['ambiguous']);
    }

    public function test_the_preview_lists_the_schools_own_classes(): void
    {
        $this->actAs('admin@synapse.test');

        $data = $this->postJson('/api/admin/import/preview', [
            'type' => 'students',
            'rows' => $this->frenchRows(),
        ])->assertOk()->json('data');

        $names = collect($data['available_classes'])->pluck('name')->all();

        $this->assertContains('Level 3A', $names);
        $this->assertNotEmpty($names);
    }

    public function test_unmapped_columns_are_surfaced(): void
    {
        $this->actAs('admin@synapse.test');

        $data = $this->postJson('/api/admin/import/preview', [
            'type' => 'students',
            'rows' => [['Nom' => 'Test Pupil', 'Courriel' => 't@aics.cm', 'Profession du père' => 'Cacaoculteur']],
        ])->assertOk()->json('data');

        $this->assertSame(['Profession du père'], $data['unmapped']);
    }

    public function test_an_invalid_email_is_flagged_in_the_preview(): void
    {
        $this->actAs('admin@synapse.test');

        $data = $this->postJson('/api/admin/import/preview', [
            'type' => 'students',
            'rows' => [['Nom' => 'Test Pupil', 'Courriel' => 'not-an-email', 'Classe' => 'Level 3A']],
        ])->assertOk()->json('data');

        $this->assertSame(1, $data['summary']['needs_attention']);
        $this->assertStringContainsString('not a valid email', $data['rows'][0]['warnings'][0]);
    }

    public function test_a_teacher_import_does_not_ask_for_a_class(): void
    {
        $this->actAs('admin@synapse.test');

        $data = $this->postJson('/api/admin/import/preview', [
            'type' => 'teachers',
            'rows' => [['Nom' => 'Mme Chen', 'Courriel' => 'chen@aics.cm', 'N° du personnel' => 'TCH-900']],
        ])->assertOk()->json('data');

        $this->assertSame('TCH-900', $data['rows'][0]['values']['staff_no']);
        $this->assertSame([], $data['available_classes']);
        $this->assertSame(0, $data['summary']['needs_attention']);
    }

    public function test_a_preview_is_capped(): void
    {
        $this->actAs('admin@synapse.test');

        $this->postJson('/api/admin/import/preview', [
            'type' => 'students',
            'rows' => array_fill(0, 501, ['Nom' => 'X', 'Courriel' => 'x@aics.cm']),
        ])->assertStatus(422)->assertJsonValidationErrors('rows');
    }

    // ----------------------------------------------------- confirmed import path

    public function test_a_confirmed_mapping_imports_the_students(): void
    {
        $this->actAs('admin@synapse.test');

        $before = Student::query()->count();

        $response = $this->postJson('/api/admin/import', [
            'type' => 'students',
            'mapping' => [
                'name' => 'Nom',
                'email' => 'Courriel',
                'matricule' => 'Matricule',
                'class' => 'Classe',
                'phone' => 'Téléphone',
            ],
            'rows' => $this->frenchRows(),
        ])->assertOk();

        $this->assertSame(2, $response->json('created'));
        $this->assertSame($before + 2, Student::query()->count());

        $student = Student::query()
            ->whereHas('user', fn ($query) => $query->where('email', 'elodie@aics.cm'))
            ->first();

        $this->assertNotNull($student);
        $this->assertSame('+237690123456', $student->user->phone);
    }

    public function test_the_legacy_row_shape_still_works_unchanged(): void
    {
        $this->actAs('admin@synapse.test');

        $classId = \App\Models\SchoolClass::where('name', 'Level 3A')->value('id');
        $before = Student::query()->count();

        $this->postJson('/api/admin/import', [
            'type' => 'students',
            'rows' => [[
                'name' => 'Legacy Pupil',
                'email' => 'legacy@aics.cm',
                'matricule' => 'AICS-9100',
                'class_id' => $classId,
            ]],
        ])->assertOk()->assertJsonPath('created', 1);

        $this->assertSame($before + 1, Student::query()->count());
    }

    public function test_a_mapping_pointing_at_a_missing_column_is_rejected(): void
    {
        $this->actAs('admin@synapse.test');

        // Without this check every row would import as null, silently.
        $this->postJson('/api/admin/import', [
            'type' => 'students',
            'mapping' => ['name' => 'Nom', 'email' => 'Adresse introuvable'],
            'rows' => $this->frenchRows(),
        ])->assertStatus(422)->assertJsonValidationErrors('mapping.email');
    }

    public function test_a_mapping_key_that_is_not_a_field_is_rejected(): void
    {
        $this->actAs('admin@synapse.test');

        $this->postJson('/api/admin/import', [
            'type' => 'students',
            'mapping' => ['name' => 'Nom', 'role' => 'Classe'],
            'rows' => $this->frenchRows(),
        ])->assertStatus(422)->assertJsonValidationErrors('mapping.role');
    }

    public function test_an_empty_mapping_is_rejected(): void
    {
        $this->actAs('admin@synapse.test');

        $this->postJson('/api/admin/import', [
            'type' => 'students',
            'mapping' => [],
            'rows' => $this->frenchRows(),
        ])->assertStatus(422);
    }

    // --------------------------------------------------------------- permissions

    public function test_a_teacher_cannot_preview_an_import(): void
    {
        $this->actAs('teacher@synapse.test');

        $this->postJson('/api/admin/import/preview', [
            'type' => 'students',
            'rows' => $this->frenchRows(),
        ])->assertForbidden();
    }

    public function test_a_guest_cannot_preview_an_import(): void
    {
        $this->postJson('/api/admin/import/preview', [
            'type' => 'students',
            'rows' => $this->frenchRows(),
        ])->assertUnauthorized();
    }

    public function test_another_schools_classes_are_neither_listed_nor_matched(): void
    {
        $this->actAs('admin.saintalbert@synapse.test');

        $data = $this->postJson('/api/admin/import/preview', [
            'type' => 'students',
            'rows' => [['Nom' => 'Test Pupil', 'Courriel' => 't@saintalbert.edu', 'Classe' => 'Level 3A']],
        ])->assertOk()->json('data');

        $this->assertNotContains('Level 3A', collect($data['available_classes'])->pluck('name')->all());
        $this->assertNull($data['rows'][0]['values']['class_id']);
    }

    // ------------------------------------------------------------- provider path

    public function test_header_mapping_falls_back_when_the_provider_fails(): void
    {
        config()->set('ai.enabled', true);
        config()->set('ai.driver', 'http');
        config()->set('ai.key', 'test-key');
        config()->set('ai.model', 'test-model');

        Http::fake(['*' => Http::response(['error' => 'upstream'], 500)]);

        $this->actAs('admin@synapse.test');

        $data = $this->postJson('/api/admin/import/preview', [
            'type' => 'students',
            'rows' => $this->frenchRows(),
        ])->assertOk()->json('data');

        $this->assertSame('Nom', $data['mapping']['name'], 'The rule table still maps the file.');
    }

    public function test_only_the_header_row_is_sent_to_a_provider(): void
    {
        config()->set('ai.enabled', true);
        config()->set('ai.driver', 'http');
        config()->set('ai.key', 'test-key');
        config()->set('ai.model', 'test-model');

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => '{"mapping":{}}']]],
        ])]);

        $this->actAs('admin@synapse.test');

        $this->postJson('/api/admin/import/preview', [
            'type' => 'students',
            'rows' => $this->frenchRows(),
        ])->assertOk();

        Http::assertSent(function ($request) {
            $body = (string) json_encode($request->data());

            // Pupils' data must never leave the server to work out what a column
            // is called — only the header row does.
            $this->assertStringNotContainsString('Ngo Bassa', $body);
            $this->assertStringNotContainsString('elodie@aics.cm', $body);
            $this->assertStringNotContainsString('690123456', $body);
            $this->assertStringContainsString('Courriel', $body);

            return true;
        });
    }

    public function test_a_provider_suggestion_cannot_overrule_an_exact_match(): void
    {
        config()->set('ai.enabled', true);
        config()->set('ai.driver', 'http');
        config()->set('ai.key', 'test-key');
        config()->set('ai.model', 'test-model');

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'mapping' => ['name' => 'Classe'],
            ])]]],
        ])]);

        $this->actAs('admin@synapse.test');

        $data = $this->postJson('/api/admin/import/preview', [
            'type' => 'students',
            'rows' => $this->frenchRows(),
        ])->assertOk()->json('data');

        $this->assertSame('Nom', $data['mapping']['name'], 'An exact rule match is not overturned.');
    }
}
