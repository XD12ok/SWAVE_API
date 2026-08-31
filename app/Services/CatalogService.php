<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Charm;

class CatalogService
{
    public function index()
    {
        return [
            'charms' => Charm::where('active', true)->get(),
            'categories' => Category::where('active', true)->get(),
        ];
    }
}
