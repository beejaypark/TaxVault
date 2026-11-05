import { WealthSummary, cashFlowTotals, projectNetWorth, wealthInsights } from './wealth';

const summary: WealthSummary = {
  assets: [
    { kind: 'property', id: 'p', label: 'House', value: 900_000, as_of: '2026-09-01' },
    { kind: 'investment', id: 'i', label: 'shares', value: 100_000, as_of: '2026-09-01' },
  ],
  total_assets: 1_000_000,
  total_liabilities: 500_000,
  net_worth: 500_000,
  average_interest_rate: 6,
  history: [],
  cash_flow: [
    { month: '2026-08', income: 5000, expenses: 3000 },
    { month: '2026-09', income: 5000, expenses: 3000 },
  ],
};

describe('wealth utils', () => {
  it('totals cash flow and the savings rate', () => {
    const totals = cashFlowTotals(summary.cash_flow);

    expect(totals).toEqual({ income: 10000, expenses: 6000, net: 4000, savingsRate: 0.4 });
  });

  it('has no savings rate without income', () => {
    expect(cashFlowTotals([]).savingsRate).toBeNull();
  });

  it('projects net worth with growth and savings', () => {
    const totals = { income: 100_000, expenses: 60_000 };
    const assumptions = { incomeGrowth: 0, expenseGrowth: 0, assetGrowth: 0, interestRate: 6 };

    const path = projectNetWorth(summary, totals, assumptions, 'base', 2);

    expect(path).toEqual([500_000, 540_000, 580_000]);
  });

  it('orders scenarios and applies an interest rate rise', () => {
    const totals = { income: 100_000, expenses: 60_000 };
    const assumptions = { incomeGrowth: 3, expenseGrowth: 3, assetGrowth: 4, interestRate: 6 };
    const last = (s: 'conservative' | 'base' | 'optimistic', rate = 6) =>
      projectNetWorth(summary, totals, { ...assumptions, interestRate: rate }, s, 10).at(-1)!;

    expect(last('conservative')).toBeLessThan(last('base'));
    expect(last('base')).toBeLessThan(last('optimistic'));
    expect(last('base', 8)).toBeLessThan(last('base', 6));
  });

  it('flags a low savings rate and stale values, and praises low debt', () => {
    const low = wealthInsights(
      { ...summary, assets: [{ ...summary.assets[0], is_cost_base: true }], total_liabilities: 100_000 },
      { income: 100, expenses: 95, net: 5, savingsRate: 0.05 },
      new Date('2026-10-01'),
    );

    expect(low.some((i) => i.tone === 'improve' && i.text.includes('5%'))).toBe(true);
    expect(low.some((i) => i.tone === 'improve' && i.text.includes('older than 6 months'))).toBe(true);
    expect(low.some((i) => i.tone === 'strength' && i.text.includes('conservative'))).toBe(true);
  });
});
