<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentRequest;
use App\Models\Payment;
use App\Models\School;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Services\Pdf\PdfRenderer;
use App\Services\Pdf\ReportCardPresenter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Issues the school's official documents as real PDFs.
 *
 * Every document is rendered from a Blade template through dompdf, stored on a
 * private disk and stamped with a verification code that can be checked at
 * `GET /api/verify/{code}` without authentication.
 */
class DocumentService
{
    public function __construct(
        private readonly PdfRenderer $pdf,
        private readonly GradeService $grades,
        private readonly TranscriptService $transcripts,
        private readonly ReportCardPresenter $presenter,
        private readonly DocumentTypeService $documentTypes,
    ) {}

    /**
     * Documents issued to a student (most recent first).
     *
     * @return Collection<int, Document>
     */
    public function forStudent(Student $student): Collection
    {
        return $student->documents()->latest()->get();
    }

    /**
     * Generate the official PDF backing a document request.
     */
    public function generateForRequest(DocumentRequest $request, ?User $actor = null): Document
    {
        $student = $request->student()->with(['user', 'school'])->first();
        $school = $student?->school ?? $request->school;

        $payload = $this->certificatePayload($request, $student, $school);

        return $this->issue(
            view: 'pdf.certificate',
            payload: $payload,
            school: $school,
            student: $student,
            type: Str::slug($request->type, '_'),
            title: $payload['document_title'],
            reference: $request->reference,
            request: $request,
            actor: $actor,
            meta: ['request_id' => $request->id],
        );
    }

    /**
     * Generate (or re-generate) a student's report card for a period.
     */
    public function generateReportCard(Student $student, ?Semester $semester = null, ?User $actor = null): Document
    {
        $student->loadMissing(['user', 'school']);

        $reportCard = $this->grades->reportCard($student, null, $semester);
        $presented = $this->presenter->present($reportCard);

        $period = $semester?->name ?? 'Full year';
        $year = $reportCard['academic_year'];

        return $this->issue(
            view: 'pdf.report-card',
            payload: array_merge($presented, [
                'student' => $student,
                'class' => $reportCard['class'],
                'academic_year' => $year,
                'semester' => $semester,
                'rank' => $reportCard['rank'],
                'class_size' => $reportCard['class_size'],
            ]),
            school: $student->school,
            student: $student,
            type: 'report_card',
            title: 'Report card — '.($year?->name ?? '').' · '.$period,
            actor: $actor,
            meta: [
                'academic_year_id' => $year?->id,
                'semester_id' => $semester?->id,
                'average' => $reportCard['average'],
                'rank' => $reportCard['rank'],
            ],
        );
    }

    /**
     * Generate a student's multi-year transcript.
     */
    public function generateTranscript(Student $student, ?User $actor = null): Document
    {
        $student->loadMissing(['user', 'school']);

        $transcript = $this->transcripts->forStudent($student);

        return $this->issue(
            view: 'pdf.transcript',
            payload: [
                'student' => $student,
                'years' => $transcript['years'],
                'cumulative' => $transcript['cumulative'],
                'scale' => config('synapse.grading.scale', 20),
            ],
            school: $student->school,
            student: $student,
            type: 'transcript',
            title: 'Academic transcript',
            actor: $actor,
            meta: ['cumulative' => $transcript['cumulative']],
        );
    }

    /**
     * Render a payment receipt. Receipts are not stored as student documents —
     * they are streamed straight back to the school admin who asked for one.
     */
    public function receiptBytes(Payment $payment): string
    {
        $payment->loadMissing(['school', 'subscription.plan']);

        $plan = $payment->subscription?->plan;

        return $this->pdf->render('pdf.receipt', array_merge(
            $this->pdf->branding($payment->school),
            [
                'payment' => $payment,
                'description' => $plan
                    ? 'SYNAPSE subscription — '.$plan->name.' plan'
                    : 'SYNAPSE platform subscription',
                'period' => $payment->subscription
                    ? optional($payment->subscription->start_date)->format('d M Y').' → '.optional($payment->subscription->end_date)->format('d M Y')
                    : null,
                'issued_at' => now(),
                'reference' => $payment->reference,
                'verification_code' => null,
                'verification_url' => null,
            ],
        ));
    }

    /**
     * Stream a stored document to the browser as a download.
     */
    public function download(Document $document): StreamedResponse
    {
        abort_unless(
            Storage::disk($document->disk)->exists($document->path),
            410,
            'The stored file for this document is no longer available. Please request it again.',
        );

        return Storage::disk($document->disk)->download($document->path, $document->file_name, [
            'Content-Type' => $document->mime_type,
        ]);
    }

    /**
     * Public authenticity check: returns the minimum safe metadata.
     *
     * @return array<string, mixed>|null
     */
    public function verify(string $code): ?array
    {
        /** @var Document|null $document */
        $document = Document::withoutGlobalScopes()
            ->with(['student.user', 'school'])
            ->where('verification_code', strtoupper(trim($code)))
            ->first();

        if (! $document) {
            return null;
        }

        return [
            'valid' => true,
            'title' => $document->title,
            'type' => $document->type,
            'issued_on' => $document->created_at?->toDateString(),
            'issued_to' => $document->student?->user?->name,
            'matricule' => $document->student?->matricule,
            'school' => $document->school?->name,
            'verification_code' => $document->verification_code,
        ];
    }

