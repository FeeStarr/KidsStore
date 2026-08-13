<?php

namespace App\Http\Requests;

use App\Models\Deal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $dealId = $this->route('deal')?->id;

        return [
            'title'         => ['required', 'string', 'max:255'],
            'slug'          => ['nullable', 'string', 'max:255', Rule::unique('deals', 'slug')->ignore($dealId)],
            'description'   => ['nullable', 'string'],
            'discount_type' => ['required', Rule::in([Deal::TYPE_PERCENTAGE, Deal::TYPE_FIXED_AMOUNT, Deal::TYPE_FIXED_PRICE])],
            'discount_value'=> ['required', 'numeric', 'min:0'],
            'starts_at'     => ['required', 'date'],
            'ends_at'       => ['required', 'date', 'after_or_equal:starts_at'],
            'status'        => ['nullable', Rule::in(['draft', 'scheduled', 'active', 'expired', 'cancelled'])],
            'is_featured'   => ['nullable', 'boolean'],
            'max_uses'      => ['nullable', 'integer', 'min:1'],
            'banner_image'  => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'thumbnail_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'product_ids'   => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ];
    }

    /**
     * Business-rule validation beyond the basic shape.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $type = $this->input('discount_type');
            $value = (float) $this->input('discount_value');

            if ($type === Deal::TYPE_PERCENTAGE && ($value <= 0 || $value > 100)) {
                $validator->errors()->add('discount_value', 'Percentage discount must be between 0 and 100.');
            }
        });
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'is_featured' => $this->boolean('is_featured', false),
        ]);

        if (! $this->filled('slug') && $this->filled('title')) {
            $this->merge(['slug' => \Illuminate\Support\Str::slug($this->input('title'))]);
        }
    }
}
