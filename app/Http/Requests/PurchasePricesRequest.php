<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchasePricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.id'             => ['required', 'integer'],
            'items.*.quantity'       => ['required', 'integer', 'min:1'],
            'items.*.selling_price'  => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.quantity.min' => 'Quantity must be at least 1.',
        ];
    }
}
