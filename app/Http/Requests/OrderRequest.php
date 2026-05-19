<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reference'    => ['nullable', 'string', 'max:64', Rule::unique('orders', 'reference')],
            'customer_id'  => ['nullable', 'exists:users,id'],
            'order_date'   => ['required', 'date'],
            'status'       => ['nullable', Rule::in(['order placed', 'confirmed', 'processing', 'shipped', 'ready for pick up', 'delivered', 'cancelled'])],
            'discount'     => ['nullable', 'numeric', 'min:0', 'max:100'],
            'shipping_fee' => ['nullable', 'numeric', 'min:0'],
            'note'         => ['nullable', 'string'],

            'items'                      => ['required', 'array', 'min:1'],
            'items.*.product_id'         => ['required', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'exists:product_variants,id'],
            'items.*.quantity'           => ['required', 'integer', 'min:1'],
            'items.*.unit_price'         => ['nullable', 'numeric', 'min:0'],
            'items.*.discount'           => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
