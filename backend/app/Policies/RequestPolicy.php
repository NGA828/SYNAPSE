<?php

namespace App\Policies;

use App\Models\DocumentRequest;
use App\Models\User;

class RequestPolicy
{
    public function view(User $user, DocumentRequest $request): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isStudent()) {
            return $request->student?->user_id === $user->id;
        }

        return $user->school_id !== null && $user->school_id === $request->school_id;
    }

    public function manage(User $user, DocumentRequest $request): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && $user->school_id === $request->school_id;
    }
}
