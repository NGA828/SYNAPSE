<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Concerns\HandlesPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read side of the audit trail. Admins see their own school; the platform-wide
 * view lives under the super-admin prefix.
 */
class AuditLogController extends Controller
{
    use HandlesPagination;

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = AuditLog::query()
            ->with(['user', 'school'])
            ->where('school_id', $request->user()->school_id)
            ->when($request->query('action'), fn ($q, $action) => $q->where('action', 'like', $action.'%'))
            ->when($request->query('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->query('from'), fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->whereDate('created_at', '<=', $to));

        return AuditLogResource::collection(
            $this->paginated($query, $request, searchable: ['action', 'entity_type'], sortable: ['id', 'created_at']),
        );
    }
}
