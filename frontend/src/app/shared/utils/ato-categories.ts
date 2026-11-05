export interface AtoCategoryOption {
  code: string;
  label: string;
  group: 'Income' | 'Rental property' | 'Deductions';
  direction: 'income' | 'expense';
  ref: string;
}

type Row = [string, string, AtoCategoryOption['group'], AtoCategoryOption['direction'], string];

// Keep in sync with backend/app/Domain/Transactions/AtoCategory.php; the API validates the codes.
const ROWS: Row[] = [
  ['salary', 'Salary and wages', 'Income', 'income', 'Item 1'],
  ['bank_interest', 'Bank interest', 'Income', 'income', 'Item 10'],
  ['dividends', 'Dividends', 'Income', 'income', 'Item 11'],
  ['other_income', 'Other income', 'Income', 'income', 'Item 24'],
  ['rental_income', 'Rental income', 'Rental property', 'income', 'Item 21'],
  ['rental_advertising', 'Advertising for tenants', 'Rental property', 'expense', 'Item 21'],
  ['rental_body_corporate', 'Body corporate fees', 'Rental property', 'expense', 'Item 21'],
  ['rental_borrowing_expenses', 'Borrowing expenses', 'Rental property', 'expense', 'Item 21'],
  ['rental_cleaning', 'Cleaning', 'Rental property', 'expense', 'Item 21'],
  ['rental_council_rates', 'Council rates', 'Rental property', 'expense', 'Item 21'],
  ['rental_capital_works', 'Capital works deductions', 'Rental property', 'expense', 'Item 21'],
  ['rental_depreciation', 'Capital allowances (depreciation)', 'Rental property', 'expense', 'Item 21'],
  ['rental_gardening', 'Gardening and lawn mowing', 'Rental property', 'expense', 'Item 21'],
  ['rental_agent_fees', 'Property agent fees', 'Rental property', 'expense', 'Item 21'],
  ['rental_insurance', 'Insurance', 'Rental property', 'expense', 'Item 21'],
  ['rental_interest', 'Interest on loans', 'Rental property', 'expense', 'Item 21'],
  ['rental_land_tax', 'Land tax', 'Rental property', 'expense', 'Item 21'],
  ['rental_legal', 'Legal fees', 'Rental property', 'expense', 'Item 21'],
  ['rental_pest_control', 'Pest control', 'Rental property', 'expense', 'Item 21'],
  ['rental_repairs', 'Repairs and maintenance', 'Rental property', 'expense', 'Item 21'],
  ['rental_water', 'Water charges', 'Rental property', 'expense', 'Item 21'],
  ['rental_other', 'Other rental expenses', 'Rental property', 'expense', 'Item 21'],
  ['work_car', 'Work-related car expenses', 'Deductions', 'expense', 'D1'],
  ['work_travel', 'Work-related travel', 'Deductions', 'expense', 'D2'],
  ['work_clothing', 'Work-related clothing', 'Deductions', 'expense', 'D3'],
  ['work_self_education', 'Work-related self-education', 'Deductions', 'expense', 'D4'],
  ['work_other', 'Other work-related expenses', 'Deductions', 'expense', 'D5'],
  ['work_from_home', 'Working from home', 'Deductions', 'expense', 'D5'],
  ['donations', 'Gifts and donations', 'Deductions', 'expense', 'D9'],
  ['tax_affairs', 'Cost of managing tax affairs', 'Deductions', 'expense', 'D10'],
  ['other_deduction', 'Other deductions', 'Deductions', 'expense', 'D15'],
];

export const ATO_CATEGORIES: AtoCategoryOption[] = ROWS.map(
  ([code, label, group, direction, ref]) => ({ code, label, group, direction, ref }),
);

export function atoCategoriesFor(direction: 'income' | 'expense'): AtoCategoryOption[] {
  return ATO_CATEGORIES.filter((category) => category.direction === direction);
}

export function atoCategoryLabel(code: string | null | undefined): string {
  const category = ATO_CATEGORIES.find((item) => item.code === code);

  return category ? `${category.label} (${category.ref})` : '';
}

export function isRentalCategory(code: string | null | undefined): boolean {
  return !!code && code.startsWith('rental_');
}
