<?php

namespace App\Policies;

use App\Models\Teacher;
use App\Models\User;

class TeacherPolicy
{
    public function view(User $user, Teacher $teacher): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->school_id !== null && $user->school_id === $teacher->school_id;
    }

    public function manage(User $user, Teacher $teacher): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && $user->school_id === $teacher->school_id;
    }
}
