<?php

namespace App\Services;

use App\Models\Order;

class KasirService
{
    public function __construct(
        protected OrderService $orderService,
    ) {}

    /**
     * Create a cashier (walk-in) order, delegating stock consumption to OrderService.
     */
    public function store(array $data, ?string $userId = null): Order
    {
        $payment = $data['payment'] ?? [];
        $paymentMethod = strtoupper((string) ($payment['method'] ?? 'CASH'));

        if (!in_array($paymentMethod, ['CASH', 'QRIS'], true)) {
            throw new \InvalidArgumentException('Metode pembayaran tidak valid');
        }

        $opts = [];

        if (array_key_exists('cashReceived', $payment)) {
            $opts['cashReceived'] = (int) $payment['cashReceived'];
        }

        if (!empty($data['buyer']['name'])) {
            $opts['buyerName'] = $data['buyer']['name'];
        }

        // Cashier persona is captured separately; the shared cashier flow keeps
        // history lookups by cashierName on the order.
        if (!empty($data['cashierName']) || !empty($data['buyer']['cashierName'])) {
            $opts['cashierName'] = $data['cashierName'] ?? $data['buyer']['cashierName'];
        }

        $order = $this->orderService->createCashierOrder(
            $data['items'],
            $paymentMethod,
            $opts,
        );

        if ($userId) {
            $order->user = $userId;
            $order->save();
        }

        return $order;
    }
}