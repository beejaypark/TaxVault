<?php

namespace App\Http\Requests\Transactions;

use App\Domain\Transactions\AtoCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'transaction_date' => ['required', 'date'],
            'direction' => ['required', 'string', Rule::in(['income', 'expense'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['required', 'string', 'max:1000'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'ato_category' => ['nullable', 'string', Rule::in(AtoCategory::codes())],
            'property_id' => [
                'nullable',
                'uuid',
                Rule::exists('properties', 'id')->where('user_id', $this->user()?->getKey()),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $category = $this->input('ato_category');
                $definition = $category ? (AtoCategory::all()[$category] ?? null) : null;

                if ($definition === null) {
                    return;
                }

                if ($definition['direction'] !== $this->input('direction')) {
                    $validator->errors()->add('ato_category', 'This tax item does not match the transaction type.');
                }

                if (AtoCategory::isRental($category) && ! $this->filled('property_id')) {
                    $validator->errors()->add('property_id', 'Choose the rental property for this tax item.');
                }
            },
        ];
    }
}
