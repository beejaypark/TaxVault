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
  PropertyDto,
  TransactionDto,
} from '../../shared/services/tax-workspace-api.service';
import {
  AtoCategoryOption,
  atoCategoriesFor,
  atoCategoryLabel,
  isRentalCategory,
} from '../../shared/utils/ato-categories';

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
  properties: PropertyDto[] = [];
  financialYears: FinancialYearDto[] = [];
  selectedDirection: 'all' | 'income' | 'expense' = 'all';
  searchText = '';
  isLoading = true;
  isSaving = false;
  showForm = false;
  editingId: string | null = null;
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

  get categoryOptions(): AtoCategoryOption[] {
    return atoCategoriesFor(this.form.direction);
  }

  get needsProperty(): boolean {
    return isRentalCategory(this.form.ato_category);
  }

  get isFormValid(): boolean {
    return !!this.form.transaction_date
      && !!this.form.description.trim()
      && this.form.amount > 0
      && (!this.needsProperty || !!this.form.property_id);
  }

  onDirectionChange(): void {
    const valid = this.categoryOptions.some((item) => item.code === this.form.ato_category);

    if (!valid) {
      this.form.ato_category = '';
      this.form.property_id = '';
    }
  }

  onCategoryChange(): void {
    if (!this.needsProperty) {
      this.form.property_id = '';
    } else if (!this.form.property_id && this.properties.length === 1) {
      this.form.property_id = this.properties[0].id;
    }
  }

  categoryLabel(code: string | null | undefined): string {
    return atoCategoryLabel(code);
  }

  propertyName(id: string | null | undefined): string {
    const property = this.properties.find((item) => item.id === id);

    return property ? property.reference_code || property.address_line_1 || 'Property' : '';
  }

  ngOnInit(): void {
    this.api.listProperties().subscribe({
      next: (properties) => {
        this.properties = properties;
        this.cdr.markForCheck();
      },
    });

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
    if (this.isSaving || !this.isFormValid) {
      return;
    }

    this.isSaving = true;
    this.errorMessage = '';
    this.successMessage = '';

    const payload = {
      ...this.form,
      description: this.form.description.trim(),
      ato_category: this.form.ato_category || null,
      property_id: this.needsProperty ? this.form.property_id || null : null,
    };
    const editing = this.editingId;
    const request$ = editing
      ? this.api.updateTransaction(editing, payload)
      : this.api.createTransaction(payload);

    request$.subscribe({
      next: (transaction) => {
        this.transactions = editing
          ? this.transactions.map((item) => (item.id === transaction.id ? transaction : item))
          : [transaction, ...this.transactions];
        this.closeForm();
        this.successMessage = editing
          ? 'Transaction updated.'
          : 'Transaction saved to your tax records.';
        this.isSaving = false;
        this.cdr.markForCheck();
      },
      error: (error: { status?: number }) => {
        this.errorMessage = error.status === 404 || error.status === 422
          ? 'No financial year matches this transaction date. Add the relevant financial year first.'
          : 'Unable to save this transaction. Check the details and try again.';
        this.isSaving = false;
        this.cdr.markForCheck();
      },
    });
  }

  startCreate(): void {
    this.closeForm();
    this.showForm = true;
  }

  startEdit(transaction: TransactionDto): void {
    this.errorMessage = '';
    this.successMessage = '';
    this.editingId = transaction.id;
    this.form = {
      transaction_date: transaction.transaction_date.slice(0, 10),
      direction: transaction.direction as 'income' | 'expense',
      amount: Number(transaction.amount),
      description: transaction.description,
      currency: transaction.currency ?? 'AUD',
      ato_category: transaction.ato_category ?? '',
      property_id: transaction.property_id ?? '',
    };
    this.showForm = true;
  }

  closeForm(): void {
    this.showForm = false;
    this.editingId = null;
    this.form = this.emptyForm();
  }

  deleteTransaction(transaction: TransactionDto): void {
    if (!confirm(`Delete "${transaction.description}"? This cannot be undone.`)) {
      return;
    }

    this.errorMessage = '';
    this.successMessage = '';

    this.api.deleteTransaction(transaction.id).subscribe({
      next: () => {
        this.transactions = this.transactions.filter((item) => item.id !== transaction.id);
        this.successMessage = 'Transaction deleted.';
        this.cdr.markForCheck();
      },
      error: () => {
        this.errorMessage = 'Unable to delete this transaction.';
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
      ato_category: '',
      property_id: '',
    };
  }
}
