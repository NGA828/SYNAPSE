<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRequestStatusRequest;
use App\Models\DocumentRequest;
use App\Services\RequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    public function __construct(
        private readonly RequestService $requestService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->requestService->all(),
        ]);
    }

    public function status(UpdateRequestStatusRequest $request, DocumentRequest $documentRequest): JsonResponse
    {
        $data = $request->validated();

        return response()->json([
            'data' => $this->requestService->transition(
                $documentRequest,
                $data['status'],
                $data['admin_note'] ?? null,
            ),
        ]);
    }

    public function generateDocument(Request $request, DocumentRequest $documentRequest): JsonResponse
    {
        return response()->json([
            'data' => $this->requestService->generateDocument($documentRequest),
        ]);
    }
}
