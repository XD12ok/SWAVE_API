<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreOrderRequest;
use App\Http\Requests\Api\UpdateOrderRequest;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function __construct(protected OrderService $orders) {}

    public function index(Request $request)
    {
        $filters = [];

        if ($request->query('status')) {
            $filters['status'] = $request->query('status');
        }

        if ($request->query('search')) {
            $filters['search'] = $request->query('search');
        }

        $result = $this->orders->getOrders($filters);

        return response()->json($result);
    }

    public function store(StoreOrderRequest $request)
    {
        $data = $request->validated();

        try {
            $order = $this->orders->createOrder($data);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['order' => $order], Response::HTTP_CREATED);
    }

    public function show(string $id)
    {
        $order = $this->orders->getOrderById($id);

        if (! $order) {
            return response()->json(['message' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['order' => $order]);
    }

    public function update(UpdateOrderRequest $request, string $id)
    {
        $data = $request->validated();

        $order = null;

        try {
            if (! empty($data['status'])) {
                try {
                    $status = OrderStatus::from($data['status']);
                } catch (\ValueError $e) {
                    return response()->json(['message' => 'Invalid status'], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                $order = $this->orders->updateOrderStatus($id, $status);
            }

            if (! empty($data['payment'])) {
                $order = $this->orders->updateOrderPayment($id, $data['payment']);
            }
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }

        if (! $order) {
            return response()->json(['message' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['order' => $order]);
    }
}
