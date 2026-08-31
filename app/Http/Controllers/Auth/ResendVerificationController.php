<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResendVerificationRequest;
use App\Models\User;
use App\Services\AuthService;

class ResendVerificationController extends Controller
{
    public function __construct(protected AuthService $auth) {}

    public function store(ResendVerificationRequest $request)
    {
        $data = $request->validated();

        // Generic response to prevent user enumeration
        $user = User::where('email', $data['email'])->first();

        if ($user && ! $user->emailVerified) {
            try {
                $this->auth->createVerificationToken((string) $user->_id);
            } catch (\Throwable $e) {
                // fail silently
            }
        }

        return response()->json([
            'message' => 'If the email exists, a verification link has been sent.',
        ], 200);
    }
}
