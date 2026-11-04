import { TransactionDto } from '../services/tax-workspace-api.service';

export interface CashFlowMonth {
  key: string;
  label: string;
  income: number;
  expenses: number;
  net: number;
  cumulative: number;
}

export interface PayeeSummary {
  name: string;
  total: number;
  count: number;
  share: number;
  months: number;
}

export type InsightKind = 'reduce' | 'grow' | 'watch' | 'info';

export interface Insight {
  kind: InsightKind;
  title: string;
  detail: string;
}

export interface FinancialAnalysis {
  months: CashFlowMonth[];
  topExpenses: PayeeSummary[];
  topIncome: PayeeSummary[];
  insights: Insight[];
}

const money = (value: number): string =>
  new Intl.NumberFormat('en-AU', {
    style: 'currency',
    currency: 'AUD',
    maximumFractionDigits: 0,
  }).format(value);

export interface ForecastOptions {
  years: number;
  incomeGrowthPercent: number;
  expenseGrowthPercent: number;
}

export interface ForecastYear {
  label: string;
  income: number;
  expenses: number;
  net: number;
  closingBalance: number;
}

export interface CashFlowForecast {
  monthlyIncome: number;
  monthlyExpenses: number;
  monthsObserved: number;
  openingBalance: number;
  years: ForecastYear[];
  firstNegativeYear: string | null;
}

// Run-rate forecast: observed monthly averages for the selected year, projected forward with optional growth.
export function forecastCashFlow(
  transactions: TransactionDto[],
  startDate: string,
  endDate: string,
  options: ForecastOptions,
  now: Date = new Date(),
): CashFlowForecast | null {
  if (transactions.length === 0) {
    return null;
  }

  const keys = monthKeys(startDate, endDate);
  const currentKey = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
  const lastTransactionKey = transactions
    .map((item) => item.transaction_date.slice(0, 7))
    .sort()
    .pop() ?? keys[0];
  const cutoff = currentKey > lastTransactionKey ? currentKey : lastTransactionKey;
  const observed = Math.min(keys.length, Math.max(1, keys.filter((key) => key <= cutoff).length));

  const totalIncome = transactions
    .filter((item) => item.direction === 'income')
    .reduce((sum, item) => sum + Number(item.amount), 0);
  const totalExpenses = transactions
    .filter((item) => item.direction === 'expense')
    .reduce((sum, item) => sum + Number(item.amount), 0);

  const monthlyIncome = totalIncome / observed;
  const monthlyExpenses = totalExpenses / observed;
  const remaining = keys.length - observed;
  const openingBalance = totalIncome - totalExpenses + (monthlyIncome - monthlyExpenses) * remaining;

  const startYear = Number(startDate.slice(0, 4));
  const years: ForecastYear[] = [];
  let balance = openingBalance;

  for (let step = 1; step <= options.years; step++) {
    const income = monthlyIncome * 12 * Math.pow(1 + options.incomeGrowthPercent / 100, step);
    const expenses = monthlyExpenses * 12 * Math.pow(1 + options.expenseGrowthPercent / 100, step);

    balance += income - expenses;
    years.push({
      label: `${startYear + step}-${String(startYear + step + 1).slice(2)}`,
      income,
      expenses,
      net: income - expenses,
      closingBalance: balance,
    });
  }

  return {
    monthlyIncome,
    monthlyExpenses,
    monthsObserved: observed,
    openingBalance,
    years,
    firstNegativeYear: years.find((year) => year.closingBalance < 0)?.label ?? null,
  };
}

export function monthKeys(startDate: string, endDate: string): string[] {
  const start = new Date(`${startDate.slice(0, 10)}T00:00:00`);
  const end = new Date(`${endDate.slice(0, 10)}T00:00:00`);
  const keys: string[] = [];

  for (
    let month = new Date(start.getFullYear(), start.getMonth(), 1);
    month <= end;
    month = new Date(month.getFullYear(), month.getMonth() + 1, 1)
  ) {
    keys.push(`${month.getFullYear()}-${String(month.getMonth() + 1).padStart(2, '0')}`);
  }

  return keys;
}

function normaliseName(description: string): string {
  return description.trim().replace(/\s+/g, ' ').toLowerCase() || 'unspecified';
}

function summarisePayees(transactions: TransactionDto[], total: number): PayeeSummary[] {
  const groups = new Map<string, { name: string; total: number; count: number; months: Set<string> }>();

  for (const item of transactions) {
    const key = normaliseName(item.description);
    const group = groups.get(key) ?? {
      name: item.description.trim() || 'Unspecified',
      total: 0,
      count: 0,
      months: new Set<string>(),
    };

    group.total += Number(item.amount);
    group.count += 1;
    group.months.add(item.transaction_date.slice(0, 7));
    groups.set(key, group);
  }

  return [...groups.values()]
    .map((group) => ({
      name: group.name,
      total: group.total,
      count: group.count,
      months: group.months.size,
      share: total > 0 ? group.total / total : 0,
    }))
    .sort((a, b) => b.total - a.total);
}

