<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentRequest;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentService
{
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
     * Generate the official document backing a request (Phase 4 writes a
     * signed text placeholder; a PDF renderer can be swapped in later).
     */
    public function generateForRequest(DocumentRequest $request): Document
    {
        $student = $request->student()->with('user')->first();

        $fileName = Str::slug($request->type).'-'.Str::lower($request->reference).'.pdf';
        $path = 'documents/'.$fileName;

        $content = implode("\n", [
            'SYNAPSE — OFFICIAL DOCUMENT',
            '=============================',
            'Type: '.$request->type,
            'Reference: '.$request->reference,
            'Student: '.($student?->user?->name ?? '—'),
            'Matricule: '.($student?->matricule ?? '—'),
            'Issued: '.now()->toDateString(),
            '',
            'This document was generated and issued through SYNAPSE.',
        ]);

        Storage::disk('public')->put($path, $content);

        return Document::create([
            'request_id' => $request->id,
            'student_id' => $request->student_id,
            'title' => $request->type,
            'file_name' => $fileName,
            'mime_type' => 'application/pdf',
            'size' => strlen($content),
            'disk' => 'public',
            'path' => $path,
        ]);
    }

    /**
     * Stream a document to the browser as a download.
     */
    public function download(Document $document): StreamedResponse
    {
        return Storage::disk($document->disk)->download($document->path, $document->file_name);
    }
}
