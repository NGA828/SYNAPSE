<?php

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\Student;
use App\Models\User;
use App\Notifications\DocumentReadyNotification;
use App\Notifications\RequestStatusChangedNotification;
use App\Notifications\RequestSubmittedNotification;
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

        $this->notifications->notifyRole(
            $student->school_id,
            User::ROLE_ADMIN,
            new RequestSubmittedNotification($request, $student->user?->name ?? 'A student'),
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

        $this->notifications->notify(
            $request->student?->user,
            new RequestStatusChangedNotification($request, $status),
        );

        return $request->load('documents');
    }

    /**
     * Generate the document, mark the request ready, notify the student.
     */
    public function generateDocument(DocumentRequest $request, ?User $actor = null): DocumentRequest
    {
        $document = $this->documents->generateForRequest($request, $actor);

        $request = $this->transition($request, DocumentRequest::STATUS_READY);

        $this->notifications->notify(
            $request->student?->user,
            new DocumentReadyNotification($document),
        );

        return $request;
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
