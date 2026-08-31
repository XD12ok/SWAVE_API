<?php

namespace App\Models;


class Settings extends BaseModel
{
    protected $collection = 'settings';
    protected $guarded = [];

    protected $casts = [
        'store' => 'array',
        'pickup' => 'array',
        'delivery' => 'array',
        'maintenance' => 'boolean',
    ];
}
