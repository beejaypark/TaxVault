import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  inject,
  input,
  output,
} from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Observable } from 'rxjs';

import { TaxIconComponent } from '../../shared/components/tax-icon/tax-icon.component';
import {
  BankStatus,
  ImportResult,
  PropertyDto,
  TaxWorkspaceApiService,
} from '../../shared/services/tax-workspace-api.service';
import {
  AtoCategoryOption,
  atoCategoriesFor,
  isRentalCategory,
  suggestAtoCategory,
} from '../../shared/utils/ato-categories';
import { parseBankFile } from '../../shared/utils/bank-import';
import { TranslatePipe } from '../../shared/i18n/translate.pipe';

interface PreviewRow {
  date: string;
  description: string;
  amount: number;
  selected: boolean;
  category: string;
  propertyId: string;
}

const MAX_ROWS = 1000;

@Component({
  selector: 'app-transaction-import',
  imports: [FormsModule, TaxIconComponent, TranslatePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <section class="panel" [attr.aria-label]="'Transactions.ImportTitle' | translate">
      <div class="head">
        <div>
          <h2>{{ 'Transactions.ImportTitle' | translate }}</h2>
          <p>{{ 'Transactions.ImportDescription' | translate }}</p>
        </div>
        <button type="button" class="btn" (click)="closed.emit()"><tv-icon name="close" /> {{ 'Transactions.Close' | translate }}</button>
      </div>

      @if (bank && bank.enabled) {
        <div class="bank">
          <div>
            <strong>{{ 'Transactions.BankConnection' | translate }}</strong>
            <p>
              @if (bank.connected) {
                Connected through our bank data provider{{ bank.last_synced_at ? ', last synced ' + lastSynced : '' }}. Synced transactions arrive uncategorised; use bulk categorise afterwards.
              } @else {
                Connect your bank read-only to bring in transactions automatically. You approve access with the bank, and can revoke it there at any time.
              }
            </p>
          </div>
          <div class="actions">
            <button type="button" class="btn" [disabled]="bankBusy" (click)="connectBank()">{{ bank.connected ? ('Transactions.AddAnotherBank' | translate) : ('Transactions.ConnectBank' | translate) }}</button>
            @if (bank.connected) {
              <button type="button" class="btn primary" [disabled]="bankBusy" (click)="syncBank()">{{ bankBusy ? ('Transactions.Working' | translate) : ('Transactions.SyncNow' | translate) }}</button>
            }
          </div>
        </div>
      }

      <label class="file">{{ 'Transactions.BankFile' | translate }}
        <input type="file" accept=".csv,.ofx,.qfx,text/csv" (change)="onFile($event)" />
      </label>

      @if (errorMessage) { <p class="note error" role="alert">{{ errorMessage }}</p> }
      @if (result) {
        <p class="note success" role="status">
          Imported {{ result.imported }}. Skipped {{ result.duplicates }} already imported@if (result.outside_financial_years > 0) { and {{ result.outside_financial_years }} outside your financial years (add the year first) }.
        </p>
      }

      @if (rows.length > 0) {
        <p class="note">
          {{ selectedCount }} of {{ rows.length }} rows selected@if (skippedRows > 0) { · {{ skippedRows }} unreadable rows ignored }.
          {{ 'Transactions.SuggestedHint' | translate }}
        </p>
        <div class="table-wrap">
          <table>
            <thead><tr><th></th><th>Date</th><th>Description</th><th class="amount">Amount</th><th>Tax return item</th></tr></thead>
            <tbody>
              @for (row of rows; track $index) {
                <tr [class.off]="!row.selected">
                  <td><input type="checkbox" [(ngModel)]="row.selected" [name]="'sel' + $index" [attr.aria-label]="'Import ' + row.description" /></td>
                  <td>{{ row.date }}</td>
                  <td>{{ row.description }}</td>
                  <td class="amount" [class.credit]="row.amount > 0">{{ row.amount > 0 ? '+' : '−' }}{{ money(abs(row.amount)) }}</td>
                  <td>
                    <select [name]="'cat' + $index" [(ngModel)]="row.category" (ngModelChange)="onCategory(row)" [attr.aria-label]="'Tax return item for ' + row.description">
                      <option value="">Not categorised</option>
                      @for (option of optionsFor(row); track option.code) { <option [value]="option.code">{{ option.label }} ({{ option.ref }})</option> }
                    </select>
                    @if (isRental(row.category)) {
                      <select [name]="'prop' + $index" [(ngModel)]="row.propertyId" aria-label="Rental property">
                        <option value="">Select property</option>
                        @for (property of properties(); track property.id) { <option [value]="property.id">{{ property.reference_code || property.address_line_1 }}</option> }
                      </select>
                    }
                  </td>
                </tr>
              }
            </tbody>
          </table>
        </div>
        <div class="actions">
          <button type="button" class="btn primary" [disabled]="isImporting || selectedCount === 0" (click)="runImport()">
            {{ isImporting ? ('Transactions.Importing' | translate) : ('Transactions.ImportTransactions' | translate) + ' ' + selectedCount }}
          </button>
        </div>
      }
    </section>
  `,
  styles: [`
    .panel { display: grid; gap: 12px; padding: 20px; border: 1px solid var(--tv-color-border); border-radius: 12px; background: white; }
    .head { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; }
    h2 { margin: 0; font-size: 18px; }
    p { margin: 4px 0 0; color: var(--tv-color-text-secondary); font-size: 13px; }
    .note { margin: 0; color: var(--tv-color-text-secondary); font-size: 12px; }
    .note.error { color: var(--tv-color-danger); }
    .note.success { color: var(--tv-color-success); }
    .file { display: grid; gap: 6px; font-size: 12px; font-weight: 600; color: var(--tv-color-text-secondary); }
    .table-wrap { max-height: 420px; overflow: auto; border: 1px solid var(--tv-color-border); border-radius: 8px; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    td, th { padding: 6px 10px; border-bottom: 1px solid var(--tv-color-border); text-align: left; vertical-align: top; }
    th { position: sticky; top: 0; background: var(--tv-color-background); font-size: 12px; }
    tr.off td { opacity: .5; }
    .amount { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .credit { color: var(--tv-color-success); }
    select { max-width: 260px; min-height: 32px; border: 1px solid var(--tv-color-border); border-radius: 6px; font-size: 12px; margin-right: 6px; }
    .bank { display: flex; justify-content: space-between; gap: 12px; align-items: center; flex-wrap: wrap; padding: 12px; border: 1px solid var(--tv-color-border); border-radius: 8px; }
    .bank .actions { gap: 8px; }
    .actions { display: flex; justify-content: flex-end; }
    .btn { display: inline-flex; align-items: center; gap: 6px; min-height: 36px; padding: 0 14px; border: 1px solid var(--tv-color-border); border-radius: 6px; background: white; font-size: 13px; cursor: pointer; }
    .btn.primary { background: var(--tv-color-primary); border-color: var(--tv-color-primary); color: white; }
    .btn:disabled { opacity: .55; cursor: not-allowed; }
  `],
})
export class TransactionImportComponent {
  private readonly api = inject(TaxWorkspaceApiService);
  private readonly cdr = inject(ChangeDetectorRef);

  readonly properties = input.required<PropertyDto[]>();
  readonly closed = output<void>();
  readonly imported = output<void>();

  rows: PreviewRow[] = [];
  skippedRows = 0;
  errorMessage = '';
  result: ImportResult | null = null;
  isImporting = false;
  bank: BankStatus | null = null;
  bankBusy = false;

  constructor() {
    this.api.getBankStatus().subscribe({
      next: (status) => {
        this.bank = status;
        this.cdr.markForCheck();
      },
      error: () => undefined,
    });
  }

  get lastSynced(): string {
    return this.bank?.last_synced_at ? new Date(this.bank.last_synced_at).toLocaleString('en-AU') : '';
  }

  connectBank(): void {
    this.runBank(this.api.connectBank(), (url) => {
      window.open(url, '_blank', 'noopener');
      this.errorMessage = '';
      if (this.bank) {
        this.bank = { ...this.bank, connected: true };
      }
    });
  }

  syncBank(): void {
    this.runBank(this.api.syncBank(), (result) => {
      this.result = result;
      if (this.bank) {
        this.bank = { ...this.bank, last_synced_at: result.last_synced_at };
      }
      this.imported.emit();
    });
  }

  private runBank<T>(request: Observable<T>, done: (value: T) => void): void {
    this.bankBusy = true;
    this.errorMessage = '';
    request.subscribe({
      next: (value) => {
        done(value);
        this.bankBusy = false;
        this.cdr.markForCheck();
      },
      error: () => {
        this.errorMessage = 'The bank provider could not be reached. Try again shortly.';
        this.bankBusy = false;
        this.cdr.markForCheck();
      },
    });
  }
  get selectedCount(): number {
    return this.rows.filter((row) => row.selected).length;
  }

  abs(value: number): number {
    return Math.abs(value);
  }

  money(value: number): string {
    return new Intl.NumberFormat('en-AU', { style: 'currency', currency: 'AUD' }).format(value);
  }

  isRental(code: string): boolean {
    return isRentalCategory(code);
  }

  optionsFor(row: PreviewRow): AtoCategoryOption[] {
    return atoCategoriesFor(row.amount > 0 ? 'income' : 'expense');
  }

  onCategory(row: PreviewRow): void {
    if (!isRentalCategory(row.category)) {
      row.propertyId = '';
    } else if (!row.propertyId && this.properties().length === 1) {
      row.propertyId = this.properties()[0].id;
    }
  }

  async onFile(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    this.rows = [];
    this.result = null;
    this.errorMessage = '';

    if (!file) {
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
      this.errorMessage = 'This file is larger than 5 MB. Export a shorter date range.';
      this.cdr.markForCheck();
      return;
    }

    const parsed = parseBankFile(await file.text(), file.name);

    if (parsed.error) {
      this.errorMessage = parsed.error;
    } else if (parsed.rows.length > MAX_ROWS) {
      this.errorMessage = `This file has ${parsed.rows.length} rows. Import at most ${MAX_ROWS} at a time by exporting a shorter date range.`;
    } else {
      this.skippedRows = parsed.skipped;
      this.rows = parsed.rows.map((row) => {
        const category = suggestAtoCategory(row.description, row.amount > 0 ? 'income' : 'expense');
        const preview: PreviewRow = { ...row, selected: true, category, propertyId: '' };
        this.onCategory(preview);

        return preview;
      });
    }

    this.cdr.markForCheck();
  }

  runImport(): void {
    const chosen = this.rows.filter((row) => row.selected);

    if (this.isImporting || chosen.length === 0) {
      return;
    }

    const missingProperty = chosen.some((row) => isRentalCategory(row.category) && !row.propertyId);
    if (missingProperty) {
      this.errorMessage = 'Choose a rental property for every rental item, or set it to Not categorised.';
      this.cdr.markForCheck();
      return;
    }

    this.isImporting = true;
    this.errorMessage = '';

    this.api
      .importTransactions(
        chosen.map((row) => ({
          date: row.date,
          description: row.description,
          amount: row.amount,
          ato_category: row.category || null,
          property_id: row.propertyId || null,
        })),
      )
      .subscribe({
        next: (result) => {
          this.result = result;
          this.rows = [];
          this.isImporting = false;
          this.imported.emit();
          this.cdr.markForCheck();
        },
        error: () => {
          this.errorMessage = 'Unable to import these transactions. Check the file and try again.';
          this.isImporting = false;
          this.cdr.markForCheck();
        },
      });
  }
}
