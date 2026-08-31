<?php

namespace App\Http\Middleware;

use App\Services\AuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Authenticate
{
    public function __construct(protected AuthService $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie('swave_session');
        $user = $token ? $this->auth->getUserByToken($token) : null;

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
