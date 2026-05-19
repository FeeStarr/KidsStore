<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reference'      => ['nullable', 'string', 'max:64', Rule::unique('purchases', 'reference')],
            'supplier_id'    => ['nullable', 'exists:suppliers,id'],
            'purchase_date'  => ['required', 'date'],
            'status'         => ['nullable', Rule::in(['pending', 'received', 'cancelled'])],
            'note'           => ['nullable', 'string'],

            'items'                       => ['required', 'array', 'min:1'],
            'items.*.product_id'          => ['required', 'exists:products,id'],
            'items.*.product_variant_id'  => ['nullable', 'exists:product_variants,id'],
            'items.*.quantity'            => ['required', 'integer', 'min:1'],
            'items.*.cost_price'          => ['required', 'numeric', 'min:0'],
            'items.*.shipping_fee'        => ['nullable', 'numeric', 'min:0'],
            'items.*.packaging_cost'      => ['nullable', 'numeric', 'min:0'],
            'items.*.other_costs'         => ['nullable', 'numeric', 'min:0'],
            'items.*.selling_price'       => ['nullable', 'numeric', 'min:0'],
            'items.*.discount'            => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
