<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AdminService;

class AdminController extends Controller
{
    public function __construct(protected AdminService $admin) {}

    public function index()
    {
        return response()->json($this->admin->alerts());
    }
}
