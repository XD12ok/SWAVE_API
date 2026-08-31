<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShippingMethod;
use App\Enums\InventoryReason;
use App\Models\Charm;
use App\Models\Counter;
use App\Models\InventoryLog;
use App\Models\Order;
use Illuminate\Support\Collection;

class OrderService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected CharmService $charmService,
        protected EmailService $emailService,
    ) {}

    /**
     * Generate invoice number: SWV-YYYYMMDD-NNNN
     */
    protected static function generateInvoiceNumber(int $sequence): string
    {
        $date = now()->format('Ymd');
        $padded = str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);

        return "SWV-{$date}-{$padded}";
    }

    /**
     * Resolve the price after applying an active discount.
     *
     * @param  float  $price
     * @param  array{enabled?: bool, value?: float, startAt?: string, endAt?: string}|null  $discount
     */
    protected static function getDiscountedPrice(float $price, ?array $discount): float
    {
        if (
            empty($discount['enabled'])
            || empty($discount['value'])
            || $discount['value'] <= 0
        ) {
            return $price;
        }

        if (!empty($discount['startAt']) && strtotime($discount['startAt']) > time()) {
            return $price;
        }

        if (!empty($discount['endAt']) && strtotime($discount['endAt']) < time()) {
            return $price;
        }

        return round($price - ($price * $discount['value']) / 100);
    }

    /**
     * Increment the order counter and return the new sequence value.
     */
    protected static function nextSequence(): int
    {
        $counter = Counter::where('name', 'orders')->first();

        if (!$counter) {
            $counter = Counter::create(['name' => 'orders', 'sequence' => 0]);
        }

        $counter->sequence = $counter->sequence + 1;
        $counter->save();

        return $counter->sequence;
    }

    /**
     * Create an online order with reservation.
     */
    public function createOrder(array $data): Order
    {
        $sequence = self::nextSequence();
        $invoiceNumber = self::generateInvoiceNumber($sequence);

        $charmIds = array_map(fn ($item) => $item['charmId'], $data['items']);
        $charmDocs = Charm::whereIn('_id', $charmIds)->get()->keyBy('_id');

        $items = [];

        foreach ($data['items'] as $item) {
            $charm = $charmDocs->get($item['charmId']);

            if (!$charm) {
                throw new \RuntimeException("Charm tidak ditemukan: {$item['charmId']}");
            }

            $price = self::getDiscountedPrice($charm->price, $charm->discount);
            $subtotal = $price * $item['qty'];

            $items[] = [
                'charmId' => $item['charmId'],
                'name' => $charm->name,
                'price' => $price,
                'qty' => $item['qty'],
                'subtotal' => $subtotal,
            ];
        }

        $subtotal = array_sum(array_column($items, 'subtotal'));
        $shippingCost = $data['shippingCost'] ?? 0;
        $total = $subtotal + $shippingCost;

        $order = Order::create([
            'items' => $items,
            'buyer' => $data['buyer'] ?? [],
            'shipping' => $data['shipping'] ?? [],
            'subtotal' => $subtotal,
            'shippingCost' => $shippingCost,
            'total' => $total,
            'invoiceNumber' => $invoiceNumber,
            'status' => OrderStatus::PENDING_PAYMENT,
            'source' => 'ONLINE',
        ]);

        try {
            $this->inventoryService->reserveStock(
                (string) $order->_id,
                array_map(fn ($item) => ['charmId' => $item['charmId'], 'qty' => $item['qty']], $items),
            );
        } catch (\Throwable $e) {
            $order->delete();
            throw $e;
        }

        // Send invoice email (non-blocking, best-effort)
        try {
            $this->emailService->sendInvoiceEmail([
                'invoiceNumber' => $order->invoiceNumber,
                'buyerName' => $order->buyer['name'] ?? '',
                'buyerEmail' => $order->buyer['email'] ?? '',
                'items' => $items,
                'subtotal' => $subtotal,
                'shippingCost' => $shippingCost,
                'total' => $total,
                'shippingMethod' => $order->shipping['method'] ?? '',
            ]);
        } catch (\Throwable) {
            // Email failure should not block order creation
        }

        return $order->refresh();
    }

    /**
     * Create a cashier (walk-in) order with immediate stock consumption.
     *
     * @param  array<int, array{charmId: string, qty: int}>  $items
     * @param  'CASH'|'QRIS'  $paymentMethod
     * @param  array{cashierName?: string, buyerName?: string, cashReceived?: float}|null  $opts
     */
    public function createCashierOrder(array $items, string $paymentMethod, ?array $opts = null): Order
    {
        if (empty($items)) {
            throw new \RuntimeException('Tidak ada item di keranjang');
        }

        if (!in_array($paymentMethod, ['CASH', 'QRIS'], true)) {
            throw new \RuntimeException('Metode pembayaran tidak valid');
        }

        $buyerName = trim($opts['buyerName'] ?? '') ?: 'Walk-in';

        $sequence = self::nextSequence();
        $invoiceNumber = self::generateInvoiceNumber($sequence);

        $charmIds = array_map(fn ($item) => $item['charmId'], $items);
        $charmDocs = Charm::whereIn('_id', $charmIds)->get()->keyBy('_id');

        $lineItems = [];

        foreach ($items as $item) {
            $charm = $charmDocs->get($item['charmId']);

            if (!$charm) {
                throw new \RuntimeException("Charm tidak ditemukan: {$item['charmId']}");
            }

            $price = self::getDiscountedPrice($charm->price, $charm->discount);

            $lineItems[] = [
                'charmId' => (string) $charm->_id,
                'name' => $charm->name,
                'image' => $charm->image,
                'price' => $price,
                'qty' => $item['qty'],
                'subtotal' => $price * $item['qty'],
            ];
        }

        $subtotal = array_sum(array_column($lineItems, 'subtotal'));
        $total = $subtotal;

        // Atomic consume with full rollback on any shortfall
        $consumed = [];

        foreach ($lineItems as $lineItem) {
            $charm = $this->inventoryService->deductStockAtomic(
                $lineItem['charmId'],
                $lineItem['qty'],
                'consume',
            );

            if (!$charm) {
                // Rollback previously consumed items
                foreach ($consumed as $c) {
                    Charm::where('_id', $c['charmId'])->update([
                        '$inc' => ['stock' => $c['qty'], 'totalSold' => -$c['qty']],
                    ]);
                }
                throw new \RuntimeException("Stok tidak cukup: {$lineItem['name']}");
            }

            $consumed[] = ['charmId' => $lineItem['charmId'], 'qty' => $lineItem['qty']];

            InventoryLog::create([
                'charmId' => $lineItem['charmId'],
                'before' => ($charm->stock ?? 0) + $lineItem['qty'],
                'after' => $charm->stock ?? 0,
                'change' => -$lineItem['qty'],
                'reason' => InventoryReason::ORDER,
                'reference' => "kasir:{$invoiceNumber}",
            ]);
        }

        $cashReceived = $paymentMethod === 'CASH' ? max(0, $opts['cashReceived'] ?? 0) : null;

        $order = Order::create([
            'invoiceNumber' => $invoiceNumber,
            'buyer' => [
                'name' => $buyerName,
                'email' => 'walkin@swave.local',
                'phone' => '-',
            ],
            'shipping' => [
                'method' => ShippingMethod::PICKUP->value,
                'receiverName' => $buyerName,
            ],
            'items' => $lineItems,
            'payment' => [
                'method' => $paymentMethod,
                'amount' => $total,
                'cashReceived' => $cashReceived,
                'change' => $paymentMethod === 'CASH'
                    ? max(0, ($opts['cashReceived'] ?? 0) - $total)
                    : null,
                'status' => PaymentStatus::PAID->value,
                'paidAt' => now()->toISOString(),
                'confirmedAt' => now()->toISOString(),
            ],
            'status' => OrderStatus::COMPLETED,
            'subtotal' => $subtotal,
            'shippingCost' => 0,
            'total' => $total,
            'source' => 'CASHIER',
            'cashierName' => trim($opts['cashierName'] ?? '') ?: null,
        ]);

        CharmService::invalidateCache();

        return $order->refresh();
    }

    /**
     * List orders with optional filters.
     *
     * @param  array{status?: OrderStatus, search?: string, limit?: int, offset?: int}|null  $filters
     * @return array{orders: Collection, total: int}
     */
    public function getOrders(?array $filters = null): array
    {
        $query = Order::query();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty(trim($filters['search'] ?? ''))) {
            $search = trim($filters['search']);
            $escaped = preg_quote($search, '/');
            $regex = new \MongoDB\BSON\Regex($escaped, 'i');

            $query->where(function ($q) use ($regex) {
                $q->where('buyer.name', 'regex', $regex)
                    ->orWhere('invoiceNumber', 'regex', $regex)
                    ->orWhere('buyer.email', 'regex', $regex);
            });
        }

        $total = $query->count();

        $orders = $query
            ->orderByDesc('createdAt')
            ->skip($filters['offset'] ?? 0)
            ->limit($filters['limit'] ?? 50)
            ->get();

        return compact('orders', 'total');
    }

    /**
     * Get a single order by ID.
     */
    public function getOrderById(string $id): ?Order
    {
        return Order::find($id);
    }

    /**
     * Get a single order by invoice number.
     */
    public function getOrderByInvoice(string $invoice): ?Order
    {
        return Order::where('invoiceNumber', $invoice)->first();
    }

    /**
     * Update order status with reservation side effects.
     */
    public function updateOrderStatus(string $id, OrderStatus $status): ?Order
    {
        $current = Order::find($id);

        if (!$current) {
            throw new \RuntimeException('Order not found');
        }

        $previousStatus = $current->status;

        if (in_array($previousStatus, [
            OrderStatus::CANCELLED,
            OrderStatus::COMPLETED,
            OrderStatus::EXPIRED,
        ], true)) {
            throw new \RuntimeException('Cannot update a finalized order');
        }

        if ($status === OrderStatus::PAID && $previousStatus !== OrderStatus::PAID) {
            $this->inventoryService->consumeReservations($id);
        }

        if (in_array($status, [OrderStatus::CANCELLED, OrderStatus::EXPIRED], true)) {
            $this->inventoryService->releaseReservations($id);
        }

        $current->status = $status;
        $current->save();

        CharmService::invalidateCache();

        return $current->refresh();
    }

    /**
     * Update order payment details with reservation side effects.
     *
     * @param  array{method?: string, proofImage?: array{publicId: string, secureUrl: string}, status?: string, paidAt?: string}  $paymentData
     */
    public function updateOrderPayment(string $id, array $paymentData): ?Order
    {
        $current = Order::find($id);

        if (!$current) {
            throw new \RuntimeException('Order not found');
        }

        if (in_array($current->status, [OrderStatus::CANCELLED, OrderStatus::COMPLETED], true)) {
            throw new \RuntimeException('Cannot process payment for a finalized order');
        }

        $update = [];
        $reviveOrder = false;

        if (!empty($paymentData['method'])) {
            $update['payment.method'] = $paymentData['method'];
        }

        if (!empty($paymentData['proofImage'])) {
            $update['payment.proofImage'] = $paymentData['proofImage'];
            $update['payment.status'] = PaymentStatus::WAITING_CONFIRMATION->value;
        }

        if (!empty($paymentData['status'])) {
            $update['payment.status'] = $paymentData['status'];

            if ($paymentData['status'] === PaymentStatus::PAID->value) {
                // Skip if already marked paid to avoid double consumption
                if ($current->payment['status'] ?? null !== PaymentStatus::PAID->value) {
                    $this->inventoryService->consumeReservations($id);
                }
                if ($current->status === OrderStatus::EXPIRED) {
                    $reviveOrder = true;
                }
            }

            if (in_array($paymentData['status'], [
                PaymentStatus::FAILED->value,
                PaymentStatus::REFUNDED->value,
            ], true)) {
                $this->inventoryService->releaseReservations($id);
            }
        }

        if (!empty($paymentData['paidAt'])) {
            $update['payment.paidAt'] = $paymentData['paidAt'];
        }

        if ($reviveOrder) {
            $update['status'] = OrderStatus::PAID;
        }

        $current->fill($update);
        $current->save();

        CharmService::invalidateCache();

        return $current->refresh();
    }

    /**
     * Get dashboard statistics.
     */
    public function getDashboardStats(): array
    {
        $totalOrders = Order::count();
        $pendingOrders = Order::where('status', OrderStatus::PENDING_PAYMENT)->count();
        $completedOrders = Order::where('status', OrderStatus::COMPLETED)->count();

        $totalRevenue = Order::where('status', OrderStatus::COMPLETED)->sum('total');

        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $todayPickups = Order::where('status', OrderStatus::READY_FOR_PICKUP)
            ->whereBetween('updatedAt', [$todayStart, $todayEnd])
            ->count();

        $todayDeliveries = Order::where('status', OrderStatus::SHIPPED)
            ->whereBetween('updatedAt', [$todayStart, $todayEnd])
            ->count();

        return [
            'totalOrders' => $totalOrders,
            'pendingOrders' => $pendingOrders,
            'completedOrders' => $completedOrders,
            'totalRevenue' => (float) $totalRevenue,
            'todayPickups' => $todayPickups,
            'todayDeliveries' => $todayDeliveries,
        ];
    }

    /**
     * Get admin alerts: new pending orders and overdue orders.
     */
    public function getAdminAlerts(): array
    {
        $overdueThreshold = now()->subDays(4);

        $newOrders = Order::where('status', OrderStatus::PENDING_PAYMENT)
            ->orderByDesc('createdAt')
            ->limit(10)
            ->get();

        $overdueOrders = Order::whereIn('status', [OrderStatus::PENDING_PAYMENT, OrderStatus::PAID])
            ->where('updatedAt', '<=', $overdueThreshold)
            ->orderBy('updatedAt')
            ->limit(20)
            ->get();

        $pick = fn (Order $o) => [
            '_id' => (string) $o->_id,
            'invoiceNumber' => $o->invoiceNumber,
            'buyerName' => $o->buyer['name'] ?? '-',
            'status' => $o->status->value,
            'total' => $o->total ?? 0,
            'createdAt' => $o->createdAt,
            'updatedAt' => $o->updatedAt,
        ];

        return [
            'newOrders' => $newOrders->map($pick)->values()->all(),
            'overdueOrders' => $overdueOrders->map($pick)->values()->all(),
        ];
    }
}
