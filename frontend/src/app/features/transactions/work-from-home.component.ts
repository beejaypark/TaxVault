import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  effect,
  inject,
} from '@angular/core';
import { FormsModule } from '@angular/forms';

import { TaxIconComponent } from '../../shared/components/tax-icon/tax-icon.component';
import { DocumentApiService } from '../../shared/services/documents/document-api.service';
import { AuthService } from '../../shared/services/auth/auth.service';
import {
  TaxWorkspaceApiService,
  WorkFromHomeLogDto,
  WorkFromHomeSummary,
} from '../../shared/services/tax-workspace-api.service';
import { formatFinancialYear } from '../../shared/utils/financial-year';

@Component({
  selector: 'app-work-from-home',
  imports: [FormsModule, TaxIconComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <section class="panel" aria-label="Working from home log">
      <div class="head">
        <div>
          <h2>Working from home{{ yearLabel ? ' · FY ' + yearLabel : '' }}</h2>
          <p>Record the hours you worked from home each day. Hours × the per-hour rate gives the fixed-rate claim your accountant can use. Keep timesheets, rosters or a diary as proof.</p>
        </div>
      </div>

      @if (!yearId) {
        <p class="note">Select a financial year in the header to log working-from-home hours.</p>
      } @else {
        @if (errorMessage) { <p class="note error" role="alert">{{ errorMessage }}</p> }

        @if (summary) {
          <div class="stats">
            <div><span>Days</span><strong>{{ summary.days }}</strong></div>
            <div><span>Hours</span><strong>{{ summary.hours }}</strong></div>
            <div><span>Rate per hour</span><strong>{{ money(summary.rate) }}</strong></div>
            <div><span>Fixed-rate amount</span><strong>{{ money(summary.amount) }}</strong></div>
          </div>
        }

        @if (auth.canEdit) {
          <form class="row-form" (ngSubmit)="saveRate()">
            <label>Rate per hour (AUD) <input type="number" name="rate" min="0.01" max="10" step="0.01" [(ngModel)]="rate" /></label>
            <button type="submit" class="btn" [disabled]="!rate || rate <= 0">Save rate</button>
            @if (summary?.rate_is_default) { <small>Default rate shown. Confirm the current ATO rate for this year with your accountant.</small> }
          </form>

          <form class="row-form" (ngSubmit)="saveEntry()">
            <label>Date <input type="date" name="date" [(ngModel)]="date" required /></label>
            <label>Hours <input type="number" name="hours" min="0.25" max="24" step="0.25" [(ngModel)]="hours" required /></label>
            <label class="grow">Note <input name="note" maxlength="255" [(ngModel)]="note" placeholder="Optional" /></label>
            <button type="submit" class="btn primary" [disabled]="isSaving || !date || !hours || hours <= 0"><tv-icon name="plus" /> {{ isSaving ? 'Saving…' : 'Add day' }}</button>
          </form>
          <p class="note">Entering a date that already has hours replaces them.</p>
        }

        @if (logs.length === 0) {
          <p class="note">No days logged for this year yet.</p>
        } @else {
          <table>
            <thead><tr><th>Date</th><th>Hours</th><th>Note</th>@if (auth.canEdit) {<th></th>}</tr></thead>
            <tbody>
              @for (log of logs; track log.id) {
                <tr>
                  <td>{{ formatDate(log.work_date) }}</td>
                  <td>{{ log.hours }}</td>
                  <td>{{ log.note }}</td>
                  @if (auth.canEdit) {
                    <td class="end"><button type="button" class="btn danger" (click)="remove(log)" aria-label="Delete entry"><tv-icon name="delete" /></button></td>
                  }
                </tr>
              }
            </tbody>
          </table>
        }
      }
    </section>
  `,
  styles: [`
    .panel { display: grid; gap: 14px; padding: 20px; border: 1px solid var(--tv-color-border); border-radius: 12px; background: white; }
    h2 { margin: 0; font-size: 18px; }
    p { margin: 4px 0 0; color: var(--tv-color-text-secondary); font-size: 13px; }
    .note { margin: 0; color: var(--tv-color-text-secondary); font-size: 12px; }
    .note.error { color: var(--tv-color-danger); }
    .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; }
    .stats div { display: grid; gap: 4px; padding: 12px; border: 1px solid var(--tv-color-border); border-radius: 8px; background: var(--tv-color-background); }
    .stats span { color: var(--tv-color-text-secondary); font-size: 12px; }
    .stats strong { font-size: 18px; }
    .row-form { display: flex; flex-wrap: wrap; gap: 12px; align-items: end; }
    .row-form label { display: grid; gap: 6px; color: var(--tv-color-text-secondary); font-size: 12px; font-weight: 600; }
    .row-form .grow { flex: 1 1 200px; }
    .row-form small { flex-basis: 100%; color: var(--tv-color-text-secondary); font-size: 11px; }
    input { min-height: 38px; padding: 7px 10px; border: 1px solid var(--tv-color-border); border-radius: 6px; font-size: 13px; background: white; }
    .btn { display: inline-flex; align-items: center; gap: 6px; min-height: 38px; padding: 0 14px; border: 1px solid var(--tv-color-border); border-radius: 6px; background: white; font-size: 13px; cursor: pointer; }
    .btn.primary { background: var(--tv-color-primary); border-color: var(--tv-color-primary); color: white; }
    .btn.danger { color: var(--tv-color-danger); min-height: 30px; padding: 0 8px; }
    .btn:disabled { opacity: .55; cursor: not-allowed; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    td, th { padding: 7px 10px; border-bottom: 1px solid var(--tv-color-border); text-align: left; }
    th { font-size: 12px; color: var(--tv-color-text-secondary); }
    .end { text-align: right; }
  `],
})
export class WorkFromHomeComponent {
  private readonly api = inject(TaxWorkspaceApiService);
  private readonly documents = inject(DocumentApiService);
  private readonly cdr = inject(ChangeDetectorRef);
  readonly auth = inject(AuthService);

  yearId: string | null = null;
  yearLabel = '';
  summary: WorkFromHomeSummary | null = null;
  logs: WorkFromHomeLogDto[] = [];
  errorMessage = '';
  isSaving = false;
  rate: number | null = null;
  date = '';
  hours: number | null = null;
  note = '';

  constructor() {
    effect(() => {
      const yearId = this.documents.selectedFinancialYearId();
      const year = this.documents.financialYears().find((item) => item.id === yearId);

      this.yearId = yearId;
      this.yearLabel = year ? formatFinancialYear(year.year_code) : '';
      this.summary = null;
      this.logs = [];
      this.errorMessage = '';

      if (yearId) {
        this.load(yearId);
      }

      this.cdr.markForCheck();
    });
  }

  saveEntry(): void {
    if (this.isSaving || !this.date || !this.hours || this.hours <= 0) {
      return;
    }

    this.isSaving = true;
    this.errorMessage = '';

    this.api.saveWorkFromHome(this.date, this.hours, this.note.trim()).subscribe({
      next: () => {
        this.isSaving = false;
        this.hours = null;
        this.note = '';
        this.reload();
      },
      error: (error: { status?: number }) => {
        this.isSaving = false;
        this.errorMessage = error.status === 422
          ? 'No financial year covers this date, or the hours are invalid.'
          : 'Unable to save this entry.';
        this.cdr.markForCheck();
      },
    });
  }

  saveRate(): void {
    if (!this.yearId || !this.rate || this.rate <= 0) {
      return;
    }

    this.api.saveWorkFromHomeRate(this.yearId, this.rate).subscribe({
      next: (summary) => {
        this.summary = summary;
        this.cdr.markForCheck();
      },
      error: () => {
        this.errorMessage = 'Unable to save the rate.';
        this.cdr.markForCheck();
      },
    });
  }

  remove(log: WorkFromHomeLogDto): void {
    if (!confirm(`Delete the entry for ${this.formatDate(log.work_date)}?`)) {
      return;
    }

    this.api.deleteWorkFromHome(log.id).subscribe({
      next: () => this.reload(),
      error: () => {
        this.errorMessage = 'Unable to delete this entry.';
        this.cdr.markForCheck();
      },
    });
  }

  money(value: number): string {
    return new Intl.NumberFormat('en-AU', { style: 'currency', currency: 'AUD' }).format(value);
  }

  formatDate(value: string): string {
    const date = new Date(value.slice(0, 10));

    return Number.isNaN(date.getTime())
      ? value
      : new Intl.DateTimeFormat('en-AU', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' }).format(date);
  }

  private reload(): void {
    if (this.yearId) {
      this.load(this.yearId);
    }
  }

  private load(yearId: string): void {
    this.api.getWorkFromHome(yearId).subscribe({
      next: ({ summary, logs }) => {
        if (this.yearId !== yearId) {
          return;
        }

        this.summary = summary;
        this.logs = logs;
        this.rate = summary.rate;
        this.cdr.markForCheck();
      },
      error: () => {
        this.errorMessage = 'Unable to load working-from-home entries.';
        this.cdr.markForCheck();
      },
    });
  }
}
