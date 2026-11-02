import { describe, expect, it } from 'vitest';

import {
  formatFinancialYear,
  normalizeFinancialYearInput,
} from './financial-year';

describe('financial-year formatting', () => {
  it('displays the full starting year and spaced separator', () => {
    expect(formatFinancialYear('2025-26')).toBe('2025 - 26');
  });

  it('keeps normalized year codes unchanged for API requests', () => {
    expect(normalizeFinancialYearInput('2025 - 26')).toBe('2025-26');
  });
});
