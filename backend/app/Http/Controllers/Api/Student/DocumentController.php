<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\Student;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentService $documentService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $student = $this->student($request);

        return response()->json([
            'data' => DocumentResource::collection($this->documentService->forStudent($student)),
        ]);
    }

    public function download(Request $request, Document $document): StreamedResponse
    {
        $student = $this->student($request);

        abort_unless(
            $document->student_id === $student->id,
            403,
            'This document does not belong to you.',
        );

        return $this->documentService->download($document);
    }

    private function student(Request $request): Student
    {
        $student = $request->user()->student;

        abort_unless(
            $student instanceof Student,
            403,
            'No student profile is attached to this account.',
        );

        return $student;
    }
}
