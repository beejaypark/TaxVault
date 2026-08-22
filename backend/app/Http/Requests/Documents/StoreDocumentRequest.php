<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
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
            'document_type_id' => [
                'required',
                'uuid',
            ],
            'storage_disk' => [
                'required',
                'string',
                'max:50',
            ],
            'object_key' => [
                'required',
                'string',
                'max:1024',
            ],
            'content_sha256' => [
                'required',
                'string',
                'size:64',
                'regex:/^[a-fA-F0-9]{64}$/',
            ],
            'original_filename' => [
                'nullable',
                'string',
                'max:512',
            ],
            'mime_type' => [
                'nullable',
                'string',
                'max:255',
            ],
            'size_bytes' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'captured_at' => [
                'nullable',
                'date',
            ],
            'uploaded_at' => [
                'nullable',
                'date',
            ],
            'provenance' => [
                'nullable',
                'array',
            ],
            'status' => [
                'nullable',
                'string',
                'max:30',
            ],
        ];
    }
}
