<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Password lifecycle: forgotten-password links, resets and rotations.
 */
class PasswordService
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /**
     * Send a reset link. The response never reveals whether the address
     * exists — that would be a user-enumeration hole.
     */
    public function sendResetLink(string $email): string
    {
        $status = Password::broker()->sendResetLink(['email' => $email]);

        return match ($status) {
            Password::RESET_LINK_SENT, Password::INVALID_USER => 'If that address belongs to an account, a reset link is on its way.',
            Password::RESET_THROTTLED => throw ValidationException::withMessages([
                'email' => ['Please wait a moment before requesting another reset link.'],
            ]),
            default => throw ValidationException::withMessages([
                'email' => ['We could not send a reset link. Please try again later.'],
            ]),
        };
    }

    /**
     * Complete a reset with a signed token.
     */
    public function reset(string $email, string $token, string $password): void
    {
        $status = Password::broker()->reset(
            [
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $password,
                'token' => $token,
            ],
            function (User $user) use ($password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                    'must_change_password' => false,
                    'password_changed_at' => now(),
                ])->save();

                // A reset invalidates every existing API token.
                $user->tokens()->delete();

                $this->audit->log($user->school, $user, 'password.reset', User::class, $user->id);

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => ['This password reset link is invalid or has expired.'],
            ]);
        }
    }

    /**
     * Rotate the password of the signed-in user.
     *
     * @param  bool  $revokeOtherTokens  Sign every other device out.
     */
    public function change(User $user, string $current, string $password, bool $revokeOtherTokens = true): void
    {
        if (! Hash::check($current, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Your current password is incorrect.'],
            ]);
        }

        if (Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Please choose a password you have not used before.'],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($password),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        if ($revokeOtherTokens) {
            $currentId = $user->currentAccessToken()?->id;

            $user->tokens()->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))->delete();
        }

        $this->audit->log($user->school, $user, 'password.changed', User::class, $user->id);
    }
}
