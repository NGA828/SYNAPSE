<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends Controller
{
    /**
     * All subscriptions across the platform.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Subscription::query()
                ->with(['school', 'plan'])
                ->latest()
                ->get(),
        ]);
    }
}
