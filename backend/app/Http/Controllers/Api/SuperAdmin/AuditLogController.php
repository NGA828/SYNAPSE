<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Concerns\HandlesPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController extends Controller
{
    use HandlesPagination;

    /**
     * Platform-wide activity, filterable by school, actor and action.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = AuditLog::query()
            ->with(['user', 'school'])
            ->when($request->query('school_id'), fn ($q, $id) => $q->where('school_id', $id))
            ->when($request->query('action'), fn ($q, $action) => $q->where('action', 'like', $action.'%'))
            ->when($request->query('user_id'), fn ($q, $id) => $q->where('user_id', $id));

        return AuditLogResource::collection(
            $this->paginated($query, $request, searchable: ['action', 'entity_type'], sortable: ['id', 'created_at']),
        );
    }
}
