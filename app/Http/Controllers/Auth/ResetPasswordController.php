<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\AuthService;

class ResetPasswordController extends Controller
{
    public function __construct(protected AuthService $auth) {}

    public function store(ResetPasswordRequest $request)
    {
        $data = $request->validated();

        $ok = $this->auth->resetPassword($data['token'], $data['password']);

        if (! $ok) {
            return response()->json([
                'message' => 'Invalid or expired token',
            ], 422);
        }

        return response()->json([
            'message' => 'Password reset successful',
        ], 200);
    }
}
