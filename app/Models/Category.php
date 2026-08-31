<?php

namespace App\Models;


class Category extends BaseModel
{
    protected $collection = 'categories';
    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
    ];
}
