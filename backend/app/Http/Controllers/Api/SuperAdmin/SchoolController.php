<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSchoolRequest;
use App\Http\Requests\Admin\UpdateSchoolRequest;
use App\Models\School;
use App\Services\SchoolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolController extends Controller
{
    public function __construct(
        private readonly SchoolService $schoolService,
    ) {}

    public function index(): JsonResponse
    {
        $schools = School::query()
            ->with('subscriptionPlan')
            ->withCount('users')
            ->latest()
            ->get();

        return response()->json(['data' => $schools]);
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
