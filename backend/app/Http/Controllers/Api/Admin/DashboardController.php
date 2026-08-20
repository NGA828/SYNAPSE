<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAcademicService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AdminAcademicService $academicService,
    ) {}

    /**
     * Administrator dashboard summary.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'summary' => $this->academicService->summary(),
        ]);
    }
}
