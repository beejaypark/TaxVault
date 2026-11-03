<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ConfirmDocumentPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'payment_date' => ['required', 'date'],
            'payment_method' => [
                'required',
                'string',
                Rule::in([
                    'bpay',
                    'bank_transfer',
                    'card',
                    'direct_debit',
                    'cash',
                    'other',
                ]),
            ],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999.99'],
        ];
    }
}
