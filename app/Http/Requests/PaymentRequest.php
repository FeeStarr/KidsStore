<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\PaymentMethod;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $active = PaymentMethod::where('is_active', true)->pluck('key')->toArray();
        if (empty($active)) {
            $active = ['transfer'];
        }

        return [
            'payment_date'    => ['required', 'date'],
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'method'          => ['nullable', Rule::in($active)],
            'transaction_id'  => ['nullable', 'string', 'max:128'],
            'note'            => ['nullable', 'string'],
        ];
    }
}
