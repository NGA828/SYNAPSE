<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\SchoolService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private readonly SchoolService $schoolService,
        private readonly AuditService $audit,
    ) {}

    /**
     * Platform-wide statistics for the super admin dashboard.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'stats' => $this->schoolService->platformStats(),
            'recent_activity' => $this->audit->latest(20),
        ]);
    }
}
