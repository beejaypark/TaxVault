import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  DestroyRef,
  OnInit,
  inject,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';

import { TaxIconComponent } from '../../../../shared/components/tax-icon/tax-icon.component';
import { formatFinancialYear } from '../../../../shared/utils/financial-year';
import { refreshEvery } from '../../../../shared/utils/status-refresh';
import { Review } from '../../../../shared/models/reviews/review.model';
import {
  DocumentApiService,
  DocumentDto,
  FinancialYearDto,
} from '../../../../shared/services/documents/document-api.service';
import {
  TaxWorkspaceApiService,
  TransactionDto,
} from '../../../../shared/services/tax-workspace-api.service';
import { ReviewService } from '../../../../shared/services/reviews/review.service';

interface MonthlyTransactionSummary {
  key: string;
  label: string;
  income: number;
  expenses: number;
  incomeHeight: number;
  expensesHeight: number;
}

@Component({
  selector: 'app-dashboard',
  imports: [RouterLink, TaxIconComponent],
  templateUrl: './dashboard.component.html',
  styleUrl: './dashboard.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DashboardComponent implements OnInit {
  private readonly documentsApi = inject(DocumentApiService);
  private readonly workspaceApi = inject(TaxWorkspaceApiService);
  private readonly reviewService = inject(ReviewService);
  private readonly cdr = inject(ChangeDetectorRef);
  private readonly destroyRef = inject(DestroyRef);
  private readonly refreshErrors = new Map<string, string>();

  documents: DocumentDto[] = [];
  transactions: TransactionDto[] = [];
  reviews: Review[] = [];
  financialYears: FinancialYearDto[] = [];
  isLoading = true;
  errorMessage = '';
  financialAnalysisError = '';
  refreshErrorMessage = '';

  get selectedFinancialYear(): FinancialYearDto | null {
    const selectedYearId = this.documentsApi.selectedFinancialYearId();

    return this.financialYears.find((year) => year.id === selectedYearId) ?? null;
  }

  get filteredTransactions(): TransactionDto[] {
    const yearId = this.documentsApi.selectedFinancialYearId();

    return yearId
      ? this.transactions.filter((transaction) => transaction.financial_year_id === yearId)
      : [];
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

  get netAmount(): number {
    return this.totalIncome - this.totalExpenses;
  }

  get monthlyTransactions(): MonthlyTransactionSummary[] {
    const year = this.selectedFinancialYear;

    if (!year) {
      return [];
    }

    const start = new Date(`${year.start_date}T00:00:00`);
    const end = new Date(`${year.end_date}T00:00:00`);
    const months = new Map<string, MonthlyTransactionSummary>();

    for (
      let month = new Date(start.getFullYear(), start.getMonth(), 1);
      month <= end;
      month = new Date(month.getFullYear(), month.getMonth() + 1, 1)
    ) {
      const key = [
        month.getFullYear(),
        String(month.getMonth() + 1).padStart(2, '0'),
      ].join('-');

      months.set(key, {
        key,
        label: new Intl.DateTimeFormat('en-AU', { month: 'short' }).format(month),
        income: 0,
        expenses: 0,
        incomeHeight: 0,
        expensesHeight: 0,
      });
    }

    for (const transaction of this.filteredTransactions) {
      const month = months.get(transaction.transaction_date.slice(0, 7));

      if (month) {
        if (transaction.direction === 'income') {
          month.income += Number(transaction.amount);
        } else {
          month.expenses += Number(transaction.amount);
        }
      }
    }

    const summaries = [...months.values()];
    const maximum = Math.max(
      ...summaries.flatMap((month) => [month.income, month.expenses]),
      0,
    );

    return summaries.map((month) => ({
      ...month,
      incomeHeight: maximum > 0 ? (month.income / maximum) * 100 : 0,
      expensesHeight: maximum > 0 ? (month.expenses / maximum) * 100 : 0,
    }));
  }

  get reviewChartDescription(): string {
    return `${this.completedReviews} completed, ${this.openReviews.length} needing attention, ${this.otherReviews} other reviews`;
  }

  get otherReviews(): number {
    return this.filteredReviews.filter(
      (review) => review.status !== 'completed'
        && review.status !== 'pending'
        && review.status !== 'in_progress',
    ).length;
  }

  get completedReviewWidth(): number {
    return this.reviewSegmentWidth(this.completedReviews);
  }

  get openReviewWidth(): number {
    return this.reviewSegmentWidth(this.openReviews.length);
  }

  get otherReviewWidth(): number {
    return this.reviewSegmentWidth(this.otherReviews);
  }

  get financialYear(): string {
    const selectedYearId = this.documentsApi.selectedFinancialYearId();

    if (!selectedYearId) {
      return 'All financial years';
    }

    const year = this.financialYears.find(
      (item) => item.id === selectedYearId,
    );

    return year ? `FY ${formatFinancialYear(year.year_code)}` : 'Your tax workspace';
  }

  get filteredDocuments(): DocumentDto[] {
    const selectedYearId = this.documentsApi.selectedFinancialYearId();

    return selectedYearId
      ? this.documents.filter(
          (document) => document.financial_year_id === selectedYearId,
        )
      : this.documents;
  }

  get filteredReviews(): Review[] {
    const selectedYearId = this.documentsApi.selectedFinancialYearId();

    return selectedYearId
      ? this.reviews.filter(
          (review) => review.financialYearId === selectedYearId,
        )
      : this.reviews;
  }

  get openReviews(): Review[] {
    return this.filteredReviews.filter(
      (review) => review.status === 'pending' || review.status === 'in_progress',
    );
  }

  get completedReviews(): number {
    return this.filteredReviews.filter((review) => review.status === 'completed').length;
  }

  get recentDocuments(): DocumentDto[] {
    return this.filteredDocuments.slice(0, 5);
  }

  ngOnInit(): void {
    this.documentsApi.listDocuments().subscribe({
      next: (documents) => {
        this.documents = documents;
        this.isLoading = false;
        this.cdr.markForCheck();
      },
      error: () => {
        this.errorMessage = 'Unable to load your dashboard. Please try again.';
        this.isLoading = false;
        this.cdr.markForCheck();
      },
    });

    this.documentsApi.listFinancialYears().subscribe({
      next: (years) => {
        this.financialYears = years;
        this.cdr.markForCheck();
      },
      error: () => {
        this.errorMessage = 'Unable to load your financial year. Please try again.';
        this.cdr.markForCheck();
      },
    });

    this.reviewService.listReviews().subscribe({
      next: (response) => {
        this.reviews = response.data;
        this.cdr.markForCheck();
      },
      error: () => {
        this.errorMessage = 'Unable to load your review status. Please try again.';
        this.cdr.markForCheck();
      },
    });

    this.workspaceApi.listTransactions().subscribe({
      next: (transactions) => {
        this.transactions = transactions;
        this.financialAnalysisError = '';
        this.cdr.markForCheck();
      },
      error: () => {
        this.financialAnalysisError = 'Unable to load financial analysis for this year.';
        this.cdr.markForCheck();
      },
    });

    refreshEvery(
      () => this.documentsApi.listDocuments(),
      () => this.setRefreshError(
        'documents',
        'Unable to refresh document statuses.',
      ),
    ).pipe(takeUntilDestroyed(this.destroyRef)).subscribe((documents) => {
      this.documents = documents;
      this.clearRefreshError('documents');
      this.cdr.markForCheck();
    });

    refreshEvery(
      () => this.reviewService.listReviews(),
      () => this.setRefreshError(
        'reviews',
        'Unable to refresh review statuses.',
      ),
    ).pipe(takeUntilDestroyed(this.destroyRef)).subscribe((response) => {
      this.reviews = response.data;
      this.clearRefreshError('reviews');
      this.cdr.markForCheck();
    });

    refreshEvery(
      () => this.workspaceApi.listTransactions(),
      () => this.setRefreshError(
        'transactions',
        'Unable to refresh your financial analysis.',
      ),
    ).pipe(takeUntilDestroyed(this.destroyRef)).subscribe((transactions) => {
      this.transactions = transactions;
      this.financialAnalysisError = '';
      this.clearRefreshError('transactions');
      this.cdr.markForCheck();
    });
  }

  private setRefreshError(resource: string, message: string): void {
    this.refreshErrors.set(resource, message);
    this.refreshErrorMessage = [...this.refreshErrors.values()].join(' ');
    this.cdr.markForCheck();
  }

  private clearRefreshError(resource: string): void {
    this.refreshErrors.delete(resource);
    this.refreshErrorMessage = [...this.refreshErrors.values()].join(' ');
  }

  formatDate(value: string): string {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
      return 'Date unavailable';
    }

    return new Intl.DateTimeFormat('en-AU', {
      day: 'numeric',
      month: 'short',
    }).format(date);
  }

  formatFileSize(bytes: number): string {
    if (bytes < 1024) {
      return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
      return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  }

  formatCurrency(value: number): string {
    return new Intl.NumberFormat('en-AU', {
      style: 'currency',
      currency: 'AUD',
    }).format(value);
  }

  private reviewSegmentWidth(count: number): number {
    const total = this.filteredReviews.length;

    return total > 0 ? (count / total) * 100 : 0;
  }
}
