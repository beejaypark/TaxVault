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
  suggestAtoCategory,
} from '../../shared/utils/ato-categories';

import { AuthService } from '../../shared/services/auth/auth.service';
import { TransactionImportComponent } from './transaction-import.component';
import { WorkFromHomeComponent } from './work-from-home.component';

@Component({
  selector: 'app-transactions',
  imports: [FormsModule, TaxIconComponent, BackNavigationComponent, TransactionImportComponent, WorkFromHomeComponent],
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
  showImport = false;
  showWfh = false;
  selectedIds = new Set<string>();
  bulkCategory = '';
  bulkProperty = '';
  isBulkSaving = false;

  form = this.emptyForm();

  get bulkOptions(): AtoCategoryOption[] {
    return [...atoCategoriesFor('income'), ...atoCategoriesFor('expense')];
  }

  get bulkNeedsProperty(): boolean {
    return isRentalCategory(this.bulkCategory);
  }

  get allVisibleSelected(): boolean {
    const visible = this.filteredTransactions;

    return visible.length > 0 && visible.every((item) => this.selectedIds.has(item.id));
  }

  toggleAll(checked: boolean): void {
    for (const item of this.filteredTransactions) {
      if (checked) {
        this.selectedIds.add(item.id);
      } else {
        this.selectedIds.delete(item.id);
      }
    }
  }

  toggleOne(id: string, checked: boolean): void {
    if (checked) {
      this.selectedIds.add(id);
    } else {
      this.selectedIds.delete(id);
    }
  }

  onBulkCategoryChange(): void {
    if (!this.bulkNeedsProperty) {
      this.bulkProperty = '';
    } else if (!this.bulkProperty && this.properties.length === 1) {
      this.bulkProperty = this.properties[0].id;
    }
  }

  // Groups selected rows by their suggested category and applies each group.
  suggestForSelected(): void {
    const groups = new Map<string, string[]>();

    for (const item of this.filteredTransactions.filter((t) => this.selectedIds.has(t.id))) {
      const code = suggestAtoCategory(item.description, item.direction as 'income' | 'expense');

      if (code && !isRentalCategory(code)) {
        groups.set(code, [...(groups.get(code) ?? []), item.id]);
      }
    }

    if (groups.size === 0) {
      this.errorMessage = 'No suggestions for the selected rows shown in the list. Pick an item manually.';
      this.cdr.markForCheck();
      return;
    }

    this.errorMessage = '';
    let remaining = groups.size;
    let updated = 0;

    for (const [code, ids] of groups) {
      this.api.bulkCategorise(ids, code, null).subscribe({
        next: (result) => {
          updated += result.updated;
          if (--remaining === 0) {
            this.finishBulk(`Suggested items applied to ${updated} transactions. Rental items need a property and were left for you.`);
          }
        },
        error: () => {
          if (--remaining === 0) {
            this.finishBulk(`Applied to ${updated} transactions; some groups failed.`);
          }
        },
      });
    }
  }

  applyBulk(): void {
    if (this.isBulkSaving || this.selectedIds.size === 0 || (this.bulkNeedsProperty && !this.bulkProperty)) {
      return;
    }

    this.isBulkSaving = true;
    this.errorMessage = '';

    this.api
      .bulkCategorise([...this.selectedIds], this.bulkCategory || null, this.bulkProperty || null)
      .subscribe({
        next: (result) => {
          this.finishBulk(
            `Updated ${result.updated} transactions.${result.skipped ? ` ${result.skipped} skipped because the item does not match income/expense.` : ''}`,
          );
        },
        error: () => {
          this.errorMessage = 'Unable to update the selected transactions.';
          this.isBulkSaving = false;
          this.cdr.markForCheck();
        },
      });
  }

  private finishBulk(message: string): void {
    this.isBulkSaving = false;
    this.successMessage = message;
    this.selectedIds = new Set<string>();
    this.bulkCategory = '';
    this.bulkProperty = '';
    this.loadTransactions();
  }

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
