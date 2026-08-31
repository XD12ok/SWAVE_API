<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:PENDING_PAYMENT,PAID,PROCESSING,READY_FOR_PICKUP,SHIPPED,COMPLETED,CANCELLED,EXPIRED'],
            'payment' => ['nullable', 'array'],
        ];
    }
}
