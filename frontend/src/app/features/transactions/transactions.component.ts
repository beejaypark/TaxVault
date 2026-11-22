import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  DestroyRef,
  ElementRef,
  OnInit,
  ViewChild,
  inject,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute } from '@angular/router';
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
  RecurringIncomeRuleDto,
  RecurringIncomePreview,
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
import { TranslatePipe } from '../../shared/i18n/translate.pipe';
import { CollapsibleCardDirective } from '../../shared/directives/collapsible-card.directive';
import { TranslationService } from '../../shared/i18n/translation.service';

@Component({
  selector: 'app-transactions',
  imports: [FormsModule, TaxIconComponent, BackNavigationComponent, TransactionImportComponent, WorkFromHomeComponent, TranslatePipe, CollapsibleCardDirective],
  templateUrl: './transactions.component.html',
  styleUrl: './transactions.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TransactionsComponent implements OnInit {
  private readonly api = inject(TaxWorkspaceApiService);
  private readonly documentsApi = inject(DocumentApiService);
  private readonly cdr = inject(ChangeDetectorRef);
  readonly auth = inject(AuthService);
  private readonly i18n = inject(TranslationService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly route = inject(ActivatedRoute, { optional: true });

  transactions: TransactionDto[] = [];
  properties: PropertyDto[] = [];
  financialYears: FinancialYearDto[] = [];
  selectedDirection: 'all' | 'income' | 'expense' = 'all';
  sortKey: 'description' | 'transaction_date' | 'direction' | 'financial_year_id' | 'amount' = 'transaction_date';
  sortDirection: 'asc' | 'desc' = 'desc';
  searchText = this.route?.snapshot.queryParamMap.get('q') ?? '';
  isLoading = true;
  isSaving = false;
  showForm = false;
  editingId: string | null = null;
  errorMessage = '';
  successMessage = '';
  attentionFilter = '';
  showImport = false;
  showWfh = false;
  selectedIds = new Set<string>();
  bulkCategory = '';
  bulkProperty = '';
  isBulkSaving = false;
  recurringIncome: RecurringIncomeRuleDto[] = [];
  backfillRuleId: string | null = null;
  backfillPreview: RecurringIncomePreview | null = null;
  backfillDates = new Set<string>();
  backfillBusy = false;
  backfillError = '';
  backfillResult: { created: number; skipped: number } | null = null;
  recurringForm = { name: '', description: '', amount: 0, day_of_month: 1, start_date: new Date().toISOString().slice(0, 10), end_date: '' };
  showRecurringForm = false;
  editingRecurringId: string | null = null;
  incomeSource = 'manual';

  form = this.emptyForm();

  @ViewChild('transactionEditor') transactionEditor?: ElementRef<HTMLElement>;

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
    const filtered = this.transactionsMatchingSearch.filter(transaction =>
      this.selectedDirection === 'all' || transaction.direction === this.selectedDirection,
    );

    return filtered.sort((left, right) => {
      const a = this.sortValue(left, this.sortKey);
      const b = this.sortValue(right, this.sortKey);
      const comparison = typeof a === 'number' && typeof b === 'number'
        ? a - b
        : String(a).localeCompare(String(b), undefined, { numeric: true, sensitivity: 'base' });

      return this.sortDirection === 'asc' ? comparison : -comparison;
    });
  }

  get directionCounts(): { all: number; income: number; expense: number } {
    const transactions = this.transactionsMatchingSearch;
    const counts = { all: transactions.length, income: 0, expense: 0 };
    for (const transaction of transactions) counts[transaction.direction]++;
    return counts;
  }

  changeDirection(direction: 'all' | 'income' | 'expense'): void {
    if (this.selectedDirection === direction) return;
    this.selectedDirection = direction;
    this.selectedIds.clear();
  }

  private get transactionsMatchingSearch(): TransactionDto[] {
    const search = this.searchText.trim().toLowerCase();
    const selectedFinancialYearId =
      this.documentsApi.selectedFinancialYearId();

    return this.transactions.filter((transaction) =>
      (!selectedFinancialYearId
        || transaction.financial_year_id === selectedFinancialYearId)
      && (!search || transaction.description.toLowerCase().includes(search)),
    );
  }

  sortTransactions(key: typeof this.sortKey): void {
    if (this.sortKey === key) {
      this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
      this.sortKey = key;
      this.sortDirection = key === 'amount' ? 'desc' : 'asc';
    }
  }

  sortIndicator(key: typeof this.sortKey): string {
    return this.sortKey === key ? (this.sortDirection === 'asc' ? ' ▲' : ' ▼') : '';
  }

  private sortValue(transaction: TransactionDto, key: typeof this.sortKey): string | number {
    if (key === 'amount') {
      return Number(transaction.amount);
    }

    return transaction[key] ?? '';
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
    return !this.transactionDateError()
      && !this.transactionDescriptionError()
      && !this.transactionAmountError()
      && (!this.needsProperty || !!this.form.property_id);
  }

  transactionDateError(): string | null {
    if (!this.form.transaction_date) return this.i18n.translate('Common.Required');
    const date = new Date(`${this.form.transaction_date}T00:00:00Z`);
    return Number.isNaN(date.getTime()) || date.toISOString().slice(0, 10) !== this.form.transaction_date ? this.i18n.translate('Common.ValidationFailed') : null;
  }

  transactionDescriptionError(): string | null {
    return this.form.description.trim() ? null : this.i18n.translate('Common.Required');
  }

  transactionAmountError(): string | null {
    return Number.isFinite(Number(this.form.amount)) && Number(this.form.amount) > 0 ? null : this.i18n.translate('Common.ValidationFailed');
  }

  recurringFieldError(field: 'name' | 'amount' | 'day' | 'start' | 'end'): string | null {
    if (field === 'name') return this.recurringForm.name.trim() ? null : this.i18n.translate('Common.Required');
    if (field === 'amount') return Number(this.recurringForm.amount) > 0 ? null : this.i18n.translate('Common.ValidationFailed');
    if (field === 'day') return Number(this.recurringForm.day_of_month) >= 1 && Number(this.recurringForm.day_of_month) <= 28 ? null : this.i18n.translate('Common.ValidationFailed');
    if (field === 'start') return this.recurringForm.start_date ? null : this.i18n.translate('Common.Required');
    if (!this.recurringForm.end_date) return null;
    return new Date(`${this.recurringForm.end_date}T00:00:00Z`).toString() === 'Invalid Date' ? this.i18n.translate('Common.ValidationFailed') : null;
  }

  onDirectionChange(): void {
    if (this.form.direction !== 'income') this.incomeSource = 'manual';
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

  hasAttention(transaction: TransactionDto): boolean {
    return this.transactionAttention(transaction) !== '';
  }

  transactionAttention(transaction: TransactionDto): '' | 'missing_evidence' | 'uncategorised_transactions' {
    if (this.attentionFilter === 'missing_evidence' && !transaction.document_id) return 'missing_evidence';
    if (this.attentionFilter === 'uncategorised_transactions' && !transaction.ato_category) return 'uncategorised_transactions';

    return '';
  }

  ngOnInit(): void {
    if (this.auth.canEdit) this.loadRecurringIncome();
    this.route?.queryParamMap.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((params) => {
      this.searchText = params.get('q') ?? this.searchText;
      this.attentionFilter = params.get('attention') ?? '';
      this.cdr.markForCheck();
    });
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

  loadRecurringIncome(): void {
    this.api.listRecurringIncome().subscribe({ next: (items) => { this.recurringIncome = items; this.cdr.markForCheck(); } });
  }

  previewMissingIncome(ruleId: string): void {
    if (this.backfillBusy) return;
    this.backfillRuleId = ruleId;
    this.backfillPreview = null;
    this.backfillResult = null;
    this.backfillError = '';
    this.backfillBusy = true;

    this.api.previewMissingIncome(ruleId).subscribe({
      next: preview => {
        this.backfillPreview = preview;
        this.backfillDates = new Set(preview.missing);
        this.backfillBusy = false;
        this.cdr.markForCheck();
      },
      error: () => {
        this.backfillError = 'Transactions.BackfillPreviewFailed';
        this.backfillBusy = false;
        this.cdr.markForCheck();
      },
    });
  }

  toggleBackfillDate(date: string, checked: boolean): void {
    if (checked) this.backfillDates.add(date);
    else this.backfillDates.delete(date);
  }

  generateMissingIncome(): void {
    const { backfillRuleId: id, backfillPreview: preview } = this;
    if (!id || !preview?.year || !this.backfillDates.size || this.backfillBusy) return;
    this.backfillBusy = true;
    this.backfillError = '';

    this.api.generateMissingIncome(id, preview.year, preview.amount, [...this.backfillDates]).subscribe({
      next: result => {
        this.backfillResult = result;
        this.backfillPreview = null;
        this.backfillBusy = false;
        this.loadTransactions();
        this.cdr.markForCheck();
      },
      error: () => {
        this.backfillPreview = null;
        this.backfillError = 'Transactions.BackfillSaveFailed';
        this.backfillBusy = false;
        this.cdr.markForCheck();
      },
    });
  }

  saveRecurringIncome(): void {
    const amount = Number(this.recurringForm.amount);
    const name = this.recurringForm.name.trim();
    const description = this.recurringForm.description.trim() || name;
    if (!name || !Number.isFinite(amount) || amount <= 0) {
      this.errorMessage = 'Enter a company/source and a gross amount greater than zero.';
      this.cdr.markForCheck();
      return;
    }
    this.errorMessage = '';
    const payload = { ...this.recurringForm, amount, name, description, end_date: this.recurringForm.end_date || null };
    const request$ = this.editingRecurringId
      ? this.api.updateRecurringIncome(this.editingRecurringId, payload)
      : this.api.createRecurringIncome(payload);
    request$.subscribe({
      next: (item) => { this.recurringIncome = this.editingRecurringId ? this.recurringIncome.map((rule) => rule.id === item.id ? item : rule) : [...this.recurringIncome, item]; this.resetRecurringForm(); this.successMessage = this.editingRecurringId ? 'Recurring income updated.' : 'Recurring income saved.'; this.cdr.markForCheck(); },
      error: (error: { error?: { message?: string } }) => { this.errorMessage = error?.error?.message || 'Unable to save recurring income.'; this.cdr.markForCheck(); },
    });
  }

  editRecurringIncome(item: RecurringIncomeRuleDto): void {
    this.editingRecurringId = item.id;
    this.recurringForm = { name: item.name, description: item.description, amount: Number(item.amount), day_of_month: item.day_of_month, start_date: item.start_date.slice(0, 10), end_date: item.end_date?.slice(0, 10) ?? '' };
    this.showRecurringForm = true;
    this.errorMessage = '';
  }

  resetRecurringForm(): void {
    this.editingRecurringId = null;
    this.recurringForm = { name: '', description: '', amount: 0, day_of_month: 1, start_date: new Date().toISOString().slice(0, 10), end_date: '' };
    this.showRecurringForm = false;
  }

  toggleRecurringIncome(item: RecurringIncomeRuleDto): void {
    this.api.updateRecurringIncome(item.id, { active: !item.active }).subscribe({ next: (updated) => { this.recurringIncome = this.recurringIncome.map((rule) => rule.id === updated.id ? updated : rule); this.cdr.markForCheck(); } });
  }

  selectIncomeSource(value: string): void {
    this.incomeSource = value;
    if (value === 'manual') return;
    const rule = this.recurringIncome.find((item) => item.id === value);
    if (!rule) return;
    this.form.description = rule.description;
    this.form.amount = Number(rule.amount);
    this.form.ato_category = rule.ato_category ?? '';
    this.cdr.markForCheck();
  }

  deleteRecurringIncome(item: RecurringIncomeRuleDto): void {
    if (!confirm(`Delete recurring income "${item.name}"?`)) return;
    this.api.deleteRecurringIncome(item.id).subscribe({ next: () => { this.recurringIncome = this.recurringIncome.filter((rule) => rule.id !== item.id); this.cdr.markForCheck(); } });
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
    if (this.isSaving) {
      return;
    }

    if (!this.isFormValid) {
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
    this.incomeSource = 'manual';
    this.form.transaction_date = this.defaultTransactionDate();
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
    this.incomeSource = 'manual';
    this.showForm = true;
    setTimeout(() => this.transactionEditor?.nativeElement.scrollIntoView({ behavior: 'smooth', block: 'start' }));
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

  private defaultTransactionDate(): string {
    const selectedYearId = this.documentsApi.selectedFinancialYearId();
    const selectedYear = this.financialYears.find((year) => year.id === selectedYearId);

    if (!selectedYear) {
      return new Date().toISOString().slice(0, 10);
    }

    const today = new Date().toISOString().slice(0, 10);

    return today >= selectedYear.start_date && today <= selectedYear.end_date
      ? today
      : selectedYear.end_date;
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
