<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only events for students and teachers. Drafts never appear here, and
 * the audience filter is applied server-side so a students-only event cannot
 * be read by asking for it directly.
 */
class EventController extends Controller
{
    public function __construct(
        private readonly EventService $eventService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'type' => ['nullable', 'string', 'in:'.implode(',', Event::TYPES)],
        ]);

        $events = $this->eventService->upcomingFor(
            $request->user(),
            (int) ($validated['days'] ?? 60),
            $validated['type'] ?? null,
        );

        return response()->json(['data' => EventResource::collection($events)]);
    }

    public function show(Request $request, Event $event): JsonResponse
    {
        abort_unless($event->is_published, 404, 'Event not found.');

        $query = Event::query()->visibleToRole($request->user()->role);

        abort_unless($query->whereKey($event->id)->exists(), 404, 'Event not found.');

        return response()->json(['data' => EventResource::make($event)]);
    }
}
