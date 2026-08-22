<?php

namespace App\Http\Requests\Reporting;

use Illuminate\Foundation\Http\FormRequest;

class CreateFinancialYearExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'financial_year_id' => [
                'required',
                'uuid',
            ],
        ];
    }
}
