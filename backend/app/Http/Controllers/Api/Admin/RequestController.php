<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Concerns\HandlesPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentRequestResource;
use App\Http\Requests\Admin\UpdateRequestStatusRequest;
use App\Models\DocumentRequest;
use App\Services\RequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RequestController extends Controller
{
    use HandlesPagination;

    public function __construct(
        private readonly RequestService $requestService,
    ) {}

    /**
     * Paginated request queue, filterable by status and searchable by
     * reference, type or student name.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = DocumentRequest::query()
            ->with(['student.user', 'documents'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status));

        $requests = $this->paginated(
            $query,
            $request,
            searchable: ['reference', 'type', 'student.matricule'],
            sortable: ['id', 'status', 'created_at', 'resolved_at'],
        );

        return DocumentRequestResource::collection($requests);
    }

    public function status(UpdateRequestStatusRequest $request, DocumentRequest $documentRequest): JsonResponse
    {
        $data = $request->validated();

        return response()->json([
            'data' => DocumentRequestResource::make($this->requestService->transition(
                $documentRequest,
                $data['status'],
                $data['admin_note'] ?? null,
            )->load(['student.user', 'documents'])),
        ]);
    }

    public function generateDocument(Request $request, DocumentRequest $documentRequest): JsonResponse
    {
        return response()->json([
            'data' => DocumentRequestResource::make(
                $this->requestService->generateDocument($documentRequest, $request->user())
                    ->load(['student.user', 'documents']),
            ),
        ]);
    }
}
