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
            'reference'               => ['nullable', 'string', 'max:64', Rule::unique('orders', 'reference')],
            'customer_id'             => ['nullable', 'exists:users,id'],
            'order_date'              => ['required', 'date'],
            'expected_delivery_date'  => ['nullable', 'date', 'after_or_equal:order_date'],
            'status'                  => ['nullable', Rule::in([
                'ordered', 'pending confirmation', 'confirmed', 'processing',
                'out for delivery', 'ready for pick up', 'delivered', 'cancelled',
            ])],
            'delivery_method'         => ['nullable', 'in:delivery,pickup'],
            'pickup_station_id'       => ['nullable', 'exists:pickup_stations,id'],
            'delivery_address'        => ['nullable', 'string', 'max:500'],
            'discount'                => ['nullable', 'numeric', 'min:0', 'max:100'],
            'shipping_fee'            => ['nullable', 'numeric', 'min:0'],
            'note'                    => ['nullable', 'string'],

            'items'                      => ['required', 'array', 'min:1'],
            'items.*.product_id'         => ['required', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'exists:product_variants,id'],
            'items.*.quantity'           => ['required', 'integer', 'min:1'],
            'items.*.unit_price'         => ['nullable', 'numeric', 'min:0'],
            'items.*.discount'           => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.selected_age_group' => ['nullable', 'string', 'max:32'],
            'items.*.selected_size'      => ['nullable', 'string', 'max:64'],
        ];
    }
}
