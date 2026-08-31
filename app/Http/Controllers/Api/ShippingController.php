<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ShippingCostRequest;
use App\Services\ShippingService;

class ShippingController extends Controller
{
    public function __construct(protected ShippingService $shipping) {}

    public function show(ShippingCostRequest $request)
    {
        $data = $request->validated();

        $result = $this->shipping->calculate(
            $data['lat'] ?? null,
            $data['lng'] ?? null,
            $data['method'] ?? null
        );

        return response()->json($result);
    }
}
