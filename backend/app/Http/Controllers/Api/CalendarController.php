<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CalendarController extends Controller
{
    /**
     * Widest window the endpoint will accept, so a client cannot ask for a
     * decade of expanded timetable rows in one call.
     */
    private const MAX_SPAN_DAYS = 92;

    public function __construct(
        private readonly CalendarService $calendarService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = $validated['from'] ?? now()->startOfWeek()->toDateString();
        $to = $validated['to'] ?? now()->endOfWeek()->toDateString();

        // Clamp rather than 422: a bad span is a client mistake worth
        // correcting, not one worth blocking a page load over.
        $to = Carbon::parse($from)
            ->addDays(self::MAX_SPAN_DAYS - 1)
            ->min(Carbon::parse($to))
            ->toDateString();

        return response()->json([
            'from' => $from,
            'to' => $to,
            'data' => $this->calendarService->itemsFor($request->user(), $from, $to),
        ]);
    }

    /**
     * The dashboard strip: just today.
     */
    public function today(Request $request): JsonResponse
    {
        return response()->json([
            'date' => now()->toDateString(),
            'data' => $this->calendarService->todayFor($request->user()),
        ]);
    }
}
