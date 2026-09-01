<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Concerns\HandlesPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentRequestResource;
use App\Http\Requests\Admin\UpdateRequestStatusRequest;
use App\Models\DocumentRequest;
use App\Services\DocumentTypeService;
use App\Services\RequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RequestController extends Controller
{
    use HandlesPagination;

    public function __construct(
        private readonly RequestService $requestService,
        private readonly DocumentTypeService $documentTypes,
    ) {}

    /**
     * Paginated request queue, filterable by status and searchable by
     * reference, type or student name.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = DocumentRequest::query()
            ->with(['student.user', 'documents'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            /*
            | The queue's real worklist: requests a template cannot answer. Kept
            | as a NOT IN over the auto-generatable list rather than a stored
            | flag, so it stays correct as the list changes.
            */
            ->when(
                $request->boolean('needs_human'),
                fn ($q) => $q->whereNotIn('type', DocumentRequest::AUTO_GENERATABLE_TYPES),
            );

        $requests = $this->paginated(
            $query,
            $request,
            searchable: ['reference', 'type', 'student.matricule'],
            sortable: ['id', 'status', 'created_at', 'resolved_at'],
        );

        return DocumentRequestResource::collection($requests);
    }

    /**
     * Counters for the queue header, so an administrator can see how much of the
     * backlog is instant and how much needs them.
     *
     * @return array<string, mixed>
     */
    public function triageSummary(): JsonResponse
    {
        $counts = DocumentRequest::query()
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $needsHuman = 0;
        $auto = 0;

        foreach ($counts as $type => $total) {
            $this->documentTypes->canAutoGenerate((string) $type) ? $auto += $total : $needsHuman += $total;
        }

        return response()->json([
            'data' => [
                'total' => (int) $counts->sum(),
                'auto_generatable' => $auto,
                'needs_human' => $needsHuman,
                'catalogue' => $this->documentTypes->catalogue(),
            ],
        ]);
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
