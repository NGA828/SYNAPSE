<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSchoolClassRequest;
use App\Models\SchoolClass;
use App\Services\AdminAcademicService;
use Illuminate\Http\JsonResponse;

class SchoolClassController extends Controller
{
    public function __construct(
        private readonly AdminAcademicService $academicService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => SchoolClass::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreSchoolClassRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->academicService->createClass($request->user()->school, $request->validated()),
        ], 201);
    }
}
