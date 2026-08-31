<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CatalogService;

class CatalogController extends Controller
{
    public function __construct(protected CatalogService $catalog) {}

    public function index()
    {
        return response()->json($this->catalog->index());
    }
}
