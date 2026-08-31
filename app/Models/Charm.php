<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\HybridRelations;

class Charm extends BaseModel
{
    use HybridRelations;

    protected $collection = 'charms';
    protected $guarded = [];
    protected $hidden = ['categoryRef'];

    public function categoryRef()
    {
        return $this->belongsTo(Category::class, 'category', '_id');
    }

    protected $casts = [
        'image' => 'array',
        'discount' => 'array',
        'price' => 'float',
        'stock' => 'integer',
        'reservedStock' => 'integer',
        'totalSold' => 'integer',
        'weight' => 'float',
        'limited' => 'boolean',
        'active' => 'boolean',
    ];
}
