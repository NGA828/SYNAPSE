<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreImportPreviewRequest;
use App\Http\Requests\Admin\StoreImportRequest;
use App\Services\ImportMappingService;
use App\Services\ImportService;
use Illuminate\Http\JsonResponse;

class ImportController extends Controller
{
    public function __construct(
        private readonly ImportService $importService,
        private readonly ImportMappingService $mapping,
    ) {}

    /**
     * Bulk-import students or teachers from parsed CSV/JSON rows.
     *
     * Two shapes are accepted. Rows already keyed by canonical field names go
     * through unchanged, exactly as before. Rows keyed by the file's own headers
     * come with the `mapping` the administrator confirmed in the preview, and
     * are rewritten first.
     */
    public function store(StoreImportRequest $request): JsonResponse
    {
        $data = $request->validated();

        $rows = isset($data['mapping'])
            ? $this->mapping->applyMapping($data['rows'], $data['mapping'], $data['type'])
            : $data['rows'];

        $result = $data['type'] === 'teachers'
            ? $this->importService->importTeachers($request->user()->school, $rows, $request->user())
            : $this->importService->importStudents($request->user()->school, $rows, $request->user());

        return response()->json($result);
    }

    /**
     * Describe what an import would do, without doing it.
     *
     * Writes nothing. The response says which column maps to which field, which
     * class each pupil resolves to, and which rows would fail — so a French
     * spreadsheet no longer produces a wall of per-row errors to decipher.
     */
    public function preview(StoreImportPreviewRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json([
            'data' => $this->mapping->preview(
                $request->user()->school,
                $data['rows'],
                $data['type'] ?? 'students',
            ),
        ]);
    }
}
