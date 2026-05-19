<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_date'    => ['required', 'date'],
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'method'          => ['nullable', Rule::in(['cash', 'card', 'transfer', 'mobile', 'other'])],
            'transaction_id'  => ['nullable', 'string', 'max:128'],
            'note'            => ['nullable', 'string'],
        ];
    }
}
