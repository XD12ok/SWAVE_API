<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LogoutController extends Controller
{
    public function __construct(protected AuthService $auth) {}

    public function destroy(Request $request)
    {
        $token = $request->cookie('swave_session');
        $this->auth->logout($token);

        $cookie = Cookie::forget('swave_session');

        return response()->json([
            'message' => 'Logged out',
        ])->cookie($cookie);
    }
}
