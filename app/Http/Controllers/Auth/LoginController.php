<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LoginController extends Controller
{
    public function __construct(protected AuthService $auth) {}

    public function store(LoginRequest $request)
    {
        $data = $request->validated();

        try {
            $result = $this->auth->login($data['email'], $data['password']);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 401);
        }

        $cookie = Cookie::make(
            'swave_session',
            $result['token'],
            config('session.lifetime', 43200),
            '/',
            config('session.domain'),
            request()->secure(),
            true,
            false,
            'Lax'
        );

        return response()->json([
            'user' => $result['user'],
        ])->cookie($cookie);
    }

    public function me(Request $request)
    {
        $token = $request->cookie('swave_session');
        $user = $token ? $this->auth->getUserByToken($token) : null;

        if (! $user) {
            return response()->json([
                'user' => null,
            ], 200);
        }

        return response()->json([
            'user' => $user,
        ]);
    }
}
