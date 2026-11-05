import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  OnChanges,
  inject,
  input,
} from '@angular/core';
import { RouterLink } from '@angular/router';
import { forkJoin, of } from 'rxjs';

import {
  ReturnSummaryDto,
  ReturnSummaryLine,
  TaxWorkspaceApiService,
} from '../../shared/services/tax-workspace-api.service';
import { formatFinancialYear } from '../../shared/utils/financial-year';
import {
  YearComparisonItem,
  compareReturnSummaries,
  findPreviousYearId,
} from '../../shared/utils/year-comparison';

interface LineGroup {
  title: string;
  lines: ReturnSummaryLine[];
  total: number;
}

@Component({
  selector: 'app-return-summary',
  imports: [RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <section class="panel" aria-label="Tax return item summary">
      <div class="head">
        <div>
          <div class="eyebrow">By tax return item</div>
          <h2>FY {{ yearLabel }} evidence totals</h2>
        </div>
        <button type="button" class="ghost no-print" (click)="print()">Print</button>
      </div>

      @if (errorMessage) {
        <p class="note error" role="alert">{{ errorMessage }}</p>
      } @else if (!summary) {
        <p class="note" role="status">Loading totals...</p>
      } @else {
        @if (uncategorisedCount > 0) {
          <p class="note warn" role="status">
            {{ uncategorisedCount }} transaction{{ uncategorisedCount === 1 ? '' : 's' }}
            ({{ money(uncategorisedAmount) }}) have no tax return item and are not in these totals.
            <a routerLink="/transactions">Categorise them</a>
          </p>
        }

        @if (groups.length === 0) {
          <p class="note">No categorised records yet. Tag transactions with a tax return item to build this summary.</p>
        }

        @for (group of groups; track group.title) {
          <h3>{{ group.title }}</h3>
          <table>
            <tbody>
              @for (line of group.lines; track line.category + (line.property_id ?? '')) {
                <tr>
                  <td>
                    {{ line.label }}
                    @if (line.property_name) { <small>{{ line.property_name }}</small> }
                    @if (line.source === 'wfh_log') { <small>{{ summary.work_from_home.hours }} h × {{ money(summary.work_from_home.rate) }}</small> }
                  </td>
                  <td class="ref">{{ line.ato_ref }}</td>
                  <td class="amount">{{ money(line.amount) }}</td>
                </tr>
              }
              <tr class="total"><td colspan="2">Total</td><td class="amount">{{ money(group.total) }}</td></tr>
            </tbody>
          </table>
        }

        @if (summary.rental.length > 0) {
          <h3>Rental result by property</h3>
          <table>
            <thead><tr><th>Property</th><th class="amount">Income</th><th class="amount">Expenses</th><th class="amount">Net</th></tr></thead>
            <tbody>
              @for (row of summary.rental; track row.property_id) {
                <tr>
                  <td>{{ row.property_name }}
                    @if (row.interest_from_mortgage_records > 0) { <small>includes {{ money(row.interest_from_mortgage_records) }} loan interest from mortgage entries</small> }
                  </td>
                  <td class="amount">{{ money(row.income) }}</td>
                  <td class="amount">{{ money(row.expenses) }}</td>
                  <td class="amount" [class.loss]="row.net_result < 0">{{ money(row.net_result) }}</td>
                </tr>
              }
            </tbody>
          </table>
        }

        @if (comparison) {
          <h3>Compared with FY {{ previousLabel }}</h3>
          @if (comparison.length === 0) {
            <p class="note">No missing items or large changes compared with last year.</p>
          } @else {
            <table>
              <tbody>
                @for (item of comparison; track item.kind + item.label + item.propertyName) {
                  <tr>
                    <td>
                      <span class="badge" [class]="item.kind">{{ kindLabel(item.kind) }}</span>
                      {{ item.label }}
                      @if (item.propertyName) { <small>{{ item.propertyName }}</small> }
                    </td>
                    <td class="amount">{{ money(item.previous) }} → {{ money(item.current) }}</td>
                  </tr>
                }
              </tbody>
            </table>
            <p class="note">"Missing" means recorded last year but not this year. It may be correct, or a record may not have been added yet.</p>
          }
        }

        <p class="note">Totals come from the records you categorised. They help your accountant and may differ from the lodged return. This is not tax advice.</p>
      }
    </section>
  `,
  styles: [`
    .panel { display: grid; gap: 12px; padding: 20px; border: 1px solid var(--tv-color-border); border-radius: 12px; background: white; }
    .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
    .eyebrow { color: var(--tv-color-primary); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    h2 { margin: 2px 0 0; font-size: 18px; }
    h3 { margin: 8px 0 0; font-size: 14px; }
    .ghost { padding: 6px 12px; border: 1px solid var(--tv-color-border); border-radius: 6px; background: white; cursor: pointer; font-size: 13px; }
    .note { margin: 0; color: var(--tv-color-text-secondary); font-size: 12px; }
    .note.error { color: var(--tv-color-danger); }
    .note.warn { padding: 8px 10px; border-radius: 6px; background: #fff4e0; color: var(--tv-color-text); }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    td, th { padding: 7px 10px; border-bottom: 1px solid var(--tv-color-border); text-align: left; }
    th { font-size: 12px; color: var(--tv-color-text-secondary); }
    small { display: block; color: var(--tv-color-text-secondary); font-size: 11px; }
    .amount { text-align: right; font-variant-numeric: tabular-nums; }
    .ref { color: var(--tv-color-text-secondary); font-size: 12px; width: 70px; }
    .total td { font-weight: 700; border-bottom: 0; }
    .loss { color: var(--tv-color-danger); }
    .badge { display: inline-block; margin-right: 6px; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; }
    .badge.missing { background: #fde8e8; color: #9b1c1c; }
    .badge.changed { background: #fff4e0; color: #8a5a00; }
    .badge.new { background: #e6f4ea; color: #1e6b34; }
    @media print { .no-print { display: none; } }
  `],
})
export class ReturnSummaryComponent implements OnChanges {
  private readonly api = inject(TaxWorkspaceApiService);
  private readonly cdr = inject(ChangeDetectorRef);

  readonly financialYearId = input.required<string>();
  readonly years = input.required<{ id: string; year_code: string; start_date: string }[]>();

  summary: ReturnSummaryDto | null = null;
  comparison: YearComparisonItem[] | null = null;
  previousLabel = '';
  errorMessage = '';
  groups: LineGroup[] = [];
  uncategorisedCount = 0;
  uncategorisedAmount = 0;
  yearLabel = '';

  private requestToken = 0;

  ngOnChanges(): void {
    const yearId = this.financialYearId();
    const token = ++this.requestToken;
    const previousId = findPreviousYearId(this.years(), yearId);

    this.summary = null;
    this.comparison = null;
    this.errorMessage = '';
    this.yearLabel = formatFinancialYear(this.years().find((year) => year.id === yearId)?.year_code ?? '');
    this.previousLabel = previousId
      ? formatFinancialYear(this.years().find((year) => year.id === previousId)?.year_code ?? '')
      : '';

    forkJoin([
      this.api.getReturnSummary(yearId),
      previousId ? this.api.getReturnSummary(previousId) : of(null),
    ]).subscribe({
      next: ([current, previous]) => {
        if (token !== this.requestToken) {
          return;
        }

        this.summary = current;
        this.groups = this.buildGroups(current);
        this.uncategorisedCount = current.uncategorised.income.records + current.uncategorised.expense.records;
        this.uncategorisedAmount = current.uncategorised.income.amount + current.uncategorised.expense.amount;
        this.comparison = previous ? compareReturnSummaries(current, previous) : null;
        this.cdr.markForCheck();
      },
      error: () => {
        if (token === this.requestToken) {
          this.errorMessage = 'Unable to load the tax return item summary.';
          this.cdr.markForCheck();
        }
      },
    });
  }

  money(value: number): string {
    return new Intl.NumberFormat('en-AU', { style: 'currency', currency: 'AUD' }).format(value);
  }

  kindLabel(kind: YearComparisonItem['kind']): string {
    return { missing: 'Missing this year', changed: 'Big change', new: 'New this year' }[kind];
  }

  print(): void {
    window.print();
  }

  private buildGroups(summary: ReturnSummaryDto): LineGroup[] {
    return ['Income', 'Rental property', 'Deductions']
      .map((title) => {
        const lines = summary.lines.filter((line) => line.group === title);
        const total = lines.reduce(
          (sum, line) => sum + (title === 'Rental property' && line.direction === 'expense' ? -line.amount : line.amount),
          0,
        );

        return { title: title === 'Rental property' ? 'Rental property (net of expenses)' : title, lines, total };
      })
      .filter((group) => group.lines.length > 0);
  }
}
