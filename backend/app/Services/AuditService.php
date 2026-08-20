<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;

class AuditService
{
    public function log(
        ?School $school,
        ?User $user,
        string $action,
        string $entityType,
        ?int $entityId = null,
        array $metadata = [],
    ): AuditLog {
        return AuditLog::create([
            'school_id' => $school?->id,
            'user_id' => $user?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Activity for a single school.
     */
    public function forSchool(School $school, int $limit = 50)
    {
        return AuditLog::query()->where('school_id', $school->id)->latest('id')->take($limit)->get();
    }

    /**
     * Platform-wide activity (super admin).
     */
    public function latest(int $limit = 50)
    {
        return AuditLog::query()->with('school', 'user')->latest('id')->take($limit)->get();
    }
}
