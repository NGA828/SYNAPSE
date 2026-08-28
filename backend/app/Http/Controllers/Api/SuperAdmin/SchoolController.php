<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Concerns\HandlesPagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSchoolRequest;
use App\Http\Requests\Admin\UpdateSchoolRequest;
use App\Models\School;
use App\Services\SchoolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolController extends Controller
{
    use HandlesPagination;

    public function __construct(
        private readonly SchoolService $schoolService,
    ) {}

    /**
     * Paginated tenant directory, searchable by name/slug/email.
     */
    public function index(Request $request): JsonResponse
    {
        $query = School::query()
            ->with('subscriptionPlan')
            ->withCount('users')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status));

        $schools = $this->paginated(
            $query,
            $request,
            searchable: ['name', 'slug', 'email', 'code'],
            sortable: ['id', 'name', 'created_at', 'subscription_expires_at'],
        );

        // The logo is a base64 blob — never ship it in a list payload.
        $schools->getCollection()->each->makeHidden('logo');

        return response()->json($schools->toArray());
    }

    public function store(StoreSchoolRequest $request): JsonResponse
    {
        $school = $this->schoolService->create($request->validated(), $request->user());

        return response()->json(['data' => $school->load('subscriptionPlan')], 201);
    }

    public function show(School $school): JsonResponse
    {
        return response()->json([
            'data' => $school->load(['subscriptionPlan', 'subscription.plan']),
            'users_count' => $school->users()->count(),
        ]);
    }

    public function update(UpdateSchoolRequest $request, School $school): JsonResponse
    {
        $school = $this->schoolService->update($school, $request->validated(), $request->user());

        return response()->json(['data' => $school->load('subscriptionPlan')]);
    }

    /**
     * Activate / suspend / deactivate a school (never destroys data).
     */
    public function status(Request $request, School $school): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:active,trial,suspended,expired'],
        ]);

        $school = $this->schoolService->setStatus($school, $data['status'], $request->user());

        return response()->json(['data' => $school->load('subscriptionPlan')]);
    }

    /**
     * The users belonging to a school (super admin inspection).
     */
    public function users(School $school): JsonResponse
    {
        return response()->json(['data' => $this->schoolService->users($school)]);
    }
}
