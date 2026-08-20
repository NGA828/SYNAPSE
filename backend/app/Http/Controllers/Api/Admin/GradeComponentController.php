<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGradeComponentRequest;
use App\Models\GradeComponent;
use App\Services\GradeComponentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradeComponentController extends Controller
{
    public function __construct(
        private readonly GradeComponentService $componentService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->componentService->index($request->user()->school));
    }

    public function store(StoreGradeComponentRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->componentService->create($request->user()->school, $request->validated()),
        ], 201);
    }

    public function update(StoreGradeComponentRequest $request, GradeComponent $component): JsonResponse
    {
        return response()->json([
            'data' => $this->componentService->update($component, $request->validated()),
        ]);
    }

    public function destroy(GradeComponent $component): JsonResponse
    {
        $this->componentService->delete($component);

        return response()->json(['message' => 'Component removed.']);
    }
}
