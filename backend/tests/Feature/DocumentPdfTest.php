<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentRequest;
use App\Models\Student;
use App\Models\User;
use App\Notifications\DocumentReadyNotification;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        Storage::fake('local');
    }

    private function student(): Student
    {
        return User::where('email', 'student@synapse.test')->firstOrFail()->student;
    }

    public function test_a_generated_document_is_a_real_pdf(): void
    {
        $document = app(DocumentService::class)->generateTranscript($this->student());

        $this->assertSame('application/pdf', $document->mime_type);
        $this->assertGreaterThan(500, $document->size);

        Storage::disk($document->disk)->assertExists($document->path);

        // A PDF always starts with the %PDF- magic number and ends with %%EOF.
        $bytes = Storage::disk($document->disk)->get($document->path);

        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertStringContainsString('%%EOF', $bytes);
    }

    public function test_a_report_card_pdf_can_be_downloaded_by_the_student(): void
    {
        Sanctum::actingAs(User::where('email', 'student@synapse.test')->firstOrFail(), ['*']);

        $response = $this->get('/api/student/report-card/pdf');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_generating_a_requested_document_notifies_the_student_and_marks_it_ready(): void
    {
        Notification::fake();

        $student = $this->student();

        $request = DocumentRequest::create([
            'school_id' => $student->school_id,
            'student_id' => $student->id,
            'reference' => 'REQ-9001',
            'type' => 'Certificate of Enrollment',
            'status' => DocumentRequest::STATUS_APPROVED,
        ]);

        Sanctum::actingAs(User::where('email', 'admin@synapse.test')->firstOrFail(), ['*']);

        $this->postJson("/api/admin/requests/{$request->id}/generate-document")
            ->assertOk()
            ->assertJsonPath('data.status', DocumentRequest::STATUS_READY);

        $document = Document::where('request_id', $request->id)->firstOrFail();

        $this->assertNotNull($document->verification_code);
        Notification::assertSentTo($student->user, DocumentReadyNotification::class);
    }

    public function test_a_document_can_be_verified_publicly_by_its_code(): void
    {
        $document = app(DocumentService::class)->generateTranscript($this->student());

        $this->getJson('/api/verify/'.$document->verification_code)
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('matricule', $this->student()->matricule);
    }

    public function test_an_unknown_verification_code_is_rejected(): void
    {
        $this->getJson('/api/verify/SYN-0000-0000')
            ->assertNotFound()
            ->assertJsonPath('valid', false);
    }

    public function test_a_student_cannot_download_another_students_document(): void
    {
        $document = app(DocumentService::class)->generateTranscript($this->student());

        $other = User::where('email', 'student.saintalbert@synapse.test')->first()
            ?? User::where('role', User::ROLE_STUDENT)
                ->where('school_id', '!=', $this->student()->school_id)
                ->firstOrFail();

        Sanctum::actingAs($other, ['*']);

        $response = $this->get("/api/student/documents/{$document->id}/download");

        // 403 within a school, 404 across tenants (the global scope hides it).
        $this->assertContains($response->status(), [403, 404]);
    }
}
