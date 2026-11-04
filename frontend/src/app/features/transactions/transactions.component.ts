import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  DestroyRef,
  OnInit,
  inject,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';

import { TaxIconComponent } from '../../shared/components/tax-icon/tax-icon.component';
import { BackNavigationComponent } from '../../shared/components/back-navigation/back-navigation.component';
import { formatFinancialYear } from '../../shared/utils/financial-year';
import { refreshEvery } from '../../shared/utils/status-refresh';
import {
  DocumentApiService,
  FinancialYearDto,
} from '../../shared/services/documents/document-api.service';
import {
  TaxWorkspaceApiService,
  TransactionDto,
} from '../../shared/services/tax-workspace-api.service';

import { AuthService } from '../../shared/services/auth/auth.service';

@Component({
  selector: 'app-transactions',
  imports: [FormsModule, TaxIconComponent, BackNavigationComponent],
  templateUrl: './transactions.component.html',
  styleUrl: './transactions.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TransactionsComponent implements OnInit {
  private readonly api = inject(TaxWorkspaceApiService);
  private readonly documentsApi = inject(DocumentApiService);
  private readonly cdr = inject(ChangeDetectorRef);
  readonly auth = inject(AuthService);
  private readonly destroyRef = inject(DestroyRef);

  transactions: TransactionDto[] = [];
  financialYears: FinancialYearDto[] = [];
  selectedDirection: 'all' | 'income' | 'expense' = 'all';
  searchText = '';
  isLoading = true;
  isSaving = false;
  showForm = false;
  errorMessage = '';
  successMessage = '';

  form = this.emptyForm();

  get filteredTransactions(): TransactionDto[] {
    const search = this.searchText.trim().toLowerCase();
    const selectedFinancialYearId =
      this.documentsApi.selectedFinancialYearId();

    return this.transactions.filter((transaction) =>
      (!selectedFinancialYearId
        || transaction.financial_year_id === selectedFinancialYearId)
      && (this.selectedDirection === 'all'
        || transaction.direction === this.selectedDirection)
      && (!search || transaction.description.toLowerCase().includes(search)),
    );
  }

  get financialYearLabel(): string {
    const selectedYearId = this.documentsApi.selectedFinancialYearId();
    const year = this.financialYears.find(
      (item) => item.id === selectedYearId,
    );

    return year ? `FY ${formatFinancialYear(year.year_code)}` : 'All financial years';
  }

  get totalIncome(): number {
    return this.filteredTransactions
      .filter((transaction) => transaction.direction === 'income')
      .reduce((total, transaction) => total + Number(transaction.amount), 0);
  }

  get totalExpenses(): number {
    return this.filteredTransactions
      .filter((transaction) => transaction.direction === 'expense')
      .reduce((total, transaction) => total + Number(transaction.amount), 0);
  }

  ngOnInit(): void {
    this.documentsApi.listAllFinancialYears().subscribe({
      next: (years) => {
        this.financialYears = years;
        this.loadTransactions();
        refreshEvery(
          () => this.api.listTransactions(),
          () => {
            this.errorMessage = 'Unable to refresh income and expense records.';
            this.cdr.markForCheck();
          },
        ).pipe(takeUntilDestroyed(this.destroyRef)).subscribe((transactions) => {
          this.transactions = transactions;
          this.errorMessage = '';
          this.cdr.markForCheck();
        });
      },
      error: () => {
        this.errorMessage = 'Unable to load financial years.';
        this.isLoading = false;
        this.cdr.markForCheck();
      },
    });
  }

  loadTransactions(): void {
    this.api.listTransactions().subscribe({
      next: (transactions) => {
        this.transactions = transactions;
        this.isLoading = false;
        this.cdr.markForCheck();
      },
      error: () => {
        this.errorMessage = 'Unable to load income and expense records.';
        this.isLoading = false;
        this.cdr.markForCheck();
      },
    });
  }

  saveTransaction(): void {
    if (
      this.isSaving
      || !this.form.transaction_date
      || !this.form.description.trim()
      || this.form.amount <= 0
    ) {
      return;
    }

    this.isSaving = true;
    this.errorMessage = '';
    this.successMessage = '';

    this.api.createTransaction({
      ...this.form,
      description: this.form.description.trim(),
    }).subscribe({
      next: (transaction) => {
        this.transactions = [transaction, ...this.transactions];
        this.form = this.emptyForm();
        this.showForm = false;
        this.successMessage = 'Transaction saved to your tax records.';
        this.isSaving = false;
        this.cdr.markForCheck();
      },
      error: (error: { status?: number }) => {
        this.errorMessage = error.status === 404
          ? 'No financial year matches this transaction date. Add the relevant financial year first.'
          : 'Unable to save this transaction. Check the details and try again.';
        this.isSaving = false;
        this.cdr.markForCheck();
      },
    });
  }

  formatCurrency(value: string | number): string {
    return new Intl.NumberFormat('en-AU', {
      style: 'currency',
      currency: 'AUD',
    }).format(Number(value));
  }

  formatDate(value: string): string {
    const date = new Date(value);

    return Number.isNaN(date.getTime())
      ? 'Date unavailable'
      : new Intl.DateTimeFormat('en-AU', {
          day: 'numeric',
          month: 'short',
          year: 'numeric',
        }).format(date);
  }

  financialYearFor(id: string): string {
    const yearCode = this.financialYears.find((year) => year.id === id)?.year_code;

    return yearCode ? formatFinancialYear(yearCode) : '—';
  }

  private emptyForm() {
    return {
      transaction_date: '',
      direction: 'expense' as 'income' | 'expense',
      amount: 0,
      description: '',
      currency: 'AUD',
    };
  }
}
