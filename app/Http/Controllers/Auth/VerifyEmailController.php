<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class VerifyEmailController extends Controller
{
    public function __construct(protected AuthService $auth) {}

    public function show(Request $request)
    {
        $token = $request->query('token');

        $verified = $this->auth->verifyEmailToken($token);

        $base = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/').'/login';
        $url = $base.'?verified='.($verified ? '1' : '0');

        return Redirect::away($url);
    }
}
