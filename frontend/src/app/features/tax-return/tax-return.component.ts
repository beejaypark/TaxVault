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

@Component({
  selector: 'app-tax-return',
  imports: [DatePipe, TaxIconComponent, BackNavigationComponent, CashFlowAnalysisComponent, ReturnSummaryComponent],
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
        this.successMessage = 'Accountant pack generated and saved.';
        this.isExporting = false;
        this.cdr.markForCheck();
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
