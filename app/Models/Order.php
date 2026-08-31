<?php

namespace App\Models;

use App\Enums\OrderStatus;

class Order extends BaseModel
{
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $collection = 'orders';
    protected $guarded = [];

    protected $casts = [
        'buyer' => 'array',
        'shipping' => 'array',
        'items' => 'array',
        'payment' => 'array',
        'subtotal' => 'float',
        'shippingCost' => 'float',
        'total' => 'float',
        'status' => OrderStatus::class,
    ];
}
