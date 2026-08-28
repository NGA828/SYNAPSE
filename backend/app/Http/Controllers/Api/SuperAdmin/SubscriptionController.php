<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Concerns\HandlesPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubscriptionController extends Controller
{
    use HandlesPagination;

    /**
     * Every subscription on the platform, paginated and filterable by status.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Subscription::query()
            ->with(['school', 'plan'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('plan_id'), fn ($q, $id) => $q->where('plan_id', $id));

        $subscriptions = $this->paginated(
            $query,
            $request,
            searchable: ['school.name'],
            sortable: ['id', 'start_date', 'end_date', 'created_at'],
        );

        return SubscriptionResource::collection($subscriptions);
    }
}
