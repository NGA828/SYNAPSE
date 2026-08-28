<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\PasswordService;
use Illuminate\Http\JsonResponse;

class PasswordController extends Controller
{
    public function __construct(
        private readonly PasswordService $passwords,
    ) {}

    /**
     * Request a reset link (public, rate limited).
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        return response()->json([
            'message' => $this->passwords->sendResetLink($request->validated('email')),
        ]);
    }

    /**
     * Complete a reset with the token from the e-mail (public, rate limited).
     */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();

        $this->passwords->reset($data['email'], $data['token'], $data['password']);

        return response()->json([
            'message' => 'Your password has been reset. You can now sign in.',
        ]);
    }

    /**
     * Rotate the password of the signed-in user (also clears the
     * must_change_password flag set on provisioned accounts).
     */
    public function change(ChangePasswordRequest $request): JsonResponse
    {
        $data = $request->validated();

        $this->passwords->change($request->user(), $data['current_password'], $data['password']);

        return response()->json([
            'message' => 'Your password has been updated.',
            'user' => $request->user()->fresh()->load(['school', 'student', 'teacher']),
        ]);
    }
}
