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
        $purchaseId = $this->route('purchase')?->id;

        return [
            'purchase_number' => ['nullable', 'string', 'max:64',
                Rule::unique('purchases', 'purchase_number')->ignore($purchaseId)],
            'reference'      => ['nullable', 'string', 'max:64',
                Rule::unique('purchases', 'reference')->ignore($purchaseId)],
            'supplier_id'    => ['nullable', 'exists:suppliers,id'],
            'purchase_date'  => ['required', 'date'],
            'status'         => ['nullable', Rule::in(['pending', 'received', 'cancelled'])],
            'note'           => ['nullable', 'string'],
            'pickup_fee_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],

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
