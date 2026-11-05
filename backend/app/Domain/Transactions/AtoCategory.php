<?php

namespace App\Domain\Transactions;

/**
 * Tax-return line items used to label transactions. Labels follow the ATO
 * individual return and rental property schedule; they organise evidence and
 * are not tax advice.
 */
final class AtoCategory
{
    public const RENTAL_INCOME = 'rental_income';

    public const RENTAL_INTEREST = 'rental_interest';

    /**
     * @return array<string, array{label: string, group: string, direction: string, ato_ref: string|null}>
     */
    public static function all(): array
    {
        return [
            'salary' => ['label' => 'Salary and wages', 'group' => 'Income', 'direction' => 'income', 'ato_ref' => 'Item 1'],
            'bank_interest' => ['label' => 'Bank interest', 'group' => 'Income', 'direction' => 'income', 'ato_ref' => 'Item 10'],
            'dividends' => ['label' => 'Dividends', 'group' => 'Income', 'direction' => 'income', 'ato_ref' => 'Item 11'],
            'other_income' => ['label' => 'Other income', 'group' => 'Income', 'direction' => 'income', 'ato_ref' => 'Item 24'],

            'rental_income' => ['label' => 'Rental income', 'group' => 'Rental property', 'direction' => 'income', 'ato_ref' => 'Item 21'],
            'rental_advertising' => ['label' => 'Advertising for tenants', 'group' => 'Rental property', 'direction' => 'expense', 'ato_ref' => 'Item 21'],
            'rental_body_corporate' => ['label' => 'Body corporate fees', 'group' => 'Rental property', 'direction' => 'expense', 'ato_ref' => 'Item 21'],
            'rental_borrowing_expenses' => ['label' => 'Borrowing expenses', 'group' => 'Rental property', 'direction' => 'expense', 'ato_ref' => 'Item 21'],
            'rental_cleaning' => ['label' => 'Cleaning', 'group' => 'Rental property', 'direction' => 'expense', 'ato_ref' => 'Item 21'],
            'rental_council_rates' => ['label' => 'Council rates', 'group' => 'Rental property', 'direction' => 'expense', 'ato_ref' => 'Item 21'],
            'rental_capital_works' => ['label' => 'Capital works deductions', 'group' => 'Rental property', 'direction' => 'expense', 'ato_ref' => 'Item 21'],
            'rental_depreciation' => ['label' => 'Capital allowances (depreciation)', 'group' => 'Rental property', 'direction' => 'expense', 'ato_ref' => 'Item 21'],
            'rental_gardening' => ['label' => 'Gardening and lawn mowing', 'group' => 'Rental property', 'direction' => 'expense', 'ato_ref' => 'Item 21'],
            'rental_agent_fees' => ['label' => 'Property agent fees', 'group' => 'Rental property', 'direction' => 'expense', 'ato_ref' => 'Item 21'],
            'rental_insurance' => ['label' => 'Insurance', 'group' => 'Rental property', 'direction' => 'expense', 'ato_ref' => 'Item 21'],
            'rental_interest' => ['label' => 'Interest on loans', 'group' => 'Rental property', 'direction' => 'expense', 'ato_ref' => 'Item 21'],
            'rental_land_tax' => ['label' => 'Land tax', 'group' => 'Rental property', 'direction' => 'expense', 'ato_ref' => 'Item 21'],
            'rental_legal' => ['label' => 'Legal fees', 'group' => 'Rental property', 'direction' => 'expense', 'ato_ref' => 'Item 21'],
            'rental_pest_control' => ['label' => 'Pest control', 'group' => 'Rental property', 'direction' => 'expense', 'ato_ref' => 'Item 21'],
            'rental_repairs' => ['label' => 'Repairs and maintenance', 'group' => 'Rental property', 'direction' => 'expense', 'ato_ref' => 'Item 21'],
            'rental_water' => ['label' => 'Water charges', 'group' => 'Rental property', 'direction' => 'expense', 'ato_ref' => 'Item 21'],
            'rental_other' => ['label' => 'Other rental expenses', 'group' => 'Rental property', 'direction' => 'expense', 'ato_ref' => 'Item 21'],

            'work_car' => ['label' => 'Work-related car expenses', 'group' => 'Deductions', 'direction' => 'expense', 'ato_ref' => 'D1'],
            'work_travel' => ['label' => 'Work-related travel', 'group' => 'Deductions', 'direction' => 'expense', 'ato_ref' => 'D2'],
            'work_clothing' => ['label' => 'Work-related clothing', 'group' => 'Deductions', 'direction' => 'expense', 'ato_ref' => 'D3'],
            'work_self_education' => ['label' => 'Work-related self-education', 'group' => 'Deductions', 'direction' => 'expense', 'ato_ref' => 'D4'],
            'work_other' => ['label' => 'Other work-related expenses', 'group' => 'Deductions', 'direction' => 'expense', 'ato_ref' => 'D5'],
            'work_from_home' => ['label' => 'Working from home', 'group' => 'Deductions', 'direction' => 'expense', 'ato_ref' => 'D5'],
            'donations' => ['label' => 'Gifts and donations', 'group' => 'Deductions', 'direction' => 'expense', 'ato_ref' => 'D9'],
            'tax_affairs' => ['label' => 'Cost of managing tax affairs', 'group' => 'Deductions', 'direction' => 'expense', 'ato_ref' => 'D10'],
            'other_deduction' => ['label' => 'Other deductions', 'group' => 'Deductions', 'direction' => 'expense', 'ato_ref' => 'D15'],
        ];
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::all());
    }

    public static function isRental(string $code): bool
    {
        return str_starts_with($code, 'rental_');
    }
}
