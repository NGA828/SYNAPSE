<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlanRequest;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => SubscriptionPlan::query()->orderBy('price')->get(),
        ]);
    }

    public function store(StorePlanRequest $request): JsonResponse
    {
        return response()->json([
            'data' => SubscriptionPlan::create($request->validated()),
        ], 201);
    }

    public function update(StorePlanRequest $request, SubscriptionPlan $plan): JsonResponse
    {
        $plan->update($request->validated());

        return response()->json(['data' => $plan->fresh()]);
    }
}
