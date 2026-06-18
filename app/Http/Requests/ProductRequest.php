<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'category_id'   => ['nullable', 'exists:categories,id'],
            'brand_id'      => ['nullable', 'exists:brands,id'],
            'sku'           => ['nullable', 'string', 'max:64', Rule::unique('products', 'sku')->ignore($productId)],
            'name'          => ['required', 'string', 'max:255'],
            'slug'          => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($productId)],
            'description'   => ['nullable', 'string'],
            'status'        => ['nullable', Rule::in(['active', 'inactive', 'draft'])],

            'variants'             => ['nullable', 'array'],
            'variants.*.id'        => ['nullable', 'integer', 'exists:product_variants,id'],
            'variants.*.name'      => ['required_with:variants', 'string', 'max:128'],
            'variants.*.sku'       => ['nullable', 'string', 'max:64'],
            'variants.*.size'      => ['nullable', 'string', 'max:64'],
            'variants.*.age_group'   => ['nullable', 'array'],
            'variants.*.age_group.*' => ['nullable', 'string', 'max:32'],
            'variants.*.quantity'  => ['required_with:variants', 'integer', 'min:0'],
            'variants.*.images'    => ['required_with:variants', 'array', 'min:1'],
            'variants.*.images.*'  => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'variants.*.sizes'                => ['nullable', 'array'],
            'variants.*.sizes.*.size'         => ['required_with:variants.*.sizes', 'string', 'max:64'],
            'variants.*.sizes.*.quantity'     => ['required_with:variants.*.sizes', 'integer', 'min:0'],
            'variants.*.sizes.*.sku'          => ['nullable', 'string', 'max:64'],
            'variants.*.sizes.*.images'       => ['nullable', 'array'],
            'variants.*.sizes.*.images.*'     => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],

            'images'               => ['nullable', 'array'],

            'images.*'             => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'discount'      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active'     => ['nullable', 'boolean'],

            'delete_images'   => ['nullable', 'array'],
            'delete_images.*' => ['integer', 'exists:product_images,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'discount'  => $this->input('discount', 0),
            'selling_price' => $this->filled('selling_price') ? $this->input('selling_price') : 0,
            'status' => $this->input('status', $this->boolean('is_active', true) ? 'active' : 'inactive'),
        ]);

        if ($this->has('variants') && is_array($this->input('variants'))) {
            $variants = array_map(function ($v) {
                if (is_array($v)) {
                    $v['quantity'] = isset($v['quantity']) ? (int) $v['quantity'] : 0;
                }
                return $v;
            }, $this->input('variants'));

            $this->merge(['variants' => $variants]);
        }

        // Coerce sizes quantities
        if ($this->has('variants') && is_array($this->input('variants'))) {
            $variants = $this->input('variants');
            foreach ($variants as $vi => $v) {
                if (isset($v['sizes']) && is_array($v['sizes'])) {
                    foreach ($v['sizes'] as $si => $s) {
                        if (is_array($s) && isset($s['quantity'])) {
                            $variants[$vi]['sizes'][$si]['quantity'] = (int) $s['quantity'];
                        }
                    }
                }
            }

            $this->merge(['variants' => $variants]);
        }
    }
}
