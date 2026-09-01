<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\AtRiskFilterRequest;
use App\Models\Student;
use App\Services\AnalyticsService;
use App\Services\AtRiskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Academic analytics and the pastoral register.
 *
 * One controller serves both the admin and teacher route groups: the services
 * scope everything by the caller, so duplicating the controller per role would
 * only create two places for the definitions to drift apart.
 */
class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
        private readonly AtRiskService $atRiskService,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->analyticsService->overview($request->user()),
        ]);
    }

    /**
     * Flagged students, worst first. Paginated because a large school can have
     * a long register and the screen is a worklist, not a scroll.
     */
    public function register(AtRiskFilterRequest $request): JsonResponse
    {
        $entries = $this->atRiskService->register($request->user(), $request->validated());

        $perPage = (int) $request->validated('per_page', 15) ?: 15;
        $page = (int) $request->validated('page', 1) ?: 1;

        $paginator = new LengthAwarePaginator(
            $entries->forPage($page, $perPage)->values(),
            $entries->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return response()->json($this->envelope($paginator));
    }

    /**
     * One student's full record. Authorisation is the service's job, because
     * "may this caller see this pupil?" depends on enrollment, not on role.
     */
    public function student(Request $request, Student $student): JsonResponse
    {
        return response()->json([
            'data' => $this->atRiskService->profile($request->user(), $student),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function envelope(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->items(),
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
