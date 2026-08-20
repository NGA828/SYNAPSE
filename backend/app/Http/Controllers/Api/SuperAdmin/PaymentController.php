<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    /**
     * All payments across the platform.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Payment::query()
                ->with(['school', 'subscription.plan'])
                ->latest()
                ->get(),
        ]);
    }
}
