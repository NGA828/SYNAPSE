<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Concerns\HandlesPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    use HandlesPagination;

    /**
     * Every payment on the platform, paginated and filterable by status/provider.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Payment::query()
            ->with(['school', 'subscription.plan'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('provider'), fn ($q, $provider) => $q->where('provider', $provider))
            ->when($request->query('school_id'), fn ($q, $id) => $q->where('school_id', $id));

        $payments = $this->paginated(
            $query,
            $request,
            searchable: ['reference', 'school.name'],
            sortable: ['id', 'amount', 'paid_at', 'created_at'],
        );

        return PaymentResource::collection($payments);
    }
}
