<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSemesterRequest;
use App\Models\AcademicYear;
use App\Models\Semester;
use App\Services\SemesterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SemesterController extends Controller
{
    public function __construct(
        private readonly SemesterService $semesterService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $year = AcademicYear::current();

        return response()->json([
            'data' => $year ? $this->semesterService->forYear($year) : [],
            'academic_year' => $year,
        ]);
    }

    public function store(StoreSemesterRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->semesterService->create($request->user()->school, $request->validated()),
        ], 201);
    }

    public function activate(Semester $semester): JsonResponse
    {
        return response()->json([
            'data' => $this->semesterService->activate($semester),
        ]);
    }

    public function destroy(Semester $semester): JsonResponse
    {
        $this->semesterService->delete($semester);

        return response()->json(['message' => 'Semester removed.']);
    }
}
