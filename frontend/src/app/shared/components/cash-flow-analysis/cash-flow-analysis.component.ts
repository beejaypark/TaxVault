import { ChangeDetectionStrategy, Component, computed, input, signal } from '@angular/core';

import { TransactionDto } from '../../services/tax-workspace-api.service';
import { analyseFinancialYear, forecastCashFlow } from '../../utils/financial-insights';

const WIDTH = 640;
const HEIGHT = 240;
const PAD = { top: 16, right: 12, bottom: 28, left: 52 };

@Component({
  selector: 'tv-cash-flow-analysis',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './cash-flow-analysis.component.html',
  styleUrl: './cash-flow-analysis.component.scss',
})
export class CashFlowAnalysisComponent {
  readonly transactions = input.required<TransactionDto[]>();
  readonly startDate = input.required<string>();
  readonly endDate = input.required<string>();

  readonly width = WIDTH;
  readonly Math = Math;
  readonly height = HEIGHT;
  readonly horizonOptions = [1, 2, 3, 5, 10];

  readonly horizon = signal(3);
  readonly incomeGrowth = signal(0);
  readonly expenseGrowth = signal(0);

  readonly analysis = computed(() =>
    analyseFinancialYear(this.transactions(), this.startDate(), this.endDate()),
  );

  readonly forecast = computed(() =>
    forecastCashFlow(this.transactions(), this.startDate(), this.endDate(), {
      years: this.horizon(),
      incomeGrowthPercent: this.incomeGrowth(),
      expenseGrowthPercent: this.expenseGrowth(),
    }),
  );

  readonly forecastScale = computed(() => {
    const years = this.forecast()?.years ?? [];

    return Math.max(...years.map((year) => Math.abs(year.closingBalance)), 1);
  });

  readonly hasData = computed(() => this.transactions().length > 0);

  private readonly bounds = computed(() => {
    const months = this.analysis().months;
    const values = months.flatMap((month) => [month.income, month.expenses, month.cumulative, 0]);

    return { min: Math.min(...values), max: Math.max(...values, 1) };
  });

  private y(value: number): number {
    const { min, max } = this.bounds();
    const span = max - min || 1;

    return PAD.top + (1 - (value - min) / span) * (HEIGHT - PAD.top - PAD.bottom);
  }

  private x(index: number, count: number): number {
    const inner = WIDTH - PAD.left - PAD.right;

    return PAD.left + (inner / count) * (index + 0.5);
  }

  readonly bars = computed(() => {
    const months = this.analysis().months;
    const slot = (WIDTH - PAD.left - PAD.right) / months.length;
    const barWidth = Math.max(4, slot * 0.32);
    const zero = this.y(0);

    return months.map((month, index) => {
      const centre = this.x(index, months.length);
      const incomeY = this.y(month.income);
      const expenseY = this.y(month.expenses);

      return {
        label: month.label,
        centre,
        barWidth,
        incomeX: centre - barWidth - 1,
        incomeY,
        incomeHeight: Math.max(0, zero - incomeY),
        expenseX: centre + 1,
        expenseY,
        expenseHeight: Math.max(0, zero - expenseY),
        title: `${month.label}: income ${this.format(month.income)}, expenses ${this.format(month.expenses)}, net ${this.format(month.net)}`,
      };
    });
  });

  readonly linePoints = computed(() => {
    const months = this.analysis().months;

    return months
      .map((month, index) => `${this.x(index, months.length)},${this.y(month.cumulative)}`)
      .join(' ');
  });

  readonly zeroY = computed(() => this.y(0));

  readonly gridLines = computed(() => {
    const { min, max } = this.bounds();

    return [0, 1, 2, 3].map((step) => {
      const value = min + ((max - min) * step) / 3;

      return { y: this.y(value), label: this.compact(value) };
    });
  });

  readonly finalBalance = computed(() => {
    const months = this.analysis().months;

    return months.length > 0 ? months[months.length - 1].cumulative : 0;
  });

  readonly chartDescription = computed(
    () => `Monthly income, expenses and running balance. Year-end balance ${this.format(this.finalBalance())}.`,
  );

  setHorizon(value: string): void {
    this.horizon.set(Number(value));
  }

  setIncomeGrowth(value: string): void {
    this.incomeGrowth.set(this.parsePercent(value));
  }

  setExpenseGrowth(value: string): void {
    this.expenseGrowth.set(this.parsePercent(value));
  }

  format(value: number): string {
    return new Intl.NumberFormat('en-AU', { style: 'currency', currency: 'AUD' }).format(value);
  }

  percent(value: number): string {
    return `${Math.round(value * 100)}%`;
  }

  private parsePercent(value: string): number {
    const parsed = Number(value);

    return Number.isFinite(parsed) ? Math.min(Math.max(parsed, -50), 50) : 0;
  }

  private compact(value: number): string {
    return new Intl.NumberFormat('en-AU', {
      notation: 'compact',
      maximumFractionDigits: 1,
    }).format(value);
  }
}
