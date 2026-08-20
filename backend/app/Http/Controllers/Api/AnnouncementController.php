<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function __construct(
        private readonly AnnouncementService $announcementService,
    ) {}

    /**
     * Announcements visible to the authenticated user (role-filtered).
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->announcementService->forUser($request->user()),
        ]);
    }
}
