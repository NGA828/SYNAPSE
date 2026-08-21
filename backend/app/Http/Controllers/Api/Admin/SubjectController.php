<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSubjectRequest;
use App\Http\Requests\Admin\UpdateSubjectRequest;
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

    public function update(UpdateSubjectRequest $request, Subject $subject): JsonResponse
    {
        $subject->update($request->validated());

        return response()->json(['data' => $subject->fresh()]);
    }

    public function destroy(Subject $subject): JsonResponse
    {
        $subject->delete();

        return response()->json(['message' => 'Subject removed.']);
    }
}
