<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreKasirRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.charmId' => ['required', 'string'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'buyer' => ['nullable', 'array'],
            'shipping' => ['nullable', 'array'],
            'shippingCost' => ['nullable', 'numeric'],
            'payment' => ['nullable', 'array'],
        ];
    }
}
