<?php

namespace App\Models;

use App\Enums\ReservationStatus;

class InventoryReservation extends BaseModel
{
    protected $collection = 'inventory_reservations';
    protected $guarded = [];

    protected $casts = [
        'expiresAt' => 'datetime',
        'status' => ReservationStatus::class,
    ];
}
