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
     * @return array{token: string, user: User, must_change_password: bool}
     *
     * @throws ValidationException
     */
    public function login(string $email, string $password, ?string $device = null): array
    {
        if (! Auth::attempt(['email' => $email, 'password' => $password])) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        abort_if(
            $user->school && $user->school->status === 'suspended',
            403,
            'This school account has been suspended. Please contact SYNAPSE support.',
        );

        $user->forceFill(['last_login_at' => now()])->save();

        return [
            'token' => $user->createToken($device ?: 'synapse-auth-token')->plainTextToken,
            'user' => $user,
            'must_change_password' => (bool) $user->must_change_password,
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
