<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Attempt authentication and issue a Sanctum personal access token.
     *
     * @return array{token: string, user: User}
     *
     * @throws ValidationException
     */
    public function login(string $email, string $password): array
    {
        if (! Auth::attempt(['email' => $email, 'password' => $password])) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = Auth::user();

        return [
            'token' => $user->createToken('synapse-auth-token')->plainTextToken,
            'user' => $user,
        ];
    }

    /**
     * Revoke the token that authenticated the current request.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
