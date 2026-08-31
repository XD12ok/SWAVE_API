<?php

namespace App\Models;

use App\Enums\InventoryReason;

class InventoryLog extends BaseModel
{
    protected $collection = 'inventory_logs';
    protected $guarded = [];

    protected $casts = [
        'reason' => InventoryReason::class,
    ];
}
