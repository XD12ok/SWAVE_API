<?php

namespace App\Models;


class ShippingRule extends BaseModel
{
    protected $collection = 'shipping_rules';
    protected $guarded = [];

    protected $casts = [
        'minKm' => 'float',
        'maxKm' => 'float',
        'price' => 'float',
        'active' => 'boolean',
    ];
}
