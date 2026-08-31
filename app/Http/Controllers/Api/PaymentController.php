<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdatePaymentRequest;
use App\Services\PaymentService;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    public function __construct(protected PaymentService $payment) {}

    public function show(string $id)
    {
        $order = $this->payment->show($id);

        if (! $order) {
            return response()->json(['message' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['order' => $order]);
    }

    public function update(UpdatePaymentRequest $request, string $id)
    {
        $data = $request->validated();

        $order = $this->payment->update($id, $data);

        if (! $order) {
            return response()->json(['message' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['order' => $order]);
    }
}
