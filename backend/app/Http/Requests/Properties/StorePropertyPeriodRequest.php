<?php

namespace App\Http\Requests\Properties;

use Illuminate\Foundation\Http\FormRequest;

class StorePropertyPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'period_start' => ['required', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'use_type' => ['required', 'string', 'max:50'],
            'ownership_percentage' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
            'provenance' => ['nullable', 'array'],
        ];
    }
}
