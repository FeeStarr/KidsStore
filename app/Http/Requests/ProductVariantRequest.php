<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $variantId = $this->route('variant')?->id;

        return [
            'sku'           => ['required', 'string', 'max:64',
                Rule::unique('product_variants', 'sku')->ignore($variantId)],
            'name'          => ['nullable', 'string', 'max:120'],
            'age_range_id'  => ['nullable', 'integer', 'exists:age_ranges,id'],
            'size_id'       => ['nullable', 'integer', 'exists:sizes,id'],
            'color_id'      => ['nullable', 'integer', 'exists:colors,id'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'discount'      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'image_id'      => ['nullable', 'integer', 'exists:product_images,id'],
            'image_ids'     => ['nullable', 'array'],
            'image_ids.*'   => ['integer', 'exists:product_images,id'],
            'is_active'     => ['nullable', 'boolean'],
            'option_keys'   => ['nullable', 'array'],
            'option_keys.*' => ['nullable', 'string', 'max:60'],
            'option_values' => ['nullable', 'array'],
            'option_values.*' => ['nullable', 'string', 'max:120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'discount'  => $this->input('discount', 0),
        ]);
    }
}
