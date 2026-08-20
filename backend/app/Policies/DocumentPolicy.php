<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isStudent()) {
            return $document->student?->user_id === $user->id;
        }

        return $user->school_id !== null && $user->school_id === $document->school_id;
    }
}
