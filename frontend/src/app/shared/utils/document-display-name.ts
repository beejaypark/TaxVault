export function documentDisplayName(
  originalFilename: string,
  description: string | null | undefined,
  fields: Record<string, { value?: unknown }> = {},
  fallbackType = '',
): string {
  if (description?.trim()) return description.trim();
  const values = Object.values(fields).map((field) => field?.value == null ? '' : String(field.value)).filter(Boolean);
  const filenameText = originalFilename.replace(/[_-]+/g, ' ');
  const text = `${values.join(' ')} ${filenameText}`;
  const lower = text.toLowerCase();
  const issuer = ['biller_alias', 'biller', 'biller_name', 'pay_to', 'account_name', 'provider', 'supplier_name', 'vendor_name', 'merchant_name', 'payee', 'issuer', 'company_name', 'organization']
    .map((key) => fields[key]?.value).find((value) => typeof value === 'string' && value.trim()) as string | undefined;
  const detectedIssuer = issuer || (lower.includes('brisbane city council') || lower.includes('brisbane council') || /\bbcc\b/i.test(text) ? 'Brisbane City Council' : '');
  const category = lower.includes('sewer') || lower.includes('wastewater') ? 'Sewerage' : lower.includes('rate notice') || /\brates?\b/i.test(text) ? 'Rates' : '';
  const period = text.match(/\bq[1-4]\b/i)?.[0]?.toUpperCase() ?? '';
  const parts = [detectedIssuer, category || fallbackType, period].filter(Boolean);
  return parts.length ? parts.join(' · ') : originalFilename;
}
