import { ChangeDetectionStrategy, ChangeDetectorRef, Component, computed, effect, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { DocumentApiService } from '../../services/documents/document-api.service';
import { TaxWorkspaceApiService } from '../../services/tax-workspace-api.service';
import {
  ProjectionAssumptions,
  ScenarioName,
  WealthSummary,
  cashFlowTotals,
  projectNetWorth,
  wealthInsights,
} from '../../utils/wealth';

const W = 640;
const H = 200;
const PAD = { top: 14, right: 12, bottom: 24, left: 56 };
const HORIZONS = [5, 10, 20];
const SCENARIOS: ScenarioName[] = ['conservative', 'base', 'optimistic'];

@Component({
  selector: 'tv-wealth-overview',
  imports: [RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './wealth-overview.component.html',
  styleUrl: './wealth-overview.component.scss',
})
export class WealthOverviewComponent {
  private readonly api = inject(TaxWorkspaceApiService);
  private readonly documents = inject(DocumentApiService);
  private readonly cdr = inject(ChangeDetectorRef);

  readonly width = W;
  readonly height = H;
  readonly horizons = HORIZONS;
  readonly scenarios = SCENARIOS;

  readonly summary = signal<WealthSummary | null>(null);
  readonly failed = signal(false);
  readonly horizon = signal(10);
  readonly assumptions = signal<ProjectionAssumptions>({ incomeGrowth: 3, expenseGrowth: 3, assetGrowth: 4, interestRate: 6 });

  readonly yearLabel = computed(() => {
    const id = this.documents.selectedFinancialYearId();

    return this.documents.financialYears().find((y) => y.id === id)?.year_code ?? '';
  });

  readonly totals = computed(() => cashFlowTotals(this.summary()?.cash_flow ?? []));
  readonly insights = computed(() => {
    const summary = this.summary();

    return summary ? wealthInsights(summary, this.totals()) : [];
  });
  readonly hasAssets = computed(() => (this.summary()?.assets.length ?? 0) > 0);

  readonly paths = computed(() => {
    const summary = this.summary();
    if (!summary) {
      return [];
    }

    return SCENARIOS.map((name) => ({
      name,
      values: projectNetWorth(summary, this.totals(), this.assumptions(), name, 20),
    }));
  });

  readonly cashBars = computed(() => {
    const months = this.summary()?.cash_flow ?? [];
    const max = Math.max(...months.flatMap((m) => [m.income, m.expenses]), 1);
    const inner = W - PAD.left - PAD.right;
    const slot = inner / Math.max(months.length, 1);
    const base = H - PAD.bottom;
    const scale = (value: number) => (value / max) * (H - PAD.top - PAD.bottom);

    return months.map((m, i) => ({
      label: m.month.slice(5),
      title: `${m.month}: income ${this.money(m.income)}, expenses ${this.money(m.expenses)}`,
      x: PAD.left + slot * i + slot * 0.12,
      width: slot * 0.34,
      incomeHeight: scale(m.income),
      expenseHeight: scale(m.expenses),
      base,
    }));
  });

  readonly cashAxis = computed(() => {
    const months = this.summary()?.cash_flow ?? [];
    const max = Math.max(...months.flatMap((m) => [m.income, m.expenses]), 1);

    return [0, 0.5, 1].map((f) => ({ y: H - PAD.bottom - f * (H - PAD.top - PAD.bottom), label: this.compact(max * f) }));
  });

  readonly trend = computed(() => this.line((this.summary()?.history ?? []).map((p) => p.net_worth)));

  readonly projection = computed(() => {
    const years = this.horizon();
    const paths = this.paths().map((p) => ({ name: p.name, values: p.values.slice(0, years + 1) }));
    const all = paths.flatMap((p) => p.values);
    const min = Math.min(...all, 0);
    const max = Math.max(...all, 1);

    return {
      lines: paths.map((p) => ({ name: p.name, points: this.points(p.values, min, max), end: p.values[p.values.length - 1] })),
      axis: [min, (min + max) / 2, max].map((v) => ({ y: this.y(v, min, max), label: this.compact(v) })),
    };
  });

  constructor() {
    effect(() => this.load(this.documents.selectedFinancialYearId()));
  }

  private load(yearId: string | null): void {
    this.api.getWealth(yearId).subscribe({
      next: (summary) => {
        this.summary.set(summary);
        this.assumptions.update((a) => ({ ...a, interestRate: summary.average_interest_rate || a.interestRate }));
        this.cdr.markForCheck();
      },
      error: () => {
        this.failed.set(true);
        this.cdr.markForCheck();
      },
    });
  }

  setAssumption(key: keyof ProjectionAssumptions, event: Event): void {
    const value = Number((event.target as HTMLInputElement).value);
    this.assumptions.update((a) => ({ ...a, [key]: value }));
  }

  money(value: number): string {
    return new Intl.NumberFormat('en-AU', { style: 'currency', currency: 'AUD', maximumFractionDigits: 0 }).format(value);
  }

  percent(value: number | null): string {
    return value === null ? 'n/a' : `${Math.round(value * 100)}%`;
  }

  yearsAt(name: ScenarioName, years: number): number {
    return this.paths().find((p) => p.name === name)?.values[years] ?? 0;
  }

  private compact(value: number): string {
    return new Intl.NumberFormat('en-AU', { notation: 'compact', maximumFractionDigits: 1 }).format(value);
  }

  private y(value: number, min: number, max: number): number {
    return PAD.top + (1 - (value - min) / (max - min || 1)) * (H - PAD.top - PAD.bottom);
  }

  private points(values: number[], min: number, max: number): string {
    const inner = W - PAD.left - PAD.right;
    const step = inner / Math.max(values.length - 1, 1);

    return values.map((v, i) => `${(PAD.left + step * i).toFixed(1)},${this.y(v, min, max).toFixed(1)}`).join(' ');
  }

  private line(values: number[]): { points: string; axis: { y: number; label: string }[] } | null {
    if (values.length < 2) {
      return null;
    }
    const min = Math.min(...values, 0);
    const max = Math.max(...values, 1);

    return {
      points: this.points(values, min, max),
      axis: [min, (min + max) / 2, max].map((v) => ({ y: this.y(v, min, max), label: this.compact(v) })),
    };
  }
}
