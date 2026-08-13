<?php

namespace App\Http\Requests;

use App\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $couponId = $this->route('coupon')?->id;

        return [
            'code'                  => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]+$/', Rule::unique('coupons', 'code')->ignore($couponId)],
            'name'                  => ['required', 'string', 'max:255'],
            'description'           => ['nullable', 'string'],
            'discount_type'         => ['required', Rule::in([Coupon::TYPE_PERCENTAGE, Coupon::TYPE_FIXED_AMOUNT, Coupon::TYPE_FIXED_PRICE])],
            'discount_value'        => ['required', 'numeric', 'min:0'],
            'applies_to'            => ['required', Rule::in([Coupon::APPLIES_ALL, Coupon::APPLIES_REGULAR_PRICE_ONLY])],
            'minimum_order_amount'  => ['nullable', 'numeric', 'min:0'],
            'maximum_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'starts_at'             => ['nullable', 'date'],
            'ends_at'               => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status'                => ['required', Rule::in([Coupon::STATUS_ACTIVE, Coupon::STATUS_INACTIVE])],
            'usage_limit'           => ['nullable', 'integer', 'min:1'],
            'product_ids'           => ['nullable', 'array'],
            'product_ids.*'         => ['integer', 'exists:products,id'],
            'variant_ids'           => ['nullable', 'array'],
            'variant_ids.*'         => ['integer', 'exists:product_variants,id'],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $type = $this->input('discount_type');
            $value = (float) $this->input('discount_value');

            if ($type === Coupon::TYPE_PERCENTAGE && ($value <= 0 || $value > 100)) {
                $validator->errors()->add('discount_value', 'Percentage discount must be between 0 and 100.');
            }
        });
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtolower(trim((string) $this->input('code'))),
        ]);
    }
}