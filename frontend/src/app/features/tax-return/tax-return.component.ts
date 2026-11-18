import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  OnInit,
  effect,
  inject,
  untracked,
} from '@angular/core';
import { DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';

import { TaxIconComponent } from '../../shared/components/tax-icon/tax-icon.component';
import { BackNavigationComponent } from '../../shared/components/back-navigation/back-navigation.component';
import { CashFlowAnalysisComponent } from '../../shared/components/cash-flow-analysis/cash-flow-analysis.component';
import { formatFinancialYear } from '../../shared/utils/financial-year';
import { ReturnSummaryComponent } from './return-summary.component';
import {
  DocumentApiService,
  FinancialYearDto,
} from '../../shared/services/documents/document-api.service';
import {
  ExportDto,
  TaxWorkspaceApiService,
  TransactionDto,
} from '../../shared/services/tax-workspace-api.service';
import { TranslatePipe } from '../../shared/i18n/translate.pipe';
import { CollapsibleCardDirective } from '../../shared/directives/collapsible-card.directive';

@Component({
  selector: 'app-tax-return',
  imports: [DatePipe, FormsModule, TaxIconComponent, BackNavigationComponent, CashFlowAnalysisComponent, ReturnSummaryComponent, TranslatePipe, CollapsibleCardDirective],
  templateUrl: './tax-return.component.html',
  styleUrl: './tax-return.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TaxReturnComponent implements OnInit {
  private readonly documentsApi = inject(DocumentApiService);
  private readonly workspaceApi = inject(TaxWorkspaceApiService);
  private readonly cdr = inject(ChangeDetectorRef);

  financialYears: FinancialYearDto[] = [];
  selectedFinancialYearId = '';
  latestExport: ExportDto | null = null;
  isLoading = true;
  isExporting = false;
  isDownloadingCsv = false;
  isDownloadingPack = false;
  showAdvancedExports = false;
  packFormat: 'json' | 'excel' | 'pdf' | 'csv' = 'json';
  transactions: TransactionDto[] = [];
  errorMessage = '';
  successMessage = '';

  get exportedYearCode(): string {
    const yearCode = this.financialYears.find(
      (year) => year.id === this.latestExport?.financial_year_id,
    )?.year_code;

    return yearCode ? formatFinancialYear(yearCode) : 'Tax year';
  }

  private yearCache: { source: TransactionDto[]; yearId: string; result: TransactionDto[] } | null = null;

  get yearTransactions(): TransactionDto[] {
    if (this.yearCache?.source === this.transactions && this.yearCache.yearId === this.selectedFinancialYearId) {
      return this.yearCache.result;
    }

    const result = this.transactions
      .filter((item) => item.financial_year_id === this.selectedFinancialYearId)
      .sort((a, b) => a.transaction_date.localeCompare(b.transaction_date));

    this.yearCache = { source: this.transactions, yearId: this.selectedFinancialYearId, result };

    return result;
  }

  get totalIncome(): number {
    return this.sumDirection('income');
  }

  get totalExpenses(): number {
    return this.sumDirection('expense');
  }

  get selectedYear(): FinancialYearDto | null {
    return this.financialYears.find((year) => year.id === this.selectedFinancialYearId) ?? null;
  }

  get selectedYearLabel(): string {
    const code = this.financialYears.find(
      (year) => year.id === this.selectedFinancialYearId,
    )?.year_code;

    return code ? formatFinancialYear(code) : '';
  }

  formatMoney(value: number | string): string {
    return new Intl.NumberFormat('en-AU', {
      style: 'currency',
      currency: 'AUD',
    }).format(Number(value));
  }

  printSummary(): void {
    window.print();
  }

  downloadCsv(): void {
    if (this.isDownloadingCsv || !this.selectedFinancialYearId) {
      return;
    }

    this.isDownloadingCsv = true;
    this.errorMessage = '';

    this.workspaceApi.downloadTransactionsCsv(this.selectedFinancialYearId).subscribe({
      next: (blob) => {
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');

        link.href = url;
        link.download = `taxvault-${this.selectedYearLabel || 'tax-year'}-transactions.csv`;
        link.click();
        URL.revokeObjectURL(url);
        this.isDownloadingCsv = false;
        this.cdr.markForCheck();
      },
      error: () => {
        this.errorMessage = 'Unable to download the CSV. Please try again.';
        this.isDownloadingCsv = false;
        this.cdr.markForCheck();
      },
    });
  }

  private sumDirection(direction: 'income' | 'expense'): number {
    return this.yearTransactions
      .filter((item) => item.direction === direction)
      .reduce((total, item) => total + Number(item.amount), 0);
  }

  formatYearCode(yearCode: string): string {
    return formatFinancialYear(yearCode);
  }

  constructor() {
    // The header selector is the single source of truth for the financial year.
    effect(() => {
      const globalId = this.documentsApi.selectedFinancialYearId();

      untracked(() => {
        if (this.financialYears.length === 0) {
          return;
        }
        const valid = this.financialYears.some((year) => year.id === globalId);
        const next = valid ? globalId ?? '' : this.documentsApi.defaultFinancialYearId(this.financialYears) ?? '';

        if (!valid) {
          this.documentsApi.selectFinancialYear(next || null);
        }
        this.selectedFinancialYearId = next;
        this.cdr.markForCheck();
      });
    });
  }

  ngOnInit(): void {
    this.documentsApi.listAllFinancialYears().subscribe({
      next: (years) => {
        this.financialYears = years;
        const selectedYearId = this.documentsApi.selectedFinancialYearId();
        this.selectedFinancialYearId = years.some(
          (year) => year.id === selectedYearId,
        )
          ? selectedYearId ?? ''
          : this.documentsApi.defaultFinancialYearId(years) ?? '';
        this.documentsApi.selectFinancialYear(
          this.selectedFinancialYearId || null,
        );
        this.isLoading = false;
        this.cdr.markForCheck();
        this.workspaceApi.listTransactions().subscribe({
          next: (transactions) => {
            this.transactions = transactions;
            this.cdr.markForCheck();
          },
        });
      },
      error: () => {
        this.errorMessage = 'Unable to load financial years. Please try again.';
        this.isLoading = false;
        this.cdr.markForCheck();
      },
    });
  }

  generateExport(): void {
    if (this.isExporting || !this.selectedFinancialYearId) {
      return;
    }

    this.isExporting = true;
    this.errorMessage = '';
    this.successMessage = '';

    this.workspaceApi.createExport(this.selectedFinancialYearId).subscribe({
      next: (result) => {
        this.latestExport = result;
        if (this.packFormat === 'json') {
          this.successMessage = 'Accountant pack generated and saved.';
          this.isExporting = false;
          this.cdr.markForCheck();
        } else {
          this.workspaceApi.downloadAccountantPack(this.selectedFinancialYearId, this.packFormat).subscribe({
            next: (blob) => {
              const extension = this.packFormat === 'excel' ? 'xlsx' : this.packFormat;
              this.downloadBlob(blob, `taxvault-${this.exportedYearCode}-accountant-pack.${extension}`);
              this.successMessage = 'Accountant pack generated and downloaded.';
              this.isExporting = false;
              this.cdr.markForCheck();
            },
            error: () => { this.errorMessage = 'Unable to download the selected pack format.'; this.isExporting = false; this.cdr.markForCheck(); },
          });
        }
      },
      error: (error: { status?: number }) => {
        this.errorMessage = error.status === 422
          ? 'This financial year cannot be exported yet. Check the year and required records.'
          : 'Unable to generate the accountant pack. Please try again.';
        this.isExporting = false;
        this.cdr.markForCheck();
      },
    });
  }

  private downloadBlob(blob: Blob, filename: string): void {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.click();
    URL.revokeObjectURL(url);
  }

  private printPack(payload: ExportDto['payload']): void {
    const data = payload as {
      tax_records?: { transactions?: Record<string, unknown>[] };
      assets?: { property_deductions?: Record<string, unknown>[]; properties?: Record<string, unknown>[]; investments?: Record<string, unknown>[]; mortgages?: Record<string, unknown>[]; mortgage_interest_entries?: Record<string, unknown>[]; mortgage_rate_changes?: Record<string, unknown>[]; asset_valuations?: Record<string, unknown>[] };
      source_references?: { documents?: Record<string, unknown>[] };
    };
    const groups = [
      ['Transactions (income & expenses)', data.tax_records?.transactions ?? []],
      ['Property deductions', data.assets?.property_deductions ?? []],
      ['Interest paid', data.assets?.mortgage_interest_entries ?? []],
      ['Investments', data.assets?.investments ?? []],
      ['Documents', data.source_references?.documents ?? []],
    ] as const;
    const win = window.open('', '_blank', 'noopener,noreferrer');
    if (!win) return;
    const esc = (value: unknown) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char] ?? char));
    const html = groups.map(([title, rows]) => `<section><h2>${esc(title)}</h2><table><thead><tr><th>Item</th><th>Details</th><th>Amount</th></tr></thead><tbody>${rows.map((row: Record<string, unknown>) => {
      const first = (...keys: string[]) => keys.map((key) => row[key]).find((value) => value !== undefined && value !== null && value !== '') ?? '';
      return `<tr><td>${esc(first('description', 'property_reference_code', 'reference_code', 'original_filename', 'deduction_type', 'investment_type', 'id'))}</td><td>${esc(first('bank_name', 'month', 'ato_category', 'address_line_1', 'notes', 'status', 'acquisition_date'))}</td><td>${esc(first('interest_amount', 'amount', 'current_value'))}</td></tr>`;
    }).join('')}</tbody></table></section>`).join('');
    win.document.write(`<!doctype html><html><head><title>TaxVault Accountant Pack - ${esc(this.exportedYearCode)}</title><style>body{font-family:Arial,sans-serif;color:#17243b;margin:32px}h1{font-size:24px}h2{font-size:16px;margin-top:28px;border-bottom:2px solid #17385f;padding-bottom:6px}table{border-collapse:collapse;width:100%;font-size:11px}th,td{border:1px solid #d8dee8;padding:6px;text-align:left}th{background:#edf3fb}@media print{body{margin:16mm}}</style></head><body><h1>TaxVault Accountant Pack - FY ${esc(this.exportedYearCode)}</h1><p>Generated ${esc(new Date().toLocaleString('en-AU'))}</p>${html}</body></html>`);
    win.document.close();
    win.focus();
    setTimeout(() => win.print(), 250);
  }

  downloadExport(): void {
    if (!this.latestExport) {
      return;
    }

    const file = new Blob(
      [JSON.stringify(this.latestExport.payload, null, 2)],
      { type: 'application/json' },
    );
    const url = URL.createObjectURL(file);
    const link = document.createElement('a');
    const year = this.financialYears.find(
      (item) => item.id === this.latestExport?.financial_year_id,
    )?.year_code ?? 'tax-year';

    link.href = url;
    link.download = `taxvault-${year}-return-pack.json`;
    link.click();
    URL.revokeObjectURL(url);
  }

  formatDate(value: string): string {
    const date = new Date(value);

    return Number.isNaN(date.getTime())
      ? 'Date unavailable'
      : new Intl.DateTimeFormat('en-AU', {
          day: 'numeric',
          month: 'long',
          year: 'numeric',
          hour: 'numeric',
          minute: '2-digit',
        }).format(date);
  }
}
