<?php

namespace App\Services;

use App\Models\Order;

class PaymentService
{
    public function __construct(
        protected OrderService $orderService,
    ) {}

    public function show(string $id): ?Order
    {
        return Order::find($id);
    }

    public function update(string $id, array $data): ?Order
    {
        $order = Order::find($id);

        if (! $order) {
            return null;
        }

        $payment = $order->payment ?? [];

        if (array_key_exists('method', $data)) {
            $payment['method'] = $data['method'];
        }

        if (array_key_exists('proof', $data)) {
            $payment['proof'] = $data['proof'];
        }

        $order->payment = $payment;
        $order->updatedAt = now();

        // Delegate status side effects (reserve consumption, release, revive)
        // to OrderService so payment and inventory stay consistent on one path.
        $this->orderService->updateOrderPayment($id, $data);

        $order->save();

        return $order->fresh();
    }
}