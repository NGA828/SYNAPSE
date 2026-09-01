<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Message\StartConversationRequest;
use App\Http\Requests\Message\StoreMessageRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Http\Resources\UserBriefResource;
use App\Models\Conversation;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Direct messages. Available to every role, so the routes sit in the shared
 * auth + tenant group; who may open a thread with whom is MessageService's
 * decision rather than a route's.
 */
class MessageController extends Controller
{
    public function __construct(
        private readonly MessageService $messageService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);

        return response()->json(
            ConversationResource::collection(
                $this->messageService->conversationsFor(
                    $request->user(),
                    min(max($perPage, 1), 50),
                ),
            )->response()->getData(true),
        );
    }

    /**
     * Open (or resume) a thread with another user.
     */
    public function store(StartConversationRequest $request): JsonResponse
    {
        $conversation = $this->messageService->conversationWith(
            $request->user(),
            (int) $request->validated('user_id'),
        );

        return response()->json([
            'data' => ConversationResource::make($conversation->load(['participantA', 'participantB'])),
        ], 201);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 50);

        return response()->json(
            MessageResource::collection(
                $this->messageService->threadFor(
                    $request->user(),
                    $conversation,
                    min(max($perPage, 1), 100),
                ),
            )->response()->getData(true),
        );
    }

    public function send(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $message = $this->messageService->send(
            $request->user(),
            $conversation,
            $request->validated('body'),
        );

        return response()->json(['data' => MessageResource::make($message)], 201);
    }

    /**
     * Explicit read receipt, for clients that open a thread without paging it.
     */
    public function read(Request $request, Conversation $conversation): JsonResponse
    {
        return response()->json([
            'marked' => $this->messageService->markRead($request->user(), $conversation),
        ]);
    }

    /**
     * The sidebar badge.
     */
    public function unread(Request $request): JsonResponse
    {
        return response()->json([
            'unread' => $this->messageService->unreadCountFor($request->user()),
        ]);
    }

    /**
     * Who this user is allowed to start a conversation with.
     */
    public function recipients(Request $request): JsonResponse
    {
        $users = $this->messageService->recipientsFor(
            $request->user(),
            $request->query('search') ? (string) $request->query('search') : null,
        );

        return response()->json(['data' => UserBriefResource::collection($users)]);
    }
}
