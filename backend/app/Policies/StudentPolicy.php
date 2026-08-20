<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    /**
     * A student may view their own profile; admins view their own school's
     * students; the super admin views everything.
     */
    public function view(User $user, Student $student): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isStudent()) {
            return $student->user_id === $user->id;
        }

        return $user->school_id !== null && $user->school_id === $student->school_id;
    }

    public function manage(User $user, Student $student): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && $user->school_id === $student->school_id;
    }
}
