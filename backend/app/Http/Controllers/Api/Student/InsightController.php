<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Services\AtRiskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A student's own academic picture.
 *
 * The signals are the same ones a form teacher sees, so a student is never
 * told something their school disagrees with.
 */
class InsightController extends Controller
{
    public function __construct(
        private readonly AtRiskService $atRiskService,
    ) {}

    public function mine(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->atRiskService->mine($request->user()),
        ]);
    }
}
