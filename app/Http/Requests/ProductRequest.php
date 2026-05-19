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
            'sku'           => ['nullable', 'string', 'max:64', Rule::unique('products', 'sku')->ignore($productId)],
            'name'          => ['required', 'string', 'max:255'],
            'slug'          => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($productId)],
            'description'   => ['nullable', 'string'],
            'age_group'     => ['nullable', 'array'],
            'age_group.*'   => ['string', Rule::in(['1-2', '2-3', '3-4', '4-5', '5-6', '6-7', '7-8', '8-9', '9-10', '10-11', '11-12', '12-13', '13-14', '14-15', '15-16'])],
            'gender'        => ['nullable', Rule::in(['boy', 'girl', 'unisex'])],
            'brand'         => ['nullable', 'string', 'max:128'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'discount'      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active'     => ['nullable', 'boolean'],

            'images'        => ['nullable', 'array'],
            'images.*'      => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],

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
        ]);
    }
}
