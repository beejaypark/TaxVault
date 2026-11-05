import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  effect,
  inject,
  input,
} from '@angular/core';
import { RouterLink } from '@angular/router';

import { DocumentApiService } from '../../shared/services/documents/document-api.service';
import {
  PropertyDto,
  RentalSummaryDto,
  TaxWorkspaceApiService,
} from '../../shared/services/tax-workspace-api.service';
import { formatFinancialYear } from '../../shared/utils/financial-year';

@Component({
  selector: 'app-property-rental-summary',
  imports: [RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <section class="rental" aria-label="Rental summary">
      <div class="rental-head">
        <h3>Rental summary{{ yearLabel ? ' · FY ' + yearLabel : '' }}</h3>
        <a routerLink="/transactions" class="rental-link">Add rental items in Income &amp; expenses</a>
      </div>

      @if (!yearId) {
        <p class="rental-note">Select a financial year in the header to see the rental schedule for that year.</p>
      } @else if (errorMessage) {
        <p class="rental-note error" role="alert">{{ errorMessage }}</p>
      } @else if (!summary) {
        <p class="rental-note" role="status">Loading rental summary...</p>
      } @else if (summary.income.length === 0 && summary.expenses.length === 0) {
        <p class="rental-note">No rental income or expenses are recorded for this property in this year. Tag transactions with a rental tax item and this property to build the schedule.</p>
      } @else {
        <table>
          <tbody>
            <tr class="group"><th colspan="2">Rental income</th></tr>
            @for (line of summary.income; track line.category) {
              <tr><td>{{ line.label }} <small>{{ recordsLabel(line.records) }}</small></td><td class="amount">{{ money(line.amount) }}</td></tr>
            }
            <tr class="total"><td>Total rental income</td><td class="amount">{{ money(summary.total_income) }}</td></tr>
            <tr class="group"><th colspan="2">Rental expenses</th></tr>
            @for (line of summary.expenses; track line.category) {
              <tr>
                <td>{{ line.label }} <small>{{ line.source === 'mortgage_records' ? 'from mortgage interest entries' : recordsLabel(line.records) }}</small></td>
                <td class="amount">{{ money(line.amount) }}</td>
              </tr>
            }
            <tr class="total"><td>Total rental expenses</td><td class="amount">{{ money(summary.total_expenses) }}</td></tr>
            <tr class="result" [class.loss]="summary.net_rental_result < 0">
              <td>{{ summary.net_rental_result < 0 ? 'Net rental loss' : 'Net rental income' }}</td>
              <td class="amount">{{ money(Math.abs(summary.net_rental_result)) }}</td>
            </tr>
          </tbody>
        </table>

        @if (summary.interest_mismatch) {
          <p class="rental-note warn" role="status">
            Loan interest differs: transactions total {{ money(summary.interest_from_transactions) }}, mortgage interest entries total {{ money(summary.interest_from_mortgage_records) }}. The schedule uses the transactions. Check both against your lender statements.
          </p>
        }
        <p class="rental-note">Organised from your records to help your accountant. It is not tax advice and may differ from the lodged return.</p>
      }
    </section>
  `,
  styles: [`
    .rental { display: grid; gap: 10px; width: 100%; padding-top: 16px; border-top: 1px solid var(--tv-color-border); }
    .rental-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    h3 { margin: 0; font-size: 14px; }
    .rental-link { font-size: 12px; font-weight: 600; color: var(--tv-color-primary); }
    .rental-note { margin: 0; color: var(--tv-color-text-secondary); font-size: 12px; }
    .rental-note.error { color: var(--tv-color-danger); }
    .rental-note.warn { padding: 8px 10px; border-radius: 6px; background: var(--tv-color-warning-soft, #fff4e0); color: var(--tv-color-text); }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    td, th { padding: 7px 10px; border-bottom: 1px solid var(--tv-color-border); text-align: left; }
    td small { margin-left: 6px; color: var(--tv-color-text-secondary); font-size: 11px; }
    .amount { text-align: right; font-variant-numeric: tabular-nums; }
    .group th { background: var(--tv-color-background); font-size: 12px; color: var(--tv-color-text-secondary); }
    .total td { font-weight: 650; }
    .result td { font-weight: 700; font-size: 14px; border-bottom: 0; }
    .result.loss .amount { color: var(--tv-color-danger); }
  `],
})
export class PropertyRentalSummaryComponent {
  private readonly api = inject(TaxWorkspaceApiService);
  private readonly documents = inject(DocumentApiService);
  private readonly cdr = inject(ChangeDetectorRef);

  readonly property = input.required<PropertyDto>();
  readonly Math = Math;

  yearId: string | null = null;
  yearLabel = '';
  summary: RentalSummaryDto | null = null;
  errorMessage = '';

  constructor() {
    effect(() => {
      const yearId = this.documents.selectedFinancialYearId();
      const year = this.documents.financialYears().find((item) => item.id === yearId);
      const propertyId = this.property().id;

      this.yearId = yearId;
      this.yearLabel = year ? formatFinancialYear(year.year_code) : '';
      this.summary = null;
      this.errorMessage = '';

      if (yearId) {
        this.api.getRentalSummary(propertyId, yearId).subscribe({
          next: (summary) => {
            if (this.yearId === yearId) {
              this.summary = summary;
              this.cdr.markForCheck();
            }
          },
          error: () => {
            this.errorMessage = 'Unable to load the rental summary.';
            this.cdr.markForCheck();
          },
        });
      }

      this.cdr.markForCheck();
    });
  }

  money(value: number): string {
    return new Intl.NumberFormat('en-AU', { style: 'currency', currency: 'AUD' }).format(value);
  }

  recordsLabel(records: number): string {
    return records === 1 ? '1 record' : `${records} records`;
  }
}
