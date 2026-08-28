<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
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
        $result = $this->notificationService->forUser(
            $request->user(),
            (int) $request->query('per_page', 20),
        );

        return response()->json(
            NotificationResource::collection($result['data'])
                ->additional(['unread_count' => $result['unread_count']])
                ->response()
                ->getData(true),
        );
    }

    public function read(Request $request, Notification $notification): JsonResponse
    {
        return response()->json([
            'data' => NotificationResource::make(
                $this->notificationService->markRead($request->user(), $notification),
            ),
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $this->notificationService->markAllRead($request->user());

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