export function analyseFinancialYear(
  transactions: TransactionDto[],
  startDate: string,
  endDate: string,
  now: Date = new Date(),
): FinancialAnalysis {
  const keys = monthKeys(startDate, endDate);
  const byMonth = new Map<string, CashFlowMonth>(
    keys.map((key) => {
      const [year, month] = key.split('-').map(Number);

      return [key, {
        key,
        label: new Intl.DateTimeFormat('en-AU', { month: 'short' }).format(new Date(year, month - 1, 1)),
        income: 0,
        expenses: 0,
        net: 0,
        cumulative: 0,
      }];
    }),
  );

  for (const item of transactions) {
    const month = byMonth.get(item.transaction_date.slice(0, 7));

    if (!month) {
      continue;
    }

    if (item.direction === 'income') {
      month.income += Number(item.amount);
    } else {
      month.expenses += Number(item.amount);
    }
  }

  let running = 0;
  const months = [...byMonth.values()].map((month) => {
    running += month.income - month.expenses;

    return { ...month, net: month.income - month.expenses, cumulative: running };
  });

  const expenses = transactions.filter((item) => item.direction === 'expense');
  const income = transactions.filter((item) => item.direction === 'income');
  const totalExpenses = expenses.reduce((sum, item) => sum + Number(item.amount), 0);
  const totalIncome = income.reduce((sum, item) => sum + Number(item.amount), 0);
  const topExpenses = summarisePayees(expenses, totalExpenses);
  const topIncome = summarisePayees(income, totalIncome);

  return {
    months,
    topExpenses: topExpenses.slice(0, 5),
    topIncome: topIncome.slice(0, 5),
    insights: buildInsights(months, topExpenses, topIncome, totalIncome, totalExpenses, now),
  };
}

function buildInsights(
  months: CashFlowMonth[],
  expensePayees: PayeeSummary[],
  incomePayees: PayeeSummary[],
  totalIncome: number,
  totalExpenses: number,
  now: Date,
): Insight[] {
  const insights: Insight[] = [];

  if (totalIncome === 0 && totalExpenses === 0) {
    return insights;
  }

  const currentKey = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
  const elapsed = months.filter((month) => month.key <= currentKey);
  const completed = elapsed.length > 0 ? elapsed : months;
  const activeMonths = completed.filter((month) => month.expenses > 0);

  if (totalExpenses > totalIncome && totalIncome > 0) {
    insights.push({
      kind: 'watch',
      title: 'Expenses exceed income',
      detail: `Spending is ${money(totalExpenses - totalIncome)} higher than income this year (${money(totalExpenses)} vs ${money(totalIncome)}).`,
    });
  }

  const negativeMonths = completed.filter((month) => month.net < 0).length;
  if (negativeMonths >= 2) {
    insights.push({
      kind: 'watch',
      title: `${negativeMonths} months of negative cash flow`,
      detail: 'Check whether large one-off costs or missing income records explain the shortfall.',
    });
  }

  const topExpense = expensePayees[0];
  if (topExpense && totalExpenses > 0 && topExpense.share >= 0.25) {
    insights.push({
      kind: 'reduce',
      title: `${topExpense.name} is ${Math.round(topExpense.share * 100)}% of spending`,
      detail: `${money(topExpense.total)} across ${topExpense.count} transaction${topExpense.count === 1 ? '' : 's'}. This is the largest place to review or renegotiate.`,
    });
  }

  const recurring = expensePayees.filter((payee) => payee.months >= 3).slice(0, 3);
  if (recurring.length > 0) {
    insights.push({
      kind: 'reduce',
      title: 'Recurring costs worth reviewing',
      detail: recurring
        .map((payee) => `${payee.name} (${payee.months} months, ${money(payee.total)})`)
        .join('; '),
    });
  }

  if (activeMonths.length >= 3) {
    const average = activeMonths.reduce((sum, month) => sum + month.expenses, 0) / activeMonths.length;
    const spike = [...activeMonths].sort((a, b) => b.expenses - a.expenses)[0];

    if (average > 0 && spike.expenses >= average * 1.5) {
      insights.push({
        kind: 'reduce',
        title: `${spike.label} spending spike`,
        detail: `${money(spike.expenses)} is ${Math.round((spike.expenses / average - 1) * 100)}% above the monthly average of ${money(average)}.`,
      });
    }
  }

  const topIncome = incomePayees[0];
  if (topIncome && totalIncome > 0 && topIncome.share >= 0.7 && incomePayees.length >= 1) {
    insights.push({
      kind: 'grow',
      title: 'Income depends on one source',
      detail: `${topIncome.name} provides ${Math.round(topIncome.share * 100)}% of income. Adding or documenting other sources reduces risk.`,
    });
  }

  const incomeMonths = completed.filter((month) => month.income > 0).length;
  if (totalIncome > 0 && completed.length >= 3 && incomeMonths <= completed.length / 2) {
    insights.push({
      kind: 'grow',
      title: 'Income is irregular',
      detail: `Income was recorded in ${incomeMonths} of ${completed.length} months. Make sure every payment has supporting evidence.`,
    });
  }

  if (totalIncome === 0 && totalExpenses > 0) {
    insights.push({
      kind: 'grow',
      title: 'No income recorded',
      detail: 'Upload income evidence (rent statements, payment summaries) so your accountant sees the full picture.',
    });
  }

  if (insights.length === 0) {
    insights.push({
      kind: 'info',
      title: 'No unusual patterns found',
      detail: 'Spending and income look steady based on the records uploaded so far.',
    });
  }

  return insights;
}
