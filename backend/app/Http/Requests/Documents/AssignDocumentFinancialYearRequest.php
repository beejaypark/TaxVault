<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AssignDocumentFinancialYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $userId = $this->user()?->getAuthIdentifier();

        return [
            'financial_year_code' => [
                'required_without:financial_year_id',
                'nullable',
                'string',
                'regex:/^\d{4}-\d{2}$/',
            ],
            'financial_year_id' => [
                'required_without:financial_year_code',
                'nullable',
                'uuid',
                Rule::exists('financial_years', 'id')
                    ->where('user_id', $userId)
                    ->where('status', 'active'),
            ],
        ];
    }
}
