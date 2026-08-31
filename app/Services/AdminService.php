<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;

class AdminService
{
    public function alerts(): array
    {
        $pending = Order::where('status', OrderStatus::PENDING_PAYMENT)->get();

        $new = $pending->where('createdAt', '>=', now()->subHours(24));
        $overdue = $pending->where('createdAt', '<', now()->subHours(24));

        return [
            'newPending' => [
                'count' => $new->count(),
                'orders' => $new->values(),
            ],
            'overdue' => [
                'count' => $overdue->count(),
                'orders' => $overdue->values(),
            ],
            'totalPending' => $pending->count(),
        ];
    }
}