    /**
     * Render a template, store the PDF and record the Document row.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $meta
     */
    private function issue(
        string $view,
        array $payload,
        ?School $school,
        ?Student $student,
        string $type,
        string $title,
        ?string $reference = null,
        ?DocumentRequest $request = null,
        ?User $actor = null,
        array $meta = [],
    ): Document {
        $code = $this->uniqueCode();
        $disk = config('synapse.documents.disk', 'local');

        $data = array_merge(
            $this->pdf->branding($school),
            $payload,
            [
                'issued_at' => now(),
                'reference' => $reference ?? $code,
                'verification_code' => $code,
                'verification_url' => rtrim((string) config('synapse.documents.verification_url'), '/'),
            ],
        );

        $fileName = Str::slug($type.'-'.($student?->matricule ?? 'document').'-'.$code).'.pdf';
        $path = 'documents/'.($school?->id ?? 'platform').'/'.$fileName;

        $stored = $this->pdf->store($view, $data, $path, $disk);

        return Document::create([
            'school_id' => $school?->id ?? $student?->school_id,
            'request_id' => $request?->id,
            'student_id' => $student?->id,
            'type' => $type,
            'issued_by' => $actor?->id,
            'title' => $title,
            'file_name' => $fileName,
            'mime_type' => 'application/pdf',
            'size' => $stored['size'],
            'disk' => $stored['disk'],
            'path' => $stored['path'],
            'verification_code' => $code,
            'meta' => $meta,
        ]);
    }

    /**
     * Human-readable, collision-checked verification code (e.g. SYN-7KQ4-2XPD).
     */
    private function uniqueCode(): string
    {
        do {
            $code = 'SYN-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
        } while (Document::withoutGlobalScopes()->where('verification_code', $code)->exists());

        return $code;
    }

    /**
     * Wording for each certificate type. Unknown types fall back to a generic
     * attestation so a school can add its own request types freely.
     *
     * @return array<string, mixed>
     */
    private function certificatePayload(DocumentRequest $request, ?Student $student, ?School $school): array
    {
        $name = e($student?->user?->name ?? 'the student');
        $matricule = $student?->matricule ?? '—';
        $schoolName = e($school?->name ?? config('app.name'));

        $enrollment = $student?->enrollments()
            ->with(['schoolClass', 'academicYear'])
            ->latest('academic_year_id')
            ->first();

        $class = $enrollment?->schoolClass?->name;
        $year = $enrollment?->academicYear?->name;

        $details = array_filter([
            'Student' => $student?->user?->name,
            'Matricule' => $matricule,
            'Class' => $class,
            'Academic year' => $year,
            'Request reference' => $request->reference,
        ]);

        // Match on the canonical type, resolved through the classifier so a
        // request filed as free text still lands on the right document.
        $resolved = $this->documentTypes->classify($request->type);

        [$title, $body] = match ($resolved) {
            DocumentRequest::TYPE_ENROLLMENT => [
                'Certificate of Enrollment',
                "This is to certify that <b>{$name}</b> (matricule {$matricule}) is duly enrolled at {$schoolName}"
                    .($class ? " in class <b>{$class}</b>" : '')
                    .($year ? " for the {$year} academic year" : '').'.',
            ],
            DocumentRequest::TYPE_TRANSCRIPT => [
                'Attestation of Academic Records',
                "This is to certify that the academic records of <b>{$name}</b> (matricule {$matricule}) are held by {$schoolName} and may be released on request.",
            ],
            DocumentRequest::TYPE_TRANSFER => [
                'Transfer Certificate',
                "This is to certify that <b>{$name}</b> (matricule {$matricule}) was a student of {$schoolName}"
                    .($class ? " in class <b>{$class}</b>" : '')
                    .' and has been granted a transfer at their own request. All school obligations have been settled.',
            ],
            DocumentRequest::TYPE_GOOD_CONDUCT => [
                'Certificate of Good Conduct',
                "This is to certify that <b>{$name}</b> (matricule {$matricule}) has, throughout their stay at {$schoolName}, maintained satisfactory conduct and discipline.",
            ],
            DocumentRequest::TYPE_LEAVING => [
                'School Leaving Certificate',
                "This is to certify that <b>{$name}</b> (matricule {$matricule}) has completed their studies at {$schoolName}"
                    .($year ? " at the end of the {$year} academic year" : '').'.',
            ],

            /*
            | No silent default. This arm used to emit a generic "is known to
            | this school" certificate for anything it did not recognise, the
            | request was then marked ready, and the student was told their
            | document was available — so a pupil who asked for a recommendation
            | letter was handed a document that was not what they asked for,
            | with nothing anywhere to say so. Refusing is the correct failure.
            */
            default => throw ValidationException::withMessages([
                'type' => $this->documentTypes->triage($request)['reason']
                    ?? 'This document cannot be generated automatically.',
            ]),
        };

        return [
            'document_title' => $title,
            'body' => $body,
            'details' => $details,
            'student' => $student,
        ];
    }
}
