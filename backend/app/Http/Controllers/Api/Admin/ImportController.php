<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreImportRequest;
use App\Services\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function __construct(
        private readonly ImportService $importService,
    ) {}

    /**
     * Bulk-import students or teachers from parsed CSV/JSON rows.
     */
    public function store(StoreImportRequest $request): JsonResponse
    {
        $data = $request->validated();

        $result = $data['type'] === 'teachers'
            ? $this->importService->importTeachers($request->user()->school, $data['rows'], $request->user())
            : $this->importService->importStudents($request->user()->school, $data['rows'], $request->user());

        return response()->json($result);
    }
}
