<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;

class AttendancePolicy
{
    public function view(User $user, Attendance $attendance): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isStudent()) {
            return $attendance->student?->user_id === $user->id;
        }

        return $user->school_id !== null && $user->school_id === $attendance->school_id;
    }

    public function manage(User $user, Attendance $attendance): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isAdmin()) {
            return $user->school_id === $attendance->school_id;
        }

        if ($user->isTeacher()) {
            return $attendance->teacher_id === $user->teacher?->id;
        }

        return false;
    }
}
