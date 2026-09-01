<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentRequest;
use App\Models\User;
use App\Notifications\DocumentReadyNotification;
use App\Services\DocumentTypeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Document-request types and triage (Phase 6.1).
 *
 * The regression these tests exist to hold: a request for a document no
 * template can produce used to be issued a generic "is known to this school"
 * certificate, the request was marked ready, and the student was notified that
 * it was available to download. Every refusal below therefore also asserts what
 * did *not* happen — no document, no status change, no notification.
 */
class DocumentTriageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        Storage::fake('local');
    }

    private function actAs(string $email): User
    {
        $user = User::where('email', $email)->firstOrFail();

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function classifier(): DocumentTypeService
    {
        return app(DocumentTypeService::class);
    }

    /** File a request as the student, then approve it as the admin. */
    private function approved(string $type, string $reason = 'Test'): DocumentRequest
    {
        $this->actAs('student@synapse.test');

        $id = $this->postJson('/api/student/requests', ['type' => $type, 'reason' => $reason])
            ->assertCreated()
            ->json('data.id');

        $this->actAs('admin@synapse.test');

        $this->postJson("/api/admin/requests/{$id}/status", ['status' => DocumentRequest::STATUS_APPROVED])
            ->assertOk();

        return DocumentRequest::findOrFail($id);
    }

    private function documentsFor(int $requestId): int
    {
        return Document::query()->where('request_id', $requestId)->count();
    }

    // ------------------------------------------------------------- the catalogue

    public function test_the_catalogue_is_served_rather_than_duplicated_in_the_client(): void
    {
        $this->actAs('student@synapse.test');

        $catalogue = $this->getJson('/api/student/requests/types')->assertOk()->json('data');

        $this->assertCount(count(DocumentRequest::TYPES), $catalogue);

        foreach ($catalogue as $entry) {
            $this->assertArrayHasKey('label', $entry);
            $this->assertArrayHasKey('slug', $entry);
            $this->assertIsBool($entry['auto_generatable']);
        }

        $this->assertSame(
            DocumentRequest::TYPES,
            collect($catalogue)->pluck('label')->all(),
        );
    }

    public function test_options_that_need_staff_say_so_before_the_student_waits(): void
    {
        $this->actAs('student@synapse.test');

        $catalogue = collect($this->getJson('/api/student/requests/types')->assertOk()->json('data'));

        foreach ($catalogue as $entry) {
            if ($entry['auto_generatable']) {
                $this->assertNull($entry['note']);
            } else {
                $this->assertNotNull($entry['note'], "{$entry['label']} should warn the student.");
            }
        }

        $this->assertFalse(
            $catalogue->firstWhere('slug', 'recommendation_letter')['auto_generatable'],
        );
    }

    // ------------------------------------------------------- the type is closed

    public function test_an_unknown_type_is_rejected(): void
    {
        $this->actAs('student@synapse.test');

        $this->postJson('/api/student/requests', ['type' => 'A letter saying I am nice'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_an_empty_type_is_rejected(): void
    {
        $this->actAs('student@synapse.test');

        $this->postJson('/api/student/requests', ['type' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_every_canonical_type_is_accepted(): void
    {
        $this->actAs('student@synapse.test');

        foreach (DocumentRequest::TYPES as $type) {
            $this->postJson('/api/student/requests', ['type' => $type, 'reason' => 'Accepted-type check'])
                ->assertCreated();
        }
    }

    // ------------------------------------------------------ THE FIX: refusal

    public function test_a_recommendation_letter_is_refused_and_nothing_is_created(): void
    {
        Notification::fake();

        $request = $this->approved(DocumentRequest::TYPE_RECOMMENDATION, 'For a scholarship panel');

        $response = $this->postJson("/api/admin/requests/{$request->id}/generate-document")
            ->assertStatus(422);

        $this->assertStringContainsString(
            'written and signed',
            $response->json('message'),
            'The refusal must explain why, not just refuse.',
        );

        $request->refresh();

        $this->assertSame(0, $this->documentsFor($request->id), 'No document may be created.');
        $this->assertSame(
            DocumentRequest::STATUS_APPROVED,
            $request->status,
            'The request must stay open for a person, not be marked ready.',
        );

        Notification::assertNotSentTo(
            $request->student->user,
            DocumentReadyNotification::class,
        );
    }

    public function test_an_unspecified_request_is_refused(): void
    {
        $request = $this->approved(DocumentRequest::TYPE_OTHER, 'Something unusual');

        $this->postJson("/api/admin/requests/{$request->id}/generate-document")->assertStatus(422);

        $this->assertSame(0, $this->documentsFor($request->id));
        $this->assertSame(DocumentRequest::STATUS_APPROVED, $request->fresh()->status);
    }

    public function test_a_document_the_template_can_produce_still_issues(): void
    {
        $request = $this->approved(DocumentRequest::TYPE_ENROLLMENT, 'University application');

        $this->postJson("/api/admin/requests/{$request->id}/generate-document")->assertOk();

        $request->refresh();

        $this->assertSame(1, $this->documentsFor($request->id));
        $this->assertSame(DocumentRequest::STATUS_READY, $request->status);
    }

    public function test_a_transcript_request_still_issues(): void
    {
        $request = $this->approved(DocumentRequest::TYPE_TRANSCRIPT, 'Scholarship');

        $this->postJson("/api/admin/requests/{$request->id}/generate-document")->assertOk();

        $this->assertSame(DocumentRequest::STATUS_READY, $request->fresh()->status);
    }

    // ------------------------------------------------------------- classifier

    public function test_an_exact_canonical_type_classifies_to_itself(): void
    {
        foreach (DocumentRequest::TYPES as $type) {
            $this->assertSame($type, $this->classifier()->classify($type));
        }
    }

    public function test_classification_ignores_case_and_padding(): void
    {
        $this->assertSame(
            DocumentRequest::TYPE_ENROLLMENT,
            $this->classifier()->classify('  certificate of ENROLLMENT  '),
        );
    }

    public function test_legacy_free_text_is_rescued_by_keyword(): void
    {
        $this->assertSame(
            DocumentRequest::TYPE_ENROLLMENT,
            $this->classifier()->classify('Enrolment Certifcate'),
            'A common misspelling should still resolve.',
        );

        $this->assertSame(DocumentRequest::TYPE_RECOMMENDATION, $this->classifier()->classify('Reference letter'));
        $this->assertSame(DocumentRequest::TYPE_TRANSCRIPT, $this->classifier()->classify('Attestation of results'));
        $this->assertSame(DocumentRequest::TYPE_TRANSFER, $this->classifier()->classify('Transfer Certificate'));
        $this->assertSame(DocumentRequest::TYPE_GOOD_CONDUCT, $this->classifier()->classify('Good standing'));
        $this->assertSame(DocumentRequest::TYPE_LEAVING, $this->classifier()->classify('School Leaving Certificate'));
    }

    public function test_the_specific_word_wins_over_the_generic_one(): void
    {
        // Both contain "certificate"; only the discriminator should decide.
        $this->assertSame(DocumentRequest::TYPE_TRANSFER, $this->classifier()->classify('Transfer Certificate'));
        $this->assertSame(DocumentRequest::TYPE_LEAVING, $this->classifier()->classify('School Leaving Certificate'));
    }

    public function test_unmappable_text_returns_null_rather_than_a_guess(): void
    {
        $this->assertNull($this->classifier()->classify('Permission to keep a goat on campus'));
        $this->assertNull($this->classifier()->classify(''));
        $this->assertNull($this->classifier()->classify(null));
    }

    public function test_other_is_an_escape_hatch_not_a_failed_classification(): void
    {
        $triage = $this->classifier()->triage(
            new DocumentRequest(['type' => DocumentRequest::TYPE_OTHER]),
        );

        $this->assertTrue($triage['classified']);
        $this->assertFalse($triage['auto_generatable']);
        $this->assertTrue($triage['needs_human']);
    }

    // --------------------------------------------------------------- triage rows

    public function test_every_row_in_the_queue_carries_its_triage(): void
    {
        $this->actAs('admin@synapse.test');

        $rows = $this->getJson('/api/admin/requests')->assertOk()->json('data');

        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertArrayHasKey('triage', $row);
            $this->assertArrayHasKey('needs_human', $row['triage']);
            $this->assertArrayHasKey('slug', $row['triage']);
        }
    }

    public function test_a_rescued_legacy_type_is_marked_as_not_exact(): void
    {
        $student = User::where('email', 'student@synapse.test')->firstOrFail()->student;

        $request = DocumentRequest::create([
            'school_id' => $student->school_id,
            'student_id' => $student->id,
            'reference' => 'REQ-9001',
            'type' => 'Enrolment Certifcate',
            'status' => DocumentRequest::STATUS_SUBMITTED,
        ]);

        $this->actAs('admin@synapse.test');

        $row = collect($this->getJson('/api/admin/requests')->assertOk()->json('data'))
            ->firstWhere('id', $request->id);

        $this->assertTrue($row['triage']['classified']);
        $this->assertSame(DocumentRequest::TYPE_ENROLLMENT, $row['triage']['type']);
        $this->assertFalse($row['triage']['exact'], 'It was rescued by a keyword, not filed canonically.');
        $this->assertTrue($row['triage']['auto_generatable']);
    }

    public function test_an_unmappable_request_is_flagged_and_explains_itself(): void
    {
        $student = User::where('email', 'student@synapse.test')->firstOrFail()->student;

        $request = DocumentRequest::create([
            'school_id' => $student->school_id,
            'student_id' => $student->id,
            'reference' => 'REQ-9002',
            'type' => 'Permission to keep a goat on campus',
            'status' => DocumentRequest::STATUS_SUBMITTED,
        ]);

        $this->actAs('admin@synapse.test');

        $row = collect($this->getJson('/api/admin/requests')->assertOk()->json('data'))
            ->firstWhere('id', $request->id);

        $this->assertFalse($row['triage']['classified']);
        $this->assertNull($row['triage']['type']);
        $this->assertTrue($row['triage']['needs_human']);
        $this->assertGreaterThan(20, strlen($row['triage']['reason']));

        $this->postJson("/api/admin/requests/{$request->id}/generate-document")->assertStatus(422);
        $this->assertSame(0, $this->documentsFor($request->id));
    }

    // ------------------------------------------------------------ queue counters

    public function test_the_triage_summary_partitions_the_backlog(): void
    {
        $this->actAs('admin@synapse.test');

        $summary = $this->getJson('/api/admin/requests/triage')->assertOk()->json('data');

        $this->assertSame(
            $summary['total'],
            $summary['auto_generatable'] + $summary['needs_human'],
            'The two counters must account for the whole backlog.',
        );
        $this->assertCount(count(DocumentRequest::TYPES), $summary['catalogue']);
    }

    public function test_the_needs_human_filter_returns_only_those_rows(): void
    {
        $this->approved(DocumentRequest::TYPE_RECOMMENDATION, 'Scholarship panel');

        $this->actAs('admin@synapse.test');

        $summary = $this->getJson('/api/admin/requests/triage')->assertOk()->json('data');
        $this->assertGreaterThan(0, $summary['needs_human']);

        $filtered = $this->getJson('/api/admin/requests?needs_human=1')->assertOk()->json('data');

        $this->assertNotEmpty($filtered);

        foreach ($filtered as $row) {
            $this->assertTrue($row['triage']['needs_human']);
        }

        $this->assertSame($summary['needs_human'], count($filtered));
    }

    // ------------------------------------------------------------ access control

    public function test_a_student_cannot_reach_the_admin_queue(): void
    {
        $this->actAs('student@synapse.test');

        $this->getJson('/api/admin/requests')->assertForbidden();
        $this->getJson('/api/admin/requests/triage')->assertForbidden();
    }

    public function test_a_student_cannot_generate_their_own_document(): void
    {
        $request = $this->approved(DocumentRequest::TYPE_ENROLLMENT);

        $this->actAs('student@synapse.test');

        $this->postJson("/api/admin/requests/{$request->id}/generate-document")->assertForbidden();
    }

    public function test_a_guest_cannot_read_the_catalogue_or_the_summary(): void
    {
        $this->getJson('/api/student/requests/types')->assertUnauthorized();
        $this->getJson('/api/admin/requests/triage')->assertUnauthorized();
    }

    public function test_another_school_cannot_generate_from_this_queue(): void
    {
        $request = $this->approved(DocumentRequest::TYPE_ENROLLMENT);

        $this->actAs('admin.saintalbert@synapse.test');

        // 404, not 403: the response must not confirm the record exists.
        $this->postJson("/api/admin/requests/{$request->id}/generate-document")->assertNotFound();
    }

    public function test_the_summary_counts_only_the_callers_own_school(): void
    {
        $this->actAs('admin.saintalbert@synapse.test');

        $summary = $this->getJson('/api/admin/requests/triage')->assertOk()->json('data');

        $schoolId = User::where('email', 'admin.saintalbert@synapse.test')->value('school_id');

        $this->assertSame(
            // Scoped explicitly: the tenant scope only applies inside an HTTP
            // request, so an unscoped count here would span every school.
            DocumentRequest::query()->where('school_id', $schoolId)->count(),
            $summary['total'],
            'Only Saint Albert requests are in scope for this administrator.',
        );
    }
}
