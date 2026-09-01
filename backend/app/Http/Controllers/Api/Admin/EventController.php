<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEventRequest;
use App\Http\Requests\Admin\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(
        private readonly EventService $eventService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search') ? (string) $request->query('search') : null;

        return response()->json(
            EventResource::collection($this->eventService->forAdmin(15, $search))
                ->response()
                ->getData(true),
        );
    }

    public function store(StoreEventRequest $request): JsonResponse
    {
        $event = $this->eventService->create($request->user(), $request->validated());

        return response()->json(['data' => EventResource::make($event)], 201);
    }

    public function show(Event $event): JsonResponse
    {
        return response()->json([
            'data' => EventResource::make($event->load('author')),
        ]);
    }

    public function update(UpdateEventRequest $request, Event $event): JsonResponse
    {
        $event = $this->eventService->update($request->user(), $event, $request->validated());

        return response()->json(['data' => EventResource::make($event)]);
    }

    public function publish(Request $request, Event $event): JsonResponse
    {
        $event = $this->eventService->publish($request->user(), $event);

        return response()->json(['data' => EventResource::make($event)]);
    }

    public function unpublish(Request $request, Event $event): JsonResponse
    {
        $event = $this->eventService->unpublish($request->user(), $event);

        return response()->json(['data' => EventResource::make($event)]);
    }

    public function destroy(Request $request, Event $event): JsonResponse
    {
        $this->eventService->delete($request->user(), $event);

        return response()->json(['message' => 'Event deleted.']);
    }
}
