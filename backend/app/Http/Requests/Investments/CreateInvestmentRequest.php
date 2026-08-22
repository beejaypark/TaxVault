<?php

namespace App\Http\Requests\Investments;

use Illuminate\Foundation\Http\FormRequest;

class CreateInvestmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'investment_type' => [
                'required',
                'string',
                'max:50',
            ],
            'property_id' => [
                'nullable',
                'uuid',
            ],
            'acquisition_date' => [
                'required',
                'date',
            ],
            'disposal_date' => [
                'nullable',
                'date',
                'after_or_equal:acquisition_date',
            ],
            'quantity' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'ownership_percentage' => [
                'nullable',
                'numeric',
                'between:0,100',
            ],
            'cost_base' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'incidental_costs' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'proceeds' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'source_system' => [
                'nullable',
                'string',
                'max:100',
            ],
            'external_id' => [
                'nullable',
                'string',
                'max:255',
            ],
            'metadata' => [
                'nullable',
                'array',
            ],
        ];
    }
}
