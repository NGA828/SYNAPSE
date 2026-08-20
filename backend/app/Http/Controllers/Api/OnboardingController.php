<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\RegisterSchoolRequest;
use App\Models\SubscriptionPlan;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly OnboardingService $onboarding,
    ) {}

    /**
     * Active plans available during onboarding (public).
     */
    public function plans(): JsonResponse
    {
        return response()->json([
            'data' => SubscriptionPlan::query()->where('status', 'active')->orderBy('price')->get(),
        ]);
    }

    /**
     * Register a school + administrator and start the free trial.
     */
    public function store(RegisterSchoolRequest $request): JsonResponse
    {
        $result = $this->onboarding->register($request->validated());

        return response()->json([
            'school' => $result['school'],
            'message' => 'Your school has been created. Sign in with your administrator account to continue.',
            'admin_email' => $result['admin']->email,
        ], 201);
    }
}
