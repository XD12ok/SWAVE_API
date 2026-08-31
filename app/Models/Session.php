<?php

namespace App\Models;


class Session extends BaseModel
{
    protected $collection = 'sessions';
    protected $guarded = [];

    protected $casts = [
        'expiresAt' => 'datetime',
    ];
}
