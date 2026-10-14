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
        ];
    }
}
