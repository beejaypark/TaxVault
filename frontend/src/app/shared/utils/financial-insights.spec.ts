import { TransactionDto } from '../services/tax-workspace-api.service';
import { analyseFinancialYear, forecastCashFlow } from './financial-insights';

function tx(date: string, direction: 'income' | 'expense', amount: number, description: string): TransactionDto {
  return {
    id: `${date}-${description}`,
    financial_year_id: 'fy',
    document_id: null,
    transaction_date: date,
    description,
    direction,
    amount,
    currency: 'AUD',
    source_system: null,
  };
}

describe('analyseFinancialYear', () => {
  const now = new Date(2027, 5, 30);

  it('builds monthly cash flow with a running balance', () => {
    const result = analyseFinancialYear(
      [tx('2026-07-10', 'income', 1000, 'Rent'), tx('2026-08-05', 'expense', 400, 'Plumber')],
      '2026-07-01',
      '2027-06-30',
      now,
    );

    expect(result.months).toHaveLength(12);
    expect(result.months[0].net).toBe(1000);
    expect(result.months[1].cumulative).toBe(600);
    expect(result.months[11].cumulative).toBe(600);
  });

  it('groups payees case-insensitively and ranks by total', () => {
    const result = analyseFinancialYear(
      [
        tx('2026-07-10', 'expense', 100, 'Council Rates'),
        tx('2026-08-10', 'expense', 150, 'council rates '),
        tx('2026-08-11', 'expense', 50, 'Bunnings'),
      ],
      '2026-07-01',
      '2027-06-30',
      now,
    );

    expect(result.topExpenses[0]).toMatchObject({ total: 250, count: 2, months: 2 });
    expect(result.topExpenses[1].name).toBe('Bunnings');
  });

  it('flags concentrated spending, deficits and single-source income', () => {
    const result = analyseFinancialYear(
      [tx('2026-07-10', 'income', 500, 'Tenant'), tx('2026-07-12', 'expense', 900, 'Mortgage')],
      '2026-07-01',
      '2027-06-30',
      now,
    );
    const titles = result.insights.map((item) => item.title).join('|');

    expect(titles).toContain('Expenses exceed income');
    expect(titles).toContain('Mortgage is 100% of spending');
    expect(titles).toContain('Income depends on one source');
  });

  it('detects recurring costs and spending spikes', () => {
    const rows = ['2026-07', '2026-08', '2026-09', '2026-10'].map((month, index) =>
      tx(`${month}-05`, 'expense', index === 3 ? 2000 : 100, 'Insurance'),
    );
    const result = analyseFinancialYear(
      [tx('2026-07-01', 'income', 9000, 'Salary'), tx('2026-08-01', 'income', 9000, 'Salary'), ...rows],
      '2026-07-01',
      '2027-06-30',
      now,
    );
    const titles = result.insights.map((item) => item.title).join('|');

    expect(titles).toContain('Recurring costs worth reviewing');
    expect(titles).toContain('Oct spending spike');
  });

  it('returns no insights for an empty year', () => {
    expect(analyseFinancialYear([], '2026-07-01', '2027-06-30', now).insights).toEqual([]);
  });

  describe('forecastCashFlow', () => {
    const options = { years: 3, incomeGrowthPercent: 0, expenseGrowthPercent: 0 };

    it('returns null without data', () => {
      expect(forecastCashFlow([], '2026-07-01', '2027-06-30', options, now)).toBeNull();
    });

    it('projects the observed run rate for each future year', () => {
      const result = forecastCashFlow(
        [tx('2026-07-10', 'income', 1200, 'Rent'), tx('2026-08-05', 'expense', 600, 'Loan')],
        '2026-07-01',
        '2027-06-30',
        options,
        now,
      );

      expect(result?.monthsObserved).toBe(12);
      expect(result?.openingBalance).toBe(600);
      expect(result?.years.map((year) => year.label)).toEqual(['2027-28', '2028-29', '2029-30']);
      expect(result?.years[2].closingBalance).toBe(600 + 3 * 600);
    });

    it('annualises a part-year and applies growth rates', () => {
      const partYear = new Date(2026, 8, 15);
      const result = forecastCashFlow(
        [tx('2026-07-10', 'income', 1000, 'Rent'), tx('2026-08-05', 'expense', 500, 'Loan')],
        '2026-07-01',
        '2027-06-30',
        { years: 2, incomeGrowthPercent: 10, expenseGrowthPercent: 0 },
        partYear,
      );

      expect(result?.monthsObserved).toBe(3);
      expect(result?.monthlyIncome).toBeCloseTo(1000 / 3);
      expect(result?.years[0].income).toBeCloseTo((1000 / 3) * 12 * 1.1);
      expect(result?.years[1].income).toBeGreaterThan(result!.years[0].income);
    });

    it('reports the first year the balance turns negative', () => {
      const result = forecastCashFlow(
        [tx('2026-07-10', 'income', 100, 'Rent'), tx('2026-08-05', 'expense', 900, 'Loan')],
        '2026-07-01',
        '2027-06-30',
        options,
        now,
      );

      expect(result?.firstNegativeYear).toBe('2027-28');
    });
  });
});
