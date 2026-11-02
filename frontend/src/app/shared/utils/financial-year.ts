export function formatFinancialYear(yearCode: string): string {
  const match = /^(\d{4})-(\d{2})$/.exec(yearCode);

  return match ? `${match[1]} - ${match[2]}` : yearCode;
}

export function normalizeFinancialYearInput(value: string): string {
  return value.replace(/\s+/g, '');
}
