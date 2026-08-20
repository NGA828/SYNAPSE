<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * The authenticated user's recent notifications and unread count.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->notificationService->forUser($request->user()));
    }

    public function read(Request $request, Notification $notification): JsonResponse
    {
        return response()->json([
            'data' => $this->notificationService->markRead($request->user(), $notification),
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $this->notificationService->markAllRead($request->user());

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
