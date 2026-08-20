<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAcademicYearRequest;
use App\Models\AcademicYear;
use App\Services\AdminAcademicService;
use Illuminate\Http\JsonResponse;

class AcademicYearController extends Controller
{
    public function __construct(
        private readonly AdminAcademicService $academicService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => AcademicYear::query()->orderByDesc('name')->get(),
        ]);
    }

    public function store(StoreAcademicYearRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->academicService->createYear($request->user()->school, $request->validated()),
        ], 201);
    }

    public function activate(AcademicYear $academicYear): JsonResponse
    {
        return response()->json([
            'data' => $this->academicService->activate($academicYear),
        ]);
    }
}
