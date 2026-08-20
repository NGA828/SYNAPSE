<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSubjectRequest;
use App\Models\Subject;
use App\Services\AdminAcademicService;
use Illuminate\Http\JsonResponse;

class SubjectController extends Controller
{
    public function __construct(
        private readonly AdminAcademicService $academicService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Subject::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreSubjectRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->academicService->createSubject($request->user()->school, $request->validated()),
        ], 201);
    }
}
