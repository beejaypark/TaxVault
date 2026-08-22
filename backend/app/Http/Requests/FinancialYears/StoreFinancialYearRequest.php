<?php

namespace App\Http\Requests\FinancialYears;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinancialYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'year_code' => [
                'required',
                'string',
                'regex:/^\d{4}-\d{2}$/',
            ],

            'start_date' => [
                'required',
                'date',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $yearCode = $this->input('year_code');

                    if (! is_string($yearCode) || ! preg_match('/^(\d{4})-(\d{2})$/', $yearCode, $matches)) {
                        return;
                    }

                    $startYear = (int) $matches[1];

                    if ($value !== sprintf('%04d-07-01', $startYear)) {
                        $fail(
                            'The start date must be 1 July of the first year of the Australian financial year.'
                        );
                    }
                },
            ],

            'end_date' => [
                'required',
                'date',
                'after:start_date',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $yearCode = $this->input('year_code');

                    if (! is_string($yearCode) || ! preg_match('/^(\d{4})-(\d{2})$/', $yearCode, $matches)) {
                        return;
                    }

                    $startYear = (int) $matches[1];
                    $expectedEndDate = sprintf('%04d-06-30', $startYear + 1);

                    if ($value !== $expectedEndDate) {
                        $fail(
                            'The end date must be 30 June of the year following the first year of the Australian financial year.'
                        );
                    }
                },
            ],

            'status' => [
                'sometimes',
                'string',
                Rule::in(['active', 'inactive']),
            ],
        ];
    }
}
