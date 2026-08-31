<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SignupRequest;
use App\Services\AuthService;

class SignupController extends Controller
{
    public function __construct(protected AuthService $auth) {}

    public function store(SignupRequest $request)
    {
        $data = $request->validated();

        try {
            $user = $this->auth->signup(
                $data['name'],
                $data['email'],
                $data['phone'] ?? '',
                $data['password']
            );
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        }

        // Best-effort verification email (uses userId)
        try {
            $this->auth->createVerificationToken((string) $user->_id);
        } catch (\Throwable $e) {
            // do not fail registration if email sending fails
        }

        return response()->json([
            'user' => $user,
        ], 201);
    }
}
