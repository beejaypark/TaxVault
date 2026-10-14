<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $userId = $this->user()?->getAuthIdentifier();

        return [
            'financial_year_id' => [
                'required',
                'uuid',
                Rule::exists('financial_years', 'id')
                    ->where('user_id', $userId)
                    ->where('status', 'active'),
            ],
            'document_type_id' => [
                'required',
                'uuid',
                Rule::exists('document_types', 'id')
                    ->where('status', 'active'),
            ],
            'file' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png,webp,tif,tiff',
                'max:20480',
            ],
            'captured_at' => [
                'nullable',
                'date',
            ],
        ];
    }
}