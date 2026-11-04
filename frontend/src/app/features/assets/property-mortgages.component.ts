import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  OnInit,
  inject,
  input,
  output,
} from '@angular/core';
import { FormsModule } from '@angular/forms';

import { TaxIconComponent } from '../../shared/components/tax-icon/tax-icon.component';
import {
  MortgageDto,
  PropertyDto,
  TaxWorkspaceApiService,
} from '../../shared/services/tax-workspace-api.service';
import { AuthService } from '../../shared/services/auth/auth.service';

interface ChartPoint {
  x: number;
  y: number;
  label: string;
  value: number;
}

const CHART = { width: 640, height: 240, left: 64, right: 20, top: 20, bottom: 36 };

@Component({
  selector: 'app-property-mortgages',
  imports: [FormsModule, TaxIconComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <section class="mortgages" aria-label="Mortgages">
      <div class="mortgage-head">
        <h3>Mortgages</h3>
        @if (auth.canEdit && !showForm) {
          <button type="button" class="row-action" (click)="startAdd()"><tv-icon name="plus" /> Add mortgage</button>
        }
      </div>

      @if (errorMessage) { <p class="mortgage-error" role="alert">{{ errorMessage }}</p> }

      @if (showForm) {
        <form class="mortgage-form" (ngSubmit)="save()">
          <label>Bank
            <select name="bank" [(ngModel)]="form.bank">
              @for (bank of banks; track bank) { <option [value]="bank">{{ bank }}</option> }
              <option value="Other">Other</option>
            </select>
          </label>
          @if (form.bank === 'Other') {
            <label>Bank name <input name="bankOther" [(ngModel)]="form.bankOther" maxlength="100" required /></label>
          }
          <label>Loan type
            <select name="loanType" [(ngModel)]="form.loan_type">
              <option value="variable">Variable</option>
              <option value="fixed">Fixed</option>
              <option value="interest_only">Interest only</option>
            </select>
          </label>
          <label>Loan amount (AUD) <input name="loanAmount" type="number" min="0" step="0.01" [(ngModel)]="form.loan_amount" required /></label>
          <label>Interest rate (% p.a.) <input name="rate" type="number" min="0" max="100" step="0.001" [(ngModel)]="form.interest_rate" /></label>
          <label>Start date <input name="start" type="date" [(ngModel)]="form.start_date" /></label>
          <div class="mortgage-actions">
            <button type="button" class="row-action" (click)="closeForm()">Cancel</button>
            <button type="submit" class="row-action" [disabled]="isSaving || !isFormValid">{{ isSaving ? 'Saving…' : 'Save mortgage' }}</button>
          </div>
        </form>
      }

      @if (mortgages.length === 0 && !showForm) {
        <p class="mortgage-empty">No mortgage on this property.</p>
      }

      @for (mortgage of mortgages; track mortgage.id) {
        <article class="mortgage-item">
          <div class="mortgage-summary">
            <div>
              <strong>{{ mortgage.bank_name }}</strong>
              <span>{{ loanTypeLabel(mortgage.loan_type) }}@if (mortgage.interest_rate !== null) { · {{ mortgage.interest_rate }}% p.a. }</span>
            </div>
            <div class="mortgage-amount">{{ formatCurrency(mortgage.loan_amount) }}</div>
            @if (auth.canEdit) {
              <div class="row-actions">
                <button type="button" class="row-action" (click)="startEdit(mortgage)"><tv-icon name="edit" /> Edit</button>
                <button type="button" class="row-action danger" (click)="remove(mortgage)"><tv-icon name="delete" /> Delete</button>
              </div>
            }
          </div>

          @if (auth.canEdit) {
            <form class="interest-form" (ngSubmit)="saveInterest(mortgage)">
              <label>Month <input type="month" name="month-{{ mortgage.id }}" [(ngModel)]="interestMonth" required /></label>
              <label>Interest paid (AUD) <input type="number" min="0" step="0.01" name="amount-{{ mortgage.id }}" [(ngModel)]="interestAmount" required /></label>
              <button type="submit" class="row-action" [disabled]="!interestMonth || interestAmount === null">Save interest</button>
            </form>
          }

          @if (mortgage.interest_entries.length > 0) {
            <ul class="interest-list">
              @for (entry of mortgage.interest_entries; track entry.id) {
                <li>
                  <span>{{ monthLabel(entry.month) }}</span>
                  <strong>{{ formatCurrency(entry.interest_amount) }}</strong>
                  @if (auth.canEdit) {
                    <button type="button" class="row-action danger" (click)="removeInterest(mortgage, entry.id)" aria-label="Delete interest entry"><tv-icon name="delete" /></button>
                  }
                </li>
              }
            </ul>
          }
        </article>
      }

      @if (chartPoints.length > 0) {
        <figure class="interest-chart">
          <figcaption>Monthly mortgage interest trend (all mortgages)</figcaption>
          <svg [attr.viewBox]="'0 0 ' + chart.width + ' ' + chart.height" role="img" aria-label="Monthly mortgage interest trend">
            @for (tick of yTicks; track tick.value) {
              <line [attr.x1]="chart.left" [attr.x2]="chart.width - chart.right" [attr.y1]="tick.y" [attr.y2]="tick.y" class="grid" />
              <text [attr.x]="chart.left - 8" [attr.y]="tick.y + 4" text-anchor="end">{{ formatCompact(tick.value) }}</text>
            }
            <path [attr.d]="areaPath" class="area" />
            <path [attr.d]="linePath" class="line" />
            @for (point of chartPoints; track point.label) {
              <circle [attr.cx]="point.x" [attr.cy]="point.y" r="4" class="dot"><title>{{ point.label }}: {{ formatCurrency(point.value) }}</title></circle>
              <text [attr.x]="point.x" [attr.y]="chart.height - 12" text-anchor="middle">{{ point.label }}</text>
            }
          </svg>
        </figure>
      }
    </section>
  `,
  styles: [`
    .mortgages { display: grid; gap: 12px; width: 100%; padding-top: 16px; border-top: 1px solid var(--tv-color-border); }
    .mortgage-head, .mortgage-summary { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    h3 { margin: 0; font-size: 14px; }
    .mortgage-empty { margin: 0; color: var(--tv-color-text-secondary); font-size: 12px; }
    .mortgage-error { margin: 0; padding: 8px 10px; border-radius: 6px; background: var(--tv-color-danger-soft); color: var(--tv-color-danger); font-size: 12px; }
    .mortgage-form, .interest-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; align-items: end; }
    .mortgage-actions { grid-column: 1 / -1; display: flex; justify-content: flex-end; gap: 8px; }
    label { display: grid; gap: 6px; color: var(--tv-color-text-secondary); font-size: 12px; font-weight: 600; }
    input, select { width: 100%; min-height: 38px; padding: 7px 10px; border: 1px solid var(--tv-color-border); border-radius: 6px; background: white; font-size: 13px; }
    .mortgage-item { display: grid; gap: 10px; padding: 14px; border: 1px solid var(--tv-color-border); border-radius: 8px; background: var(--tv-color-background); }
    .mortgage-summary div:first-child { display: grid; gap: 3px; }
    .mortgage-summary span { color: var(--tv-color-text-secondary); font-size: 12px; }
    .mortgage-amount { font-weight: 700; font-size: 14px; }
    .interest-list { display: flex; flex-wrap: wrap; gap: 8px; margin: 0; padding: 0; list-style: none; }
    .interest-list li { display: inline-flex; align-items: center; gap: 8px; padding: 4px 8px; border: 1px solid var(--tv-color-border); border-radius: 999px; background: white; font-size: 12px; }
    .interest-list .row-action { padding: 2px 6px; }
    .interest-chart { margin: 0; padding: 12px; border: 1px solid var(--tv-color-border); border-radius: 8px; background: white; }
    figcaption { margin-bottom: 6px; font-size: 12px; font-weight: 650; color: var(--tv-color-text-secondary); }
    svg { width: 100%; height: auto; }
    svg text { fill: var(--tv-color-text-secondary); font-size: 11px; }
    .grid { stroke: var(--tv-color-border); stroke-width: 1; }
    .line { fill: none; stroke: var(--tv-color-primary); stroke-width: 2.5; }
    .area { fill: var(--tv-color-primary-soft); opacity: .7; }
    .dot { fill: var(--tv-color-primary); }
  `],
})
export class PropertyMortgagesComponent implements OnInit {
  private readonly api = inject(TaxWorkspaceApiService);
  private readonly cdr = inject(ChangeDetectorRef);
  readonly auth = inject(AuthService);

  readonly property = input.required<PropertyDto>();
  readonly mortgagesChange = output<MortgageDto[]>();

  readonly banks = [
    'Commonwealth Bank',
    'Westpac',
    'ANZ',
    'NAB',
    'St.George Bank',
    'Macquarie Bank',
    'ING',
    'Bendigo Bank',
    'Bank of Queensland',
    'Suncorp Bank',
    'Bankwest',
    'AMP Bank',
  ];
  readonly chart = CHART;

  mortgages: MortgageDto[] = [];
  showForm = false;
  editingId: string | null = null;
  isSaving = false;
  errorMessage = '';
  form = this.emptyForm();
  interestMonth = '';
  interestAmount: number | null = null;

  chartPoints: ChartPoint[] = [];
  yTicks: { value: number; y: number }[] = [];
  linePath = '';
  areaPath = '';

  get isFormValid(): boolean {
    const bank = this.form.bank === 'Other' ? this.form.bankOther.trim() : this.form.bank;

    return !!bank && this.form.loan_amount !== null && this.form.loan_amount >= 0;
  }

  ngOnInit(): void {
    this.mortgages = this.property().mortgages ?? [];
    this.rebuildChart();
  }

  startAdd(): void {
    this.closeForm();
    this.showForm = true;
  }

  startEdit(mortgage: MortgageDto): void {
    const known = this.banks.includes(mortgage.bank_name);

    this.errorMessage = '';
    this.editingId = mortgage.id;
    this.form = {
      bank: known ? mortgage.bank_name : 'Other',
      bankOther: known ? '' : mortgage.bank_name,
      loan_type: mortgage.loan_type,
      loan_amount: Number(mortgage.loan_amount),
      interest_rate: mortgage.interest_rate === null ? null : Number(mortgage.interest_rate),
      start_date: mortgage.start_date ? mortgage.start_date.slice(0, 10) : '',
    };
    this.showForm = true;
  }

  closeForm(): void {
    this.showForm = false;
    this.editingId = null;
    this.form = this.emptyForm();
  }

  save(): void {
    if (this.isSaving || !this.isFormValid) {
      return;
    }

    this.isSaving = true;
    this.errorMessage = '';

    const payload = {
      bank_name: this.form.bank === 'Other' ? this.form.bankOther.trim() : this.form.bank,
      loan_type: this.form.loan_type,
      loan_amount: Number(this.form.loan_amount),
      interest_rate: this.form.interest_rate === null || this.form.interest_rate === ('' as unknown)
        ? null
        : Number(this.form.interest_rate),
      start_date: this.form.start_date || null,
    };
    const editing = this.editingId;
    const request$ = editing
      ? this.api.updateMortgage(editing, payload)
      : this.api.createMortgage(this.property().id, payload);

    request$.subscribe({
      next: (mortgage) => {
        this.apply(editing
          ? this.mortgages.map((item) => (item.id === mortgage.id ? mortgage : item))
          : [...this.mortgages, mortgage]);
        this.closeForm();
        this.isSaving = false;
        this.cdr.markForCheck();
      },
      error: () => this.fail('Unable to save this mortgage. Check the details and try again.'),
    });
  }

  remove(mortgage: MortgageDto): void {
    if (!confirm(`Delete the ${mortgage.bank_name} mortgage and its interest history?`)) {
      return;
    }

    this.errorMessage = '';
    this.api.deleteMortgage(mortgage.id).subscribe({
      next: () => {
        this.apply(this.mortgages.filter((item) => item.id !== mortgage.id));
        this.cdr.markForCheck();
      },
      error: () => this.fail('Unable to delete this mortgage.'),
    });
  }

  saveInterest(mortgage: MortgageDto): void {
    if (!this.interestMonth || this.interestAmount === null) {
      return;
    }

    this.errorMessage = '';
    this.api.saveMortgageInterest(mortgage.id, this.interestMonth, Number(this.interestAmount)).subscribe({
      next: (updated) => {
        this.apply(this.mortgages.map((item) => (item.id === updated.id ? updated : item)));
        this.interestAmount = null;
        this.cdr.markForCheck();
      },
      error: () => this.fail('Unable to save this interest amount.'),
    });
  }

  removeInterest(mortgage: MortgageDto, entryId: string): void {
    this.api.deleteMortgageInterest(mortgage.id, entryId).subscribe({
      next: (updated) => {
        this.apply(this.mortgages.map((item) => (item.id === updated.id ? updated : item)));
        this.cdr.markForCheck();
      },
      error: () => this.fail('Unable to delete this interest entry.'),
    });
  }

  loanTypeLabel(type: string): string {
    return { variable: 'Variable', fixed: 'Fixed', interest_only: 'Interest only' }[type] ?? type;
  }

  monthLabel(value: string): string {
    const date = new Date(value);

    return Number.isNaN(date.getTime())
      ? value
      : new Intl.DateTimeFormat('en-AU', { month: 'short', year: '2-digit', timeZone: 'UTC' }).format(date);
  }

  formatCurrency(value: string | number): string {
    return new Intl.NumberFormat('en-AU', { style: 'currency', currency: 'AUD' }).format(Number(value));
  }

  formatCompact(value: number): string {
    return new Intl.NumberFormat('en-AU', { notation: 'compact', maximumFractionDigits: 1 }).format(value);
  }

  private apply(mortgages: MortgageDto[]): void {
    this.mortgages = mortgages;
    this.rebuildChart();
    this.mortgagesChange.emit(mortgages);
  }

  private fail(message: string): void {
    this.errorMessage = message;
    this.isSaving = false;
    this.cdr.markForCheck();
  }

  private rebuildChart(): void {
    const totals = new Map<string, number>();

    for (const mortgage of this.mortgages) {
      for (const entry of mortgage.interest_entries) {
        const key = entry.month.slice(0, 7);
        totals.set(key, (totals.get(key) ?? 0) + Number(entry.interest_amount));
      }
    }

    const months = [...totals.keys()].sort();

    if (months.length === 0) {
      this.chartPoints = [];
      this.yTicks = [];
      this.linePath = '';
      this.areaPath = '';

      return;
    }

    const max = Math.max(...totals.values(), 1);
    const plotWidth = CHART.width - CHART.left - CHART.right;
    const plotHeight = CHART.height - CHART.top - CHART.bottom;
    const baseline = CHART.top + plotHeight;

    this.chartPoints = months.map((month, index) => {
      const value = totals.get(month) ?? 0;

      return {
        x: months.length === 1
          ? CHART.left + plotWidth / 2
          : CHART.left + (plotWidth * index) / (months.length - 1),
        y: baseline - (value / max) * plotHeight,
        label: this.monthLabel(`${month}-01`),
        value,
      };
    });
    this.yTicks = [0, 0.5, 1].map((ratio) => ({
      value: max * ratio,
      y: baseline - ratio * plotHeight,
    }));
    this.linePath = this.chartPoints
      .map((point, index) => `${index === 0 ? 'M' : 'L'}${point.x.toFixed(1)},${point.y.toFixed(1)}`)
      .join(' ');

    const first = this.chartPoints[0];
    const last = this.chartPoints[this.chartPoints.length - 1];
    this.areaPath = `${this.linePath} L${last.x.toFixed(1)},${baseline} L${first.x.toFixed(1)},${baseline} Z`;
  }

  private emptyForm() {
    return {
      bank: 'Commonwealth Bank',
      bankOther: '',
      loan_type: 'variable',
      loan_amount: null as number | null,
      interest_rate: null as number | null,
      start_date: '',
    };
  }
}
