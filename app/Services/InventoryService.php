<?php

namespace App\Services;

use App\Enums\InventoryReason;
use App\Enums\ReservationStatus;
use App\Models\Charm;
use App\Models\InventoryLog;
use App\Models\InventoryReservation;
use App\Models\Order;
use MongoDB\BSON\ObjectId;

class InventoryService
{
    protected const RESERVATION_TTL_MINUTES = 30;

    public function index()
    {
        return Charm::select(['_id', 'name', 'stock', 'reservedStock', 'totalSold'])
            ->orderBy('name', 'asc')
            ->get()
            ->map(fn (Charm $c) => [
                '_id' => (string) $c->_id,
                'name' => $c->name,
                'stock' => $c->stock ?? 0,
                'reservedStock' => $c->reservedStock ?? 0,
                'totalSold' => $c->totalSold ?? 0,
                'available' => max(0, ($c->stock ?? 0) - ($c->reservedStock ?? 0)),
            ])
            ->values()
            ->all();
    }

    public function logs(int $page = 1, int $perPage = 20)
    {
        $total = InventoryLog::count();

        $items = InventoryLog::orderBy('createdAt', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $charmIds = $items->pluck('charmId')
            ->map(fn ($id) => (string) $id)
            ->filter()
            ->unique()
            ->values();

        $charmMap = Charm::whereIn('_id', $charmIds)
            ->get(['_id', 'name', 'slug'])
            ->keyBy(fn (Charm $c) => (string) $c->_id);

        $data = $items->map(function ($log) use ($charmMap) {
            $arr = $log->toArray();
            $cid = (string) ($log->charmId ?? '');

            if ($cid !== '' && $charmMap->has($cid)) {
                $charm = $charmMap->get($cid);
                $arr['charmId'] = [
                    '_id' => (string) $charm->_id,
                    'name' => $charm->name,
                    'slug' => $charm->slug,
                ];
            } else {
                $arr['charmId'] = null;
            }

            $arr['createdAt'] = $log->createdAt;

            return $arr;
        })->values()->all();

        return [
            'data' => $data,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'lastPage' => (int) ceil($total / $perPage),
        ];
    }

    public function runReservationExpiryIfNeeded(): void
    {
        $this->cleanup();
    }

    /**
     * Release reservations whose TTL has expired.
     */
    public function cleanup(): int
    {
        $expired = InventoryReservation::where('status', ReservationStatus::ACTIVE->value)
            ->where('expiresAt', '<=', now())
            ->get();

        $released = 0;

        foreach ($expired as $reservation) {
            $charm = $this->deductStockAtomic($reservation->charmId, $reservation->qty, 'release');

            if ($charm) {
                $this->writeLog([
                    'charmId' => $reservation->charmId,
                    'before' => ($charm->reservedStock ?? 0) + $reservation->qty,
                    'after' => $charm->reservedStock ?? 0,
                    'change' => $reservation->qty,
                    'reason' => InventoryReason::EXPIRED,
                    'reference' => "expire:{$reservation->orderId}",
                ]);
            }

            $reservation->update(['status' => ReservationStatus::EXPIRED]);
            $released++;
        }

        return $released;
    }

    public function checkStock(array $charmIds): array
    {
        $charms = Charm::whereIn('_id', $charmIds)->get();

        return $charms->map(function (Charm $charm) {
            $available = $charm->stock - $charm->reservedStock;

            return [
                'charmId' => $charm->_id,
                'name' => $charm->name,
                'stock' => $charm->stock,
                'reservedStock' => $charm->reservedStock,
                'available' => max(0, $available),
                'inStock' => $available > 0,
            ];
        })->all();
    }

    /**
     * Reserve stock for an online order (hold units without removing physical stock).
     *
     * @param  string  $orderId
     * @param  array<int, array{charmId: string, qty: int}>  $items
     */
    public function reserveStock(string $orderId, array $items): void
    {
        $this->cleanup();

        foreach ($items as $item) {
            $charm = $this->deductStockAtomic($item['charmId'], $item['qty'], 'reserve');

            if (!$charm) {
                $this->releaseReservations($orderId);
                throw new \RuntimeException("Stok tidak cukup: {$item['charmId']}");
            }

            $availableAfter = ($charm->stock ?? 0) - ($charm->reservedStock ?? 0);

            $this->writeLog([
                'charmId' => $item['charmId'],
                'before' => $availableAfter + $item['qty'],
                'after' => $availableAfter,
                'change' => -$item['qty'],
                'reason' => InventoryReason::ORDER,
                'reference' => "reserve:{$orderId}",
            ]);

            InventoryReservation::create([
                'orderId' => $orderId,
                'charmId' => $item['charmId'],
                'qty' => $item['qty'],
                'expiresAt' => now()->addMinutes(self::RESERVATION_TTL_MINUTES),
                'status' => ReservationStatus::ACTIVE->value,
            ]);
        }
    }

    /**
     * Fulfill reservations of a paid order (consume physical stock once).
     */
    public function consumeReservations(string $orderId): void
    {
        $reservations = InventoryReservation::where('orderId', $orderId)
            ->whereIn('status', [
                ReservationStatus::ACTIVE->value,
                ReservationStatus::EXPIRED->value,
                ReservationStatus::RELEASED->value,
            ])
            ->get();

        // Safety net: if the order has no reservation documents at all (e.g. the
        // TTL index already deleted them), rebuild the consumption from the
        // order's items so a paid order still decrements physical stock once.
        if (!InventoryReservation::where('orderId', $orderId)->exists()) {
            $order = Order::find($orderId);

            if ($order && !empty($order->items)) {
                foreach ($order->items as $item) {
                    $charm = $this->deductStockAtomic($item['charmId'], $item['qty'], 'consume-reserved');

                    if (!$charm) {
                        throw new \RuntimeException("Stok tidak cukup untuk memproses pembayaran order {$orderId}");
                    }

                    $this->writeLog([
                        'charmId' => $item['charmId'],
                        'before' => ($charm->stock ?? 0) + $item['qty'],
                        'after' => $charm->stock ?? 0,
                        'change' => -$item['qty'],
                        'reason' => InventoryReason::ORDER,
                        'reference' => $orderId,
                    ]);
                }
            }

            return;
        }

        foreach ($reservations as $reservation) {
            // Pay-after-expiry: reservation was released, re-reserve atomically or fail.
            if ($reservation->status !== ReservationStatus::ACTIVE) {
                $reReserved = $this->deductStockAtomic($reservation->charmId, $reservation->qty, 'reserve');

                if (!$reReserved) {
                    throw new \RuntimeException("Stok tidak cukup untuk memproses pembayaran order {$orderId}");
                }
            }

            $charm = $this->deductStockAtomic($reservation->charmId, $reservation->qty, 'consume-reserved');

            if (!$charm) {
                throw new \RuntimeException("Reservasi tidak dapat diproses untuk charm {$reservation->charmId}");
            }

            $this->writeLog([
                'charmId' => $reservation->charmId,
                'before' => ($charm->stock ?? 0) + $reservation->qty,
                'after' => $charm->stock ?? 0,
                'change' => -$reservation->qty,
                'reason' => InventoryReason::ORDER,
                'reference' => $orderId,
            ]);

            $reservation->update(['status' => ReservationStatus::CONSUMED]);
        }
    }

    /**
     * Release active reservations of a cancelled/expired order.
     */
    public function releaseReservations(string $orderId): void
    {
        $reservations = InventoryReservation::where('orderId', $orderId)
            ->where('status', ReservationStatus::ACTIVE->value)
            ->get();

        foreach ($reservations as $reservation) {
            $charm = $this->deductStockAtomic($reservation->charmId, $reservation->qty, 'release');

            if ($charm) {
                $this->writeLog([
                    'charmId' => $reservation->charmId,
                    'before' => ($charm->reservedStock ?? 0) + $reservation->qty,
                    'after' => $charm->reservedStock ?? 0,
                    'change' => $reservation->qty,
                    'reason' => InventoryReason::ORDER,
                    'reference' => "release:{$orderId}",
                ]);
            }

            $reservation->update(['status' => ReservationStatus::RELEASED]);
        }
    }

    /**
     * Single atomic gate for ALL stock movements.
     *
     * @param  'reserve'|'release'|'consume'|'consume-reserved'  $mode
     */
    public function deductStockAtomic(string $charmId, int $qty, string $mode): ?Charm
    {
        try {
            $objectId = new ObjectId($charmId);
        } catch (\Throwable) {
            return null;
        }

        $filter = ['_id' => $objectId];
        $update = [];

        switch ($mode) {
            case 'reserve':
                // Online order: hold units without removing physical stock.
                $filter['$expr'] = [
                    '$gte' => [['$subtract' => ['$stock', '$reservedStock']], $qty],
                ];
                $update['$inc'] = ['reservedStock' => $qty];
                break;

            case 'release':
                $filter['$expr'] = ['$gte' => ['$reservedStock', $qty]];
                $update['$inc'] = ['reservedStock' => -$qty];
                break;

            case 'consume-reserved':
                // Fulfill a previously reserved unit (paid online order).
                $filter['$expr'] = [
                    '$and' => [
                        ['$gte' => ['$stock', $qty]],
                        ['$gte' => ['$reservedStock', $qty]],
                    ],
                ];
                $update['$inc'] = ['stock' => -$qty, 'reservedStock' => -$qty, 'totalSold' => $qty];
                break;

            case 'consume':
            default:
                // Direct sale (kasir): must not touch units reserved by online orders.
                $filter['$expr'] = [
                    '$gte' => [['$subtract' => ['$stock', '$reservedStock']], $qty],
                ];
                $update['$inc'] = ['stock' => -$qty, 'totalSold' => $qty];
                break;
        }

        return Charm::query()->raw(
            fn (\MongoDB\Collection $collection) => $collection->findOneAndUpdate(
                $filter,
                $update,
                ['returnDocument' => \MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER],
            ),
        );
    }

    /**
     * Persist a stock movement log with a consistent schema.
     *
     * @param  array{charmId: string, before: int, after: int, change: int, reason: InventoryReason, reference: string}  $entry
     */
    protected function writeLog(array $entry): void
    {
        InventoryLog::create([
            'charmId' => $entry['charmId'],
            'before' => $entry['before'],
            'after' => $entry['after'],
            'change' => $entry['change'],
            'reason' => $entry['reason'],
            'reference' => $entry['reference'],
        ]);
    }
}