<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'method' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'proof' => ['nullable', 'string'],
        ];
    }
}
