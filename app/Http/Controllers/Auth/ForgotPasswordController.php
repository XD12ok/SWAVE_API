<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Services\AuthService;

class ForgotPasswordController extends Controller
{
    public function __construct(protected AuthService $auth) {}

    public function store(ForgotPasswordRequest $request)
    {
        $data = $request->validated();

        // Generic response to prevent user enumeration.
        // createPasswordResetToken sends the email when the user exists.
        $this->auth->createPasswordResetToken($data['email']);

        return response()->json([
            'message' => 'If the email exists, a password reset link has been sent.',
        ], 200);
    }
}
