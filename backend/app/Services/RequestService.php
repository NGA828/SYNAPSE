<?php

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\Student;
use Illuminate\Support\Collection;

class RequestService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly DocumentService $documents,
    ) {}

    /**
     * Create a request and notify administrators.
     */
    public function create(Student $student, array $data): DocumentRequest
    {
        $request = DocumentRequest::create([
            'school_id' => $student->school_id,
            'student_id' => $student->id,
            'reference' => $this->nextReference(),
            'type' => $data['type'],
            'reason' => $data['reason'] ?? null,
            'status' => DocumentRequest::STATUS_SUBMITTED,
        ]);

        $this->notifications->sendToRole(
            'admin',
            'request_created',
            'New request',
            "{$student->user?->name} submitted a \"{$request->type}\" request.",
            ['request_id' => $request->id],
        );

        return $request->load('documents');
    }

    /**
     * Requests belonging to a student.
     *
     * @return Collection<int, DocumentRequest>
     */
    public function forStudent(Student $student): Collection
    {
        return $student->requests()->with('documents')->latest()->get();
    }

    /**
     * All requests (admin), with student details.
     *
     * @return Collection<int, DocumentRequest>
     */
    public function all(): Collection
    {
        return DocumentRequest::query()
            ->with(['student.user', 'documents'])
            ->latest()
            ->get();
    }

    /**
     * Move a request through its lifecycle and notify the student.
     */
    public function transition(DocumentRequest $request, string $status, ?string $note = null): DocumentRequest
    {
        $request->update([
            'status' => $status,
            'admin_note' => $note,
            'resolved_at' => in_array($status, [
                DocumentRequest::STATUS_READY,
                DocumentRequest::STATUS_REJECTED,
            ], true) ? now() : $request->resolved_at,
        ]);

        $message = match ($status) {
            DocumentRequest::STATUS_UNDER_REVIEW => "Your request {$request->reference} is now under review.",
            DocumentRequest::STATUS_APPROVED => "Your request {$request->reference} has been approved.",
            DocumentRequest::STATUS_READY => "Your request {$request->reference} is ready to download.",
            DocumentRequest::STATUS_REJECTED => "Your request {$request->reference} was declined.",
            default => "Your request {$request->reference} was updated.",
        };

        if ($request->student?->user) {
            $this->notifications->send(
                $request->student->user,
                'request_updated',
                'Request update',
                $message,
                ['request_id' => $request->id, 'status' => $status],
            );
        }

        return $request->load('documents');
    }

    /**
     * Generate the document, mark the request ready, notify the student.
     */
    public function generateDocument(DocumentRequest $request): DocumentRequest
    {
        $this->documents->generateForRequest($request);

        return $this->transition($request, DocumentRequest::STATUS_READY);
    }

    /**
     * Unique human-friendly reference, e.g. REQ-1045.
     */
    private function nextReference(): string
    {
        do {
            $reference = 'REQ-'.random_int(1000, 9999);
        } while (DocumentRequest::where('reference', $reference)->exists());

        return $reference;
    }
}
