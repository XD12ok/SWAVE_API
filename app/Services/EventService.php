<?php

namespace App\Services;

use App\Models\Order;

class EventService
{
    public function snapshot(): array
    {
        $pending = Order::where('status', \App\Enums\OrderStatus::PENDING_PAYMENT)->count();

        return [
            'type' => 'snapshot',
            'pendingOrders' => $pending,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
