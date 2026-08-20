<?php

namespace App\Policies;

use App\Models\TeachingAssignment;
use App\Models\User;

class TeachingAssignmentPolicy
{
    /**
     * Only administrators manage assignments; teachers may view them.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isTeacher();
    }

    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, TeachingAssignment $assignment): bool
    {
        return $user->isAdmin()
            || ($user->isTeacher() && $assignment->teacher_id === $user->teacher?->id);
    }
}
