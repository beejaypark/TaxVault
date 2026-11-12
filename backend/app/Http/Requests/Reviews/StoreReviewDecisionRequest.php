<?php

namespace App\Http\Requests\Reviews;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'decision' => [
                'required',
                'string',
                'max:50',
            ],

            'reviewer_notes' => [
                'nullable',
                'string',
            ],
            'expense' => [
                'sometimes',
                'array',
            ],
            'expense.transaction_date' => [
                'required_with:expense',
                'date',
            ],
            'expense.direction' => [
                'required_with:expense',
                'string',
                'in:income,expense',
            ],
            'expense.amount' => [
                'required_with:expense',
                'numeric',
                'gt:0',
            ],
            'expense.description' => [
                'required_with:expense',
                'string',
                'max:1000',
            ],
            'transactions' => [
                'sometimes',
                'array',
                'min:1',
            ],
            'transactions.*.transaction_date' => [
                'required',
                'date',
            ],
            'transactions.*.direction' => [
                'required',
                'string',
                'in:income,expense',
            ],
            'transactions.*.amount' => [
                'required',
                'numeric',
                'gt:0',
            ],
            'transactions.*.description' => [
                'required',
                'string',
                'max:1000',
            ],
        ];
    }
}
