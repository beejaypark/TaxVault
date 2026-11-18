<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentExtractionCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'field_path' => ['required', 'string', 'in:invoice_date'],
            'value' => ['required', 'string', 'date_format:Y-m-d'],
        ];
    }
}
