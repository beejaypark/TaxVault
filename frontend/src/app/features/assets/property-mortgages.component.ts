import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  ElementRef,
  effect,
  OnInit,
  inject,
  input,
  output,
  ViewChild,
} from '@angular/core';
import { FormsModule } from '@angular/forms';

import { TaxIconComponent } from '../../shared/components/tax-icon/tax-icon.component';
import { DocumentApiService } from '../../shared/services/documents/document-api.service';
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
const SERIES_COLORS = ['#1f5fbf', '#d9822b', '#2e9d6a', '#8a4fbf'];

interface RateSeries {
  id: string;
  name: string;
  color: string;
  path: string;
  points: ChartPoint[];
}

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
        <form #mortgageEditor class="mortgage-form" (ngSubmit)="save()">
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
          <label>Repayment frequency
            <select name="repaymentFrequency" [(ngModel)]="form.repayment_frequency">
              <option value="weekly">Weekly</option>
              <option value="fortnightly">Fortnightly</option>
              <option value="monthly">Monthly</option>
            </select>
          </label>
          <label>Loan amount (AUD) <input name="loanAmount" type="number" min="0" step="0.01" [(ngModel)]="form.loan_amount" required /></label>
          <label>Repayment amount (AUD) <input name="repaymentAmount" type="number" min="0" step="0.01" [(ngModel)]="form.repayment_amount" /></label>
          <label>Interest rate (% p.a.) <input name="rate" type="number" min="0" max="100" step="0.001" [(ngModel)]="form.interest_rate" /></label>
          <label>Start date <input name="start" type="date" [(ngModel)]="form.start_date" /></label>
          <label>Maturity date <input name="maturity" type="date" [min]="form.start_date || undefined" [(ngModel)]="form.maturity_date" /></label>
          <label>Verified balance (AUD) <input name="verifiedBalance" type="number" min="0" step="0.01" [(ngModel)]="form.verified_balance" /></label>
          <label>Verified on <input name="verifiedBalanceDate" type="date" [(ngModel)]="form.verified_balance_date" /></label>
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
        <article class="mortgage-item" [class.mortgage-closed]="isClosed(mortgage)">
          <div class="mortgage-summary">
            <button type="button" class="mortgage-toggle" (click)="toggleMortgage(mortgage.id)" [attr.aria-expanded]="isExpanded(mortgage.id)">
              <span>
              <strong>{{ mortgage.bank_name }} @if (isClosed(mortgage)) { <span class="mortgage-status closed">Closed</span> } @else { <span class="mortgage-status active">Active</span> }</strong>
              <span>{{ mortgagePeriod(mortgage) }}</span>
              @if (mortgage.verified_balance !== null && mortgage.verified_balance !== undefined) { <small class="balance-verification" [class.needs-review]="mortgage.balance_needs_review">{{ mortgage.balance_needs_review ? 'Balance needs review' : 'Balance verified' }} · {{ dateLabel(mortgage.verified_balance_date ?? '') }}</small> }
              </span>
              <span class="mortgage-chevron" aria-hidden="true">{{ isExpanded(mortgage.id) ? '−' : '+' }}</span>
            </button>
            <div class="mortgage-amount">
              @if (mortgage.verified_balance !== null && mortgage.verified_balance !== undefined) {
                <small>Verified balance</small>
                <strong>{{ formatCurrency(mortgage.verified_balance) }}</strong>
                <small class="balance-estimate">Estimated today · {{ formatCurrency(mortgage.estimated_balance ?? mortgage.loan_amount) }}</small>
              } @else {
                <small>Estimated balance</small>
                <strong>{{ formatCurrency(mortgage.estimated_balance ?? mortgage.loan_amount) }}</strong>
              }
              @if (mortgage.interest_rate !== null) { <small>{{ frequencyLabel(mortgage.repayment_frequency) }} repayment · {{ formatCurrency(currentRepaymentAmount(mortgage) ?? 0) }}</small> }
            </div>
            @if (auth.canEdit) {
              <div class="row-actions">
                <button type="button" class="row-action" (click)="startEdit(mortgage)"><tv-icon name="edit" /> Edit</button>
                <button type="button" class="row-action danger" (click)="remove(mortgage)"><tv-icon name="delete" /> Delete</button>
              </div>
            }
          </div>

          @if (isExpanded(mortgage.id)) {
          @if (auth.canEdit && rateFormMortgageId === mortgage.id) {
            <form class="interest-form" (ngSubmit)="saveRate(mortgage)">
              <label>Rate effective from <input type="date" name="rate-date-{{ mortgage.id }}" [(ngModel)]="rateDate" required /></label>
              <label>New rate (% p.a.) <input type="number" min="0" max="100" step="0.001" name="rate-value-{{ mortgage.id }}" [(ngModel)]="rateValue" required /></label>
              <label>Bank repayment amount (AUD) <input type="number" min="0" step="0.01" name="bank-repayment-{{ mortgage.id }}" [(ngModel)]="bankRepaymentAmount" required /></label>
              <button type="submit" class="row-action" [disabled]="!rateDate || rateValue === null || bankRepaymentAmount === null">Save rate change</button>
            </form>
          } @else if (auth.canEdit) {
            <button type="button" class="row-action add-entry" (click)="toggleRateForm(mortgage.id)"><tv-icon name="plus" /> Add rate change</button>
          }

          @if (mortgage.rate_changes.length > 0) {
            <ul class="interest-list" aria-label="Interest rate history">
              @for (change of mortgage.rate_changes; track change.id) {
                <li [attr.title]="repaymentTooltip(mortgage, change.rate, change.effective_date, change.bank_repayment_amount)">
                  <span>{{ dateLabel(change.effective_date) }}</span>
                  <strong>{{ change.rate }}%</strong>
                  @if (auth.canEdit) {
                    <button type="button" class="row-action danger" (click)="removeRate(mortgage, change.id)" aria-label="Delete rate change"><tv-icon name="delete" /></button>
                  }
                </li>
              }
            </ul>
          }

          }
        </article>
      }

      @if (rateSeries.length > 0) {
        <figure class="interest-chart">
          <figcaption>Interest rate history (% p.a.)</figcaption>
          <svg [attr.viewBox]="'0 0 ' + chart.width + ' ' + chart.height" role="img" aria-label="Mortgage interest rate history">
            @for (tick of rateTicks; track tick.value) {
              <line [attr.x1]="chart.left" [attr.x2]="chart.width - chart.right" [attr.y1]="tick.y" [attr.y2]="tick.y" class="grid" />
              <text [attr.x]="chart.left - 8" [attr.y]="tick.y + 4" text-anchor="end">{{ tick.value.toFixed(2) }}%</text>
            }
            @if (financialYearBand.width > 0) {
              <rect [attr.x]="financialYearBand.x" [attr.y]="chart.top" [attr.width]="financialYearBand.width" [attr.height]="chart.height - chart.top - chart.bottom" class="financial-year-band" />
            }
            @for (axis of rateAxis; track axis.label) {
              <text [attr.x]="axis.x" [attr.y]="chart.height - 12" [attr.text-anchor]="axis.anchor">{{ axis.label }}</text>
            }
            @for (series of rateSeries; track series.id) {
              <path [attr.d]="series.path" class="line" [style.stroke]="series.color" />
              @for (point of series.points; track point.label) {
                <circle [attr.cx]="point.x" [attr.cy]="point.y" r="4" [attr.fill]="series.color"><title>{{ series.name }} · {{ point.label }}: {{ point.value }}%</title></circle>
              }
            }
          </svg>
          @if (rateSeries.length > 1) {
            <p class="legend">
              @for (series of rateSeries; track series.id) {
                <span><i [style.background]="series.color"></i>{{ series.name }}</span>
              }
            </p>
          }
        </figure>
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
    :host { display: block; flex: 0 0 100%; min-width: 0; width: 100%; box-sizing: border-box; }
    .mortgages { display: grid; gap: 12px; width: 100%; min-width: 0; box-sizing: border-box; padding-top: 16px; border-top: 1px solid var(--tv-color-border); }
    .mortgage-head, .mortgage-summary { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    h3 { margin: 0; font-size: 14px; }
    .mortgage-empty { margin: 0; color: var(--tv-color-text-secondary); font-size: 12px; }
    .mortgage-error { margin: 0; padding: 8px 10px; border-radius: 6px; background: var(--tv-color-danger-soft); color: var(--tv-color-danger); font-size: 12px; }
    .mortgage-form, .interest-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; align-items: end; }
    .add-entry { justify-self: start; }
    .mortgage-actions { grid-column: 1 / -1; display: flex; justify-content: flex-end; gap: 8px; }
    label { display: grid; gap: 6px; color: var(--tv-color-text-secondary); font-size: 12px; font-weight: 600; }
    input, select { width: 100%; min-height: 38px; padding: 7px 10px; border: 1px solid var(--tv-color-border); border-radius: 6px; background: white; font-size: 13px; }
    .mortgage-item { display: grid; gap: 10px; padding: 14px; border: 1px solid var(--tv-color-border); border-radius: 8px; background: var(--tv-color-background); }
    .mortgage-item.mortgage-closed { border-color: #d8cfc4; background: #f7f4ef; opacity: .82; }
    .mortgage-status { display: inline-block; margin-left: 6px; padding: 2px 6px; border-radius: 999px; font-size: 10px; font-weight: 700; vertical-align: 2px; }
    .mortgage-status.active { background: var(--tv-color-success-soft); color: var(--tv-color-success); }
    .mortgage-status.closed { background: #e8e1d8; color: #746657; }
    .mortgage-summary div:first-child { display: grid; gap: 3px; }
    .mortgage-toggle { display: flex; flex: 1 1 auto; align-items: center; justify-content: space-between; gap: 12px; padding: 0; border: 0; background: transparent; color: inherit; text-align: left; cursor: pointer; }
    .mortgage-toggle > span:first-child { display: grid; gap: 3px; }
    .mortgage-toggle > span:first-child > span { color: var(--tv-color-text-secondary); font-size: 12px; }
    .balance-verification { color: var(--tv-color-success); font-size: 10px; font-weight: 650; }
    .balance-verification.needs-review { color: var(--tv-color-danger); }
    .mortgage-chevron { display: inline-grid; width: 24px; height: 24px; place-items: center; border: 1px solid var(--tv-color-border); border-radius: 50%; color: var(--tv-color-primary); font-size: 18px; line-height: 1; }
    .mortgage-toggle:focus-visible { outline: 2px solid var(--tv-color-primary); outline-offset: 3px; }
    .mortgage-summary span { color: var(--tv-color-text-secondary); font-size: 12px; }
    .mortgage-amount { font-weight: 700; font-size: 14px; }
    .mortgage-amount small { display: block; margin-bottom: 3px; color: var(--tv-color-text-muted); font-size: 10px; font-weight: 600; }
    .mortgage-amount small.balance-estimate { color: var(--tv-color-text); font-weight: 500; }
    .mortgage-amount small:last-child { margin-top: 4px; margin-bottom: 0; }
    .interest-list { display: flex; flex-wrap: wrap; gap: 8px; margin: 0; padding: 0; list-style: none; }
    .interest-list li { display: inline-flex; align-items: center; gap: 8px; padding: 4px 8px; border: 1px solid var(--tv-color-border); border-radius: 999px; background: white; font-size: 12px; }
    .interest-list .row-action { padding: 2px 6px; }
    .interest-chart { margin: 0; padding: 12px; border: 1px solid var(--tv-color-border); border-radius: 8px; background: white; }
    figcaption { margin-bottom: 6px; font-size: 12px; font-weight: 650; color: var(--tv-color-text-secondary); }
    svg { width: 100%; height: auto; }
    svg text { fill: var(--tv-color-text-secondary); font-size: 11px; }
    .grid { stroke: var(--tv-color-border); stroke-width: 1; }
    .financial-year-band { fill: var(--tv-color-primary-soft); opacity: .55; }
    .line { fill: none; stroke: var(--tv-color-primary); stroke-width: 2.5; }
    .area { fill: var(--tv-color-primary-soft); opacity: .7; }
    .dot { fill: var(--tv-color-primary); }
    .legend { display: flex; flex-wrap: wrap; gap: 14px; margin: 6px 0 0; font-size: 12px; color: var(--tv-color-text-secondary); }
    .legend span { display: inline-flex; align-items: center; gap: 6px; }
    .legend i { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
  `],
})
export class PropertyMortgagesComponent implements OnInit {
  private readonly api = inject(TaxWorkspaceApiService);
  private readonly cdr = inject(ChangeDetectorRef);
  private readonly documents = inject(DocumentApiService);
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
  expandedMortgageIds = new Set<string>();
  rateFormMortgageId: string | null = null;
  @ViewChild('mortgageEditor') mortgageEditor?: ElementRef<HTMLElement>;
  showForm = false;
  editingId: string | null = null;
  isSaving = false;
  errorMessage = '';
  form = this.emptyForm();
  interestMonth = '';
  interestAmount: number | null = null;
  rateDate = '';
  rateValue: number | null = null;
  bankRepaymentAmount: number | null = null;

  rateSeries: RateSeries[] = [];
  rateTicks: { value: number; y: number }[] = [];
  rateAxis: { label: string; x: number; anchor: 'start' | 'middle' | 'end' }[] = [];
  financialYearBand = { x: 0, width: 0 };

  chartPoints: ChartPoint[] = [];
  yTicks: { value: number; y: number }[] = [];
  linePath = '';
  areaPath = '';

  constructor() {
    effect(() => {
      this.documents.selectedFinancialYearId();
      this.documents.financialYears();
      if (this.mortgages.length > 0) {
        this.rebuildChart();
        this.cdr.markForCheck();
      }
    });
  }

  get isFormValid(): boolean {
    const bank = this.form.bank === 'Other' ? this.form.bankOther.trim() : this.form.bank;

    return !!bank && this.form.loan_amount !== null && this.form.loan_amount >= 0;
  }

  ngOnInit(): void {
    this.mortgages = this.property().mortgages ?? [];
    this.expandedMortgageIds = new Set<string>();
    this.rebuildChart();
  }

  startAdd(): void {
    this.closeForm();
    this.showForm = true;
  }

  isClosed(mortgage: MortgageDto): boolean {
    return !!mortgage.maturity_date && mortgage.maturity_date.slice(0, 10) < new Date().toISOString().slice(0, 10);
  }

  mortgagePeriod(mortgage: MortgageDto): string {
    const start = mortgage.start_date ? this.dateLabel(mortgage.start_date) : 'Start date not set';
    const end = mortgage.maturity_date ? this.dateLabel(mortgage.maturity_date) : 'No maturity date';

    return `${start} – ${end}`;
  }

  estimatedRepaymentAmount(mortgage: MortgageDto, rate: number | string, effectiveDate?: string): number | null {
    const effective = effectiveDate ? new Date(effectiveDate) : new Date();
    const verifiedDate = mortgage.verified_balance_date ? new Date(mortgage.verified_balance_date) : null;
    const useVerifiedBalance = mortgage.verified_balance !== null
      && mortgage.verified_balance !== undefined
      && (!verifiedDate || effective.getTime() >= verifiedDate.getTime());
    const principal = Number(useVerifiedBalance ? mortgage.verified_balance : (mortgage.estimated_balance ?? mortgage.loan_amount));
    const start = useVerifiedBalance && verifiedDate ? verifiedDate : (mortgage.start_date ? new Date(mortgage.start_date) : null);
    const maturity = mortgage.maturity_date ? new Date(mortgage.maturity_date) : null;
    if (!Number.isFinite(principal) || !start || !maturity || Number.isNaN(start.getTime()) || Number.isNaN(maturity.getTime())) {
      return null;
    }

    const months = Math.max(1, (maturity.getUTCFullYear() - start.getUTCFullYear()) * 12 + maturity.getUTCMonth() - start.getUTCMonth());
    const annualRate = Number(rate);
    const periodsPerYear = mortgage.repayment_frequency === 'weekly' ? 52 : mortgage.repayment_frequency === 'fortnightly' ? 26 : 12;
    const periodicRate = annualRate / 100 / periodsPerYear;
    if (mortgage.loan_type === 'interest_only') return principal * periodicRate;
    if (periodicRate <= 0) return principal / Math.max(1, Math.round((months / 12) * periodsPerYear));

    const periods = Math.max(1, Math.round((months / 12) * periodsPerYear));
    const factor = (1 + periodicRate) ** periods;

    return principal * periodicRate * factor / (factor - 1);
  }

  currentRepaymentAmount(mortgage: MortgageDto): number | null {
    const today = new Date().toISOString().slice(0, 10);
    const current = [...(mortgage.rate_changes ?? [])]
      .filter((change) => change.effective_date.slice(0, 10) <= today)
      .sort((left, right) => right.effective_date.localeCompare(left.effective_date))[0];

    return current?.bank_repayment_amount !== null && current?.bank_repayment_amount !== undefined
      ? Number(current.bank_repayment_amount)
      : this.estimatedRepaymentAmount(mortgage, mortgage.interest_rate ?? 0);
  }

  repaymentTooltip(mortgage: MortgageDto, rate: number | string, effectiveDate?: string, bankRepaymentAmount?: number | string | null): string | null {
    const repayment = bankRepaymentAmount !== null && bankRepaymentAmount !== undefined
      ? Number(bankRepaymentAmount)
      : this.estimatedRepaymentAmount(mortgage, rate, effectiveDate);

    return repayment === null ? null : `${bankRepaymentAmount !== null && bankRepaymentAmount !== undefined ? 'Bank' : 'Estimated'} ${this.frequencyLabel(mortgage.repayment_frequency)} repayment: ${this.formatCurrency(repayment)}`;
  }

  frequencyLabel(frequency: MortgageDto['repayment_frequency']): string {
    return frequency === 'weekly' ? 'weekly' : frequency === 'fortnightly' ? 'fortnightly' : 'monthly';
  }

  isExpanded(id: string): boolean {
    return this.expandedMortgageIds.has(id);
  }

  toggleMortgage(id: string): void {
    const expanded = new Set(this.expandedMortgageIds);
    if (expanded.has(id)) {
      expanded.delete(id);
    } else {
      expanded.add(id);
    }
    this.expandedMortgageIds = expanded;
  }

  toggleRateForm(id: string): void {
    this.rateFormMortgageId = this.rateFormMortgageId === id ? null : id;
  }

  startEdit(mortgage: MortgageDto): void {
    const known = this.banks.includes(mortgage.bank_name);

    this.errorMessage = '';
    this.editingId = mortgage.id;
    this.form = {
      bank: known ? mortgage.bank_name : 'Other',
      bankOther: known ? '' : mortgage.bank_name,
      loan_type: mortgage.loan_type,
      repayment_frequency: mortgage.repayment_frequency ?? 'monthly',
      loan_amount: Number(mortgage.loan_amount),
      repayment_amount: mortgage.repayment_amount === null || mortgage.repayment_amount === undefined ? null : Number(mortgage.repayment_amount),
      interest_rate: mortgage.interest_rate === null ? null : Number(mortgage.interest_rate),
      start_date: mortgage.start_date ? mortgage.start_date.slice(0, 10) : '',
      maturity_date: mortgage.maturity_date ? mortgage.maturity_date.slice(0, 10) : '',
      verified_balance: mortgage.verified_balance === null || mortgage.verified_balance === undefined ? null : Number(mortgage.verified_balance),
      verified_balance_date: mortgage.verified_balance_date ? mortgage.verified_balance_date.slice(0, 10) : '',
    };
    this.expandedMortgageIds = new Set(this.expandedMortgageIds).add(mortgage.id);
    this.showForm = true;
    setTimeout(() => this.mortgageEditor?.nativeElement.scrollIntoView({ behavior: 'smooth', block: 'start' }));
  }

  closeForm(): void {
    this.showForm = false;
    this.editingId = null;
    this.rateFormMortgageId = null;
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
      repayment_frequency: this.form.repayment_frequency,
      loan_amount: Number(this.form.loan_amount),
      repayment_amount: this.form.repayment_amount === null || this.form.repayment_amount === ('' as unknown)
        ? null
        : Number(this.form.repayment_amount),
      interest_rate: this.form.interest_rate === null || this.form.interest_rate === ('' as unknown)
        ? null
        : Number(this.form.interest_rate),
      start_date: this.form.start_date || null,
      maturity_date: this.form.maturity_date || null,
      verified_balance: this.form.verified_balance === null || this.form.verified_balance === ('' as unknown)
        ? null
        : Number(this.form.verified_balance),
      verified_balance_date: this.form.verified_balance_date || null,
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

  saveRate(mortgage: MortgageDto): void {
    if (!this.rateDate || this.rateValue === null) {
      return;
    }

    this.errorMessage = '';
    if (this.bankRepaymentAmount === null) {
      return;
    }

    this.api.saveMortgageRate(mortgage.id, this.rateDate, Number(this.rateValue), Number(this.bankRepaymentAmount)).subscribe({
      next: (updated) => {
        this.apply(this.mortgages.map((item) => (item.id === updated.id ? updated : item)));
        this.rateValue = null;
        this.bankRepaymentAmount = null;
        this.cdr.markForCheck();
      },
      error: () => this.fail('Unable to save this rate change.'),
    });
  }

  removeRate(mortgage: MortgageDto, entryId: string): void {
    this.api.deleteMortgageRate(mortgage.id, entryId).subscribe({
      next: (updated) => {
        this.apply(this.mortgages.map((item) => (item.id === updated.id ? updated : item)));
        this.cdr.markForCheck();
      },
      error: () => this.fail('Unable to delete this rate change.'),
    });
  }

  dateLabel(value: string): string {
    const date = new Date(value);

    return Number.isNaN(date.getTime())
      ? value
      : new Intl.DateTimeFormat('en-AU', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' }).format(date);
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

  mortgageTerm(mortgage: MortgageDto): string {
    const start = mortgage.start_date ? new Date(mortgage.start_date) : null;
    const maturityDate = mortgage.maturity_date;
    const maturity = maturityDate ? new Date(maturityDate) : null;

    if (!start || !maturity || Number.isNaN(start.getTime()) || Number.isNaN(maturity.getTime())) {
      return `Matures ${this.dateLabel(maturityDate ?? '')}`;
    }

    const months = Math.max(0, (maturity.getUTCFullYear() - start.getUTCFullYear()) * 12 + maturity.getUTCMonth() - start.getUTCMonth());
    const years = Math.floor(months / 12);
    const remainder = months % 12;
    const term = years > 0 && remainder > 0 ? `${years}y ${remainder}m` : years > 0 ? `${years}y` : `${remainder}m`;
    return `${term} · matures ${this.dateLabel(maturityDate!)}`;
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

  private rebuildRateChart(): void {
    const day = (value: string) => new Date(value.slice(0, 10)).getTime();
    const selectedYear = this.documents.financialYears().find(
      (year) => year.id === this.documents.selectedFinancialYearId(),
    );
    const financialYearStart = selectedYear ? day(selectedYear.start_date) : null;
    const financialYearEnd = selectedYear ? day(selectedYear.end_date) : null;
    const today = Date.now();
    const nextFinancialYearEnd = financialYearEnd === null
      ? today
      : (() => {
        const date = new Date(financialYearEnd);
        date.setUTCFullYear(date.getUTCFullYear() + 1);
        return date.getTime();
      })();
    const chartEnd = nextFinancialYearEnd;
    const histories = this.mortgages
      .map((mortgage) => {
        const loanStart = mortgage.start_date ? day(mortgage.start_date) : Number.NEGATIVE_INFINITY;
        const loanEnd = mortgage.maturity_date
          ? Math.min(day(mortgage.maturity_date), today)
          : today;
        const sourceChanges = (mortgage.rate_changes ?? []).length > 0
          ? mortgage.rate_changes
          : mortgage.interest_rate !== null && mortgage.interest_rate !== undefined
            ? [{
              id: `${mortgage.id}-initial-rate`,
              effective_date: mortgage.start_date ?? selectedYear?.start_date ?? new Date().toISOString(),
              rate: mortgage.interest_rate,
            }]
            : [];
        const firstChange = Math.min(...sourceChanges.map((change) => day(change.effective_date)), chartEnd);
        const naturalStart = Number.isFinite(loanStart) ? loanStart : firstChange;
        const seriesStart = naturalStart;
        const seriesEnd = loanEnd;
        const changes = sourceChanges.filter((change) => {
          const effective = day(change.effective_date);
          return effective >= seriesStart && effective <= seriesEnd;
        });

        return { mortgage, changes, seriesStart, seriesEnd };
      })
      .filter(({ changes, seriesStart, seriesEnd }) => changes.length > 0 && seriesEnd >= seriesStart);
    const all = histories.flatMap(({ changes }) => changes);

    if (all.length === 0) {
      this.rateSeries = [];
      this.rateTicks = [];
      this.rateAxis = [];
      this.financialYearBand = { x: 0, width: 0 };

      return;
    }

    const times = all.map((change) => day(change.effective_date));
    const rangeStarts = histories.map(({ seriesStart }) => seriesStart);
    const rangeEnds = histories.map(({ seriesEnd }) => seriesEnd);
    const start = Math.min(...rangeStarts, ...times);
    const end = Math.max(chartEnd, ...rangeEnds, ...times);
    const span = Math.max(end - start, 1);
    const rates = all.map((change) => Number(change.rate));
    const low = Math.max(0, Math.min(...rates) - 0.5);
    const high = Math.max(...rates) + 0.5;
    const plotWidth = CHART.width - CHART.left - CHART.right;
    const plotHeight = CHART.height - CHART.top - CHART.bottom;
    const baseline = CHART.top + plotHeight;
    const xOf = (time: number) => CHART.left + (plotWidth * (time - start)) / span;
    const yOf = (rate: number) => baseline - ((rate - low) / (high - low)) * plotHeight;

    const bandStart = financialYearStart === null ? null : Math.max(financialYearStart, start);
    const bandEnd = financialYearEnd === null ? null : Math.min(financialYearEnd, end);
    this.financialYearBand = bandStart !== null && bandEnd !== null && bandEnd > bandStart
      ? { x: xOf(bandStart), width: xOf(bandEnd) - xOf(bandStart) }
      : { x: 0, width: 0 };

    this.rateSeries = histories.map(({ mortgage, changes, seriesEnd }, index) => {
      const points = changes.map((change) => ({
        x: xOf(day(change.effective_date)),
        y: yOf(Number(change.rate)),
        label: this.dateLabel(change.effective_date),
        value: Number(change.rate),
      }));
      const steps = points.map((point, i) => (i === 0
        ? `M${point.x.toFixed(1)},${point.y.toFixed(1)}`
        : `H${point.x.toFixed(1)} V${point.y.toFixed(1)}`));
      if (seriesEnd >= day(changes[changes.length - 1].effective_date)) {
        steps.push(`H${xOf(seriesEnd).toFixed(1)}`);
      }

      return {
        id: mortgage.id,
        name: mortgage.bank_name,
        color: SERIES_COLORS[index % SERIES_COLORS.length],
        path: steps.join(' '),
        points,
      };
    });
    this.rateTicks = [0, 0.5, 1].map((ratio) => ({
      value: low + (high - low) * ratio,
      y: baseline - ratio * plotHeight,
    }));
    this.rateAxis = [0, 1].map((ratio, index) => ({
      label: this.dateLabel(new Date(start + span * ratio).toISOString()),
      x: CHART.left + plotWidth * ratio,
      anchor: index === 0 ? 'start' : 'end',
    }));
  }

  private rebuildChart(): void {
    this.rebuildRateChart();
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
      repayment_frequency: 'monthly' as 'weekly' | 'fortnightly' | 'monthly',
      loan_amount: null as number | null,
      repayment_amount: null as number | null,
      interest_rate: null as number | null,
      start_date: '',
      maturity_date: '',
      verified_balance: null as number | null,
      verified_balance_date: '',
    };
  }
}
