<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AccountService
{
    public function updateProfile(User $user, array $data): User
    {
        $user->update([
            'name' => $data['name'] ?? $user->name,
            'phone' => $data['phone'] ?? $user->phone,
        ]);

        return $user->fresh();
    }

    public function updatePassword(User $user, string $current, string $new): bool
    {
        if (! Hash::check($current, $user->passwordHash)) {
            return false;
        }

        $user->update([
            'passwordHash' => Hash::make($new),
        ]);

        return true;
    }

    public function orders(User $user)
    {
        return Order::where('userId', $user->_id)
            ->orderBy('createdAt', 'desc')
            ->get();
    }
}
