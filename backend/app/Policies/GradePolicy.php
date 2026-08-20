<?php

namespace App\Policies;

use App\Models\Grade;
use App\Models\User;

class GradePolicy
{
    public function view(User $user, Grade $grade): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isStudent()) {
            return $grade->student?->user_id === $user->id;
        }

        return $user->school_id !== null && $user->school_id === $grade->school_id;
    }

    /**
     * Only the assigned teacher (or an admin of the same school) may modify
     * a grade. Tenant ownership is enforced by the global scope in addition
     * to this check.
     */
    public function manage(User $user, Grade $grade): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isAdmin()) {
            return $user->school_id === $grade->school_id;
        }

        if ($user->isTeacher()) {
            return $grade->teacher_id === $user->teacher?->id;
        }

        return false;
    }
}
