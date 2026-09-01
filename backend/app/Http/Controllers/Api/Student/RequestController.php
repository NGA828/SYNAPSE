<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreRequestRequest;
use App\Models\Student;
use App\Services\DocumentTypeService;
use App\Services\RequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    public function __construct(
        private readonly RequestService $requestService,
        private readonly DocumentTypeService $documentTypes,
    ) {}

    /**
     * The documents a student may ask for.
     *
     * Served rather than hard-coded in the client so the form can only ever
     * offer what the server will accept — the free-text field it replaces is
     * what allowed a request for a document no template could produce.
     *
     * @return array<string, mixed>
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'data' => $this->documentTypes->catalogue(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $student = $this->student($request);

        return response()->json([
            'data' => $this->requestService->forStudent($student),
        ]);
    }

    public function store(StoreRequestRequest $request): JsonResponse
    {
        $student = $this->student($request);

        return response()->json([
            'data' => $this->requestService->create($student, $request->validated()),
        ], 201);
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
