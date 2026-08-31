<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Requests\Api\UpdatePasswordRequest;
use App\Services\AccountService;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(protected AccountService $account) {}

    public function updateProfile(UpdateProfileRequest $request)
    {
        $data = $request->validated();

        $user = $this->account->updateProfile($request->user(), $data);

        return response()->json(['user' => $user]);
    }

    public function updatePassword(UpdatePasswordRequest $request)
    {
        $data = $request->validated();

        $ok = $this->account->updatePassword(
            $request->user(),
            $data['current_password'],
            $data['password']
        );

        if (! $ok) {
            return response()->json([
                'message' => 'Current password is incorrect',
            ], 422);
        }

        return response()->json(['message' => 'Password updated']);
    }

    public function orders(Request $request)
    {
        $orders = $this->account->orders($request->user());

        return response()->json(['orders' => $orders]);
    }
}
