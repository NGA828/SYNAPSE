<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAnnouncementRequest;
use App\Services\AnnouncementService;
use Illuminate\Http\JsonResponse;

class AnnouncementController extends Controller
{
    public function __construct(
        private readonly AnnouncementService $announcementService,
    ) {}

    public function store(StoreAnnouncementRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->announcementService->create($request->user(), $request->validated()),
        ], 201);
    }
}
