<?php

namespace App\Http\Requests\Reviews;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'field_path' => [
                'required',
                'string',
                'max:500',
            ],

            'value' => [
                'required',
            ],
        ];
    }
}
