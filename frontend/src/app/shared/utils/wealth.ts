export interface WealthAsset {
  kind: 'property' | 'investment';
  id: string;
  label: string;
  value: number | null;
  as_of: string | null;
  is_cost_base?: boolean;
}

export interface WealthSummary {
  assets: WealthAsset[];
  total_assets: number;
  total_liabilities: number;
  net_worth: number;
  average_interest_rate: number;
  history: { month: string; assets: number; liabilities: number; net_worth: number }[];
  cash_flow: { month: string; income: number; expenses: number }[];
}

export interface ProjectionAssumptions {
  incomeGrowth: number;
  expenseGrowth: number;
  assetGrowth: number;
  interestRate: number;
}

export type ScenarioName = 'conservative' | 'base' | 'optimistic';

export const SCENARIO_SHIFT: Record<ScenarioName, { asset: number; expense: number; income: number }> = {
  conservative: { asset: -2, expense: 1, income: -1 },
  base: { asset: 0, expense: 0, income: 0 },
  optimistic: { asset: 2, expense: -1, income: 1 },
};

export interface CashFlowTotals {
  income: number;
  expenses: number;
  net: number;
  savingsRate: number | null;
}

export function cashFlowTotals(months: WealthSummary['cash_flow']): CashFlowTotals {
  const income = months.reduce((sum, m) => sum + m.income, 0);
  const expenses = months.reduce((sum, m) => sum + m.expenses, 0);

  return { income, expenses, net: income - expenses, savingsRate: income > 0 ? (income - expenses) / income : null };
}

/**
 * Year-by-year net worth. Loans are treated as interest-only (balance constant);
 * interest above or below today's average rate adjusts annual cash flow.
 */
export function projectNetWorth(
  summary: Pick<WealthSummary, 'total_assets' | 'total_liabilities' | 'average_interest_rate'>,
  totals: Pick<CashFlowTotals, 'income' | 'expenses'>,
  assumptions: ProjectionAssumptions,
  scenario: ScenarioName,
  years: number,
): number[] {
  const shift = SCENARIO_SHIFT[scenario];
  const incomeGrowth = (assumptions.incomeGrowth + shift.income) / 100;
  const expenseGrowth = (assumptions.expenseGrowth + shift.expense) / 100;
  const assetGrowth = (assumptions.assetGrowth + shift.asset) / 100;
  const extraInterest = (summary.total_liabilities * (assumptions.interestRate - summary.average_interest_rate)) / 100;

  let assets = summary.total_assets;
  let income = totals.income;
  let expenses = totals.expenses;
  const result = [summary.total_assets - summary.total_liabilities];

  for (let year = 1; year <= years; year++) {
    income *= 1 + incomeGrowth;
    expenses *= 1 + expenseGrowth;
    assets = assets * (1 + assetGrowth) + (income - expenses - extraInterest);
    result.push(assets - summary.total_liabilities);
  }

  return result;
}

export interface WealthInsight {
  tone: 'strength' | 'improve';
  text: string;
}

export function wealthInsights(
  summary: WealthSummary,
  totals: CashFlowTotals,
  today = new Date(),
): WealthInsight[] {
  const out: WealthInsight[] = [];
  const pct = (value: number) => `${Math.round(value * 100)}%`;

  if (totals.savingsRate === null) {
    out.push({ tone: 'improve', text: 'No income recorded in the last 12 months, so cash flow cannot be assessed. Import or add transactions.' });
  } else if (totals.savingsRate >= 0.2) {
    out.push({ tone: 'strength', text: `You kept ${pct(totals.savingsRate)} of income over the last 12 months, a healthy savings rate.` });
  } else if (totals.savingsRate >= 0.1) {
    out.push({ tone: 'improve', text: `Savings rate is ${pct(totals.savingsRate)}. Reaching about 20% would speed up wealth building.` });
  } else if (totals.savingsRate >= 0) {
    out.push({ tone: 'improve', text: `Savings rate is only ${pct(totals.savingsRate)}. Review the largest expense categories.` });
  } else {
    out.push({ tone: 'improve', text: 'Expenses exceeded income over the last 12 months. Spending is being funded from savings or debt.' });
  }

  if (summary.total_assets > 0) {
    const lvr = summary.total_liabilities / summary.total_assets;
    if (summary.total_liabilities === 0) {
      out.push({ tone: 'strength', text: 'You have no recorded loans.' });
    } else if (lvr <= 0.4) {
      out.push({ tone: 'strength', text: `Loans are ${pct(lvr)} of assets, a conservative level of debt.` });
    } else if (lvr > 0.6) {
      out.push({ tone: 'improve', text: `Loans are ${pct(lvr)} of assets. High gearing is sensitive to rate rises and falling values.` });
    }

    const largest = summary.assets.reduce((max, a) => Math.max(max, a.value ?? 0), 0);
    if (summary.assets.length > 1 && largest / summary.total_assets > 0.7) {
      out.push({ tone: 'improve', text: `One asset is ${pct(largest / summary.total_assets)} of your total. Consider diversifying.` });
    }
  }

  if (summary.total_liabilities > 0) {
    const cost = summary.total_liabilities * 0.01;
    out.push({
      tone: 'improve',
      text: `A 1% rise in interest rates would cost about ${Math.round(cost).toLocaleString('en-AU')} a year. Check you could absorb it.`,
    });
  }

  const staleLimit = today.getTime() - 180 * 86_400_000;
  const stale = summary.assets.filter(
    (a) => a.is_cost_base || a.as_of === null || new Date(a.as_of).getTime() < staleLimit,
  );
  if (stale.length > 0) {
    out.push({
      tone: 'improve',
      text: `${stale.length} asset value${stale.length === 1 ? ' is' : 's are'} missing or older than 6 months. Update values for an accurate net worth.`,
    });
  }

  if (summary.history.length < 2) {
    out.push({ tone: 'improve', text: 'Record asset values over time to see your net worth trend.' });
  } else {
    const first = summary.history[0].net_worth;
    const last = summary.history[summary.history.length - 1].net_worth;
    if (last > first) {
      out.push({ tone: 'strength', text: 'Net worth has grown across the recorded period.' });
    }
  }

  return out;
}
