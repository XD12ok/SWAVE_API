<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreCharmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string'],
            'categoryId' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'reservedStock' => ['nullable', 'integer', 'min:0'],
            'totalSold' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'array'],
            'discount' => ['nullable', 'array'],
            'limited' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
