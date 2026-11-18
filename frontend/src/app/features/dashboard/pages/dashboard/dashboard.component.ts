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
import { Observable, of } from 'rxjs';

import { TaxIconComponent } from '../../../../shared/components/tax-icon/tax-icon.component';
import { CashFlowAnalysisComponent } from '../../../../shared/components/cash-flow-analysis/cash-flow-analysis.component';
import { WealthOverviewComponent } from '../../../../shared/components/wealth-overview/wealth-overview.component';
import { DeadlineCardComponent } from '../../../../shared/components/deadline-card/deadline-card.component';
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
  TaxReadinessDto,
  TaxReadinessIssueDto,
  TaxNewsDto,
  TransactionDto,
} from '../../../../shared/services/tax-workspace-api.service';
import { ReviewService } from '../../../../shared/services/reviews/review.service';
import { AuthService } from '../../../../shared/services/auth/auth.service';
import { DashboardWidgetLayout } from '../../../../shared/services/auth/auth.service';
import { UserPreferencesService } from '../../../../shared/services/user-preferences.service';
import { TranslatePipe } from '../../../../shared/i18n/translate.pipe';
import { TranslationService } from '../../../../shared/i18n/translation.service';
import { CollapsibleCardDirective } from '../../../../shared/directives/collapsible-card.directive';

interface MonthlyTransactionSummary {
  key: string;
  label: string;
  income: number;
  expenses: number;
  incomeHeight: number;
  expensesHeight: number;
}

interface DashboardWidgetDefinition {
  id: string;
  label: string;
  description: string;
  zone: 'main' | 'side';
}

@Component({
  selector: 'app-dashboard',
  imports: [RouterLink, TaxIconComponent, CashFlowAnalysisComponent, DeadlineCardComponent, WealthOverviewComponent, TranslatePipe, CollapsibleCardDirective],
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
  private readonly preferences = inject(UserPreferencesService);
  private readonly translations = inject(TranslationService);
  readonly auth = inject(AuthService);
  readonly widgetDefinitions: DashboardWidgetDefinition[] = [
    { id: 'wealth', label: 'Dashboard.WidgetWealth', description: 'Dashboard.WidgetWealthDescription', zone: 'main' },
    { id: 'financial-analysis', label: 'Dashboard.WidgetAnalysis', description: 'Dashboard.WidgetAnalysisDescription', zone: 'main' },
    { id: 'attention', label: 'Dashboard.WidgetAttention', description: 'Dashboard.WidgetAttentionDescription', zone: 'side' },
    { id: 'recent-documents', label: 'Dashboard.WidgetDocuments', description: 'Dashboard.WidgetDocumentsDescription', zone: 'side' },
    { id: 'shortcuts', label: 'Dashboard.WidgetShortcuts', description: 'Dashboard.WidgetShortcutsDescription', zone: 'side' },
    { id: 'tax-news', label: 'TaxNews.Title', description: 'TaxNews.MatchedUpdates', zone: 'side' },
  ];
  editWidgets = false;
  widgetLayout: DashboardWidgetLayout[] = this.defaultWidgetLayout();
  draggingWidgetId: string | null = null;
  private readonly refreshErrors = new Map<string, string>();

  documents: DocumentDto[] = [];
  transactions: TransactionDto[] = [];
  reviews: Review[] = [];
  taxReadiness: TaxReadinessDto | null = null;
  previousTaxReadiness: TaxReadinessDto | null = null;
  taxReadinessLoading = false;
  taxNews: TaxNewsDto[] = [];
  financialYears: FinancialYearDto[] = [];
  isLoading = true;
  errorMessage = '';
  financialAnalysisError = '';
  refreshErrorMessage = '';

  get selectedFinancialYear(): FinancialYearDto | null {
    const selectedYearId = this.documentsApi.selectedFinancialYearId();

    return this.financialYears.find((year) => year.id === selectedYearId) ?? null;
  }

  toggleWidgetEditor(): void {
    this.editWidgets = !this.editWidgets;
  }

  isWidgetVisible(id: string): boolean {
    return this.widgetLayout.find((item) => item.id === id)?.visible ?? true;
  }

  widgetOrder(id: string): number {
    return this.widgetLayout.find((item) => item.id === id)?.order ?? 0;
  }

  toggleWidget(id: string): void {
    this.widgetLayout = this.widgetLayout.map((item) => item.id === id ? { ...item, visible: !item.visible } : item);
    this.saveWidgetLayout();
  }

  moveWidget(id: string, direction: -1 | 1): void {
    const item = this.widgetLayout.find((candidate) => candidate.id === id);
    if (!item) return;

    const sameZone = this.widgetLayout
      .filter((candidate) => this.definition(candidate.id)?.zone === this.definition(id)?.zone)
      .sort((left, right) => left.order - right.order);
    const index = sameZone.findIndex((candidate) => candidate.id === id);
    const target = sameZone[index + direction];
    if (!target) return;

    const order = item.order;
    item.order = target.order;
    target.order = order;
    this.widgetLayout = [...this.widgetLayout];
    this.saveWidgetLayout();
  }

  startWidgetDrag(id: string, event: DragEvent): void {
    if (!this.editWidgets) return;
    this.draggingWidgetId = id;
    event.dataTransfer?.setData('text/plain', id);
    if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
  }

  dropWidget(id: string, event: DragEvent): void {
    event.preventDefault();
    const sourceId = this.draggingWidgetId || event.dataTransfer?.getData('text/plain');
    this.draggingWidgetId = null;
    if (!sourceId || sourceId === id) return;

    const source = this.widgetLayout.find((item) => item.id === sourceId);
    const target = this.widgetLayout.find((item) => item.id === id);
    if (!source || !target || this.definition(sourceId)?.zone !== this.definition(id)?.zone) return;

    const sourceOrder = source.order;
    source.order = target.order;
    target.order = sourceOrder;
    this.widgetLayout = [...this.widgetLayout];
    this.saveWidgetLayout();
  }

  endWidgetDrag(): void {
    this.draggingWidgetId = null;
  }

  resetWidgetLayout(): void {
    this.widgetLayout = this.defaultWidgetLayout();
    this.saveWidgetLayout();
  }

  widgetLabel(id: string): string {
    const label = this.definition(id)?.label ?? id;
    return label.includes('.') ? this.translations.translate(label) : label;
  }

  private definition(id: string): DashboardWidgetDefinition | undefined {
    return this.widgetDefinitions.find((widget) => widget.id === id);
  }

  private defaultWidgetLayout(): DashboardWidgetLayout[] {
    return this.widgetDefinitions.map((widget, index) => ({ id: widget.id, visible: true, order: index }));
  }

  private saveWidgetLayout(): void {
    this.preferences.update({ dashboardLayout: this.widgetLayout });
    this.cdr.markForCheck();
  }

  private loadWidgetLayout(): void {
    const saved = this.preferences.preferences().dashboardLayout;
    if (saved.length === 0) return;

    const known = this.defaultWidgetLayout().map((item) => {
      const stored = saved.find((candidate) => candidate.id === item.id);
      return stored ? { ...item, ...stored } : item;
    });
    this.widgetLayout = known;
  }

  private filteredCache: { source: TransactionDto[]; yearId: string | null; result: TransactionDto[] } | null = null;

  get filteredTransactions(): TransactionDto[] {
    const yearId = this.documentsApi.selectedFinancialYearId();

    if (this.filteredCache?.source === this.transactions && this.filteredCache.yearId === yearId) {
      return this.filteredCache.result;
    }

    const result = yearId
      ? this.transactions.filter((transaction) => transaction.financial_year_id === yearId)
      : [];

    this.filteredCache = { source: this.transactions, yearId, result };

    return result;
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

    const start = new Date(`${year.start_date.slice(0, 10)}T00:00:00`);
    const end = new Date(`${year.end_date.slice(0, 10)}T00:00:00`);
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
    return this.translations.translate('Dashboard.ReviewChartDescription')
      .replace('{completed}', String(this.completedReviews))
      .replace('{attention}', String(this.openReviews.length))
      .replace('{other}', String(this.otherReviews));
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
      return this.translations.translate('Common.AllFinancialYears');
    }

    const year = this.financialYears.find(
      (item) => item.id === selectedYearId,
    );

    return year ? `FY ${formatFinancialYear(year.year_code)}` : this.translations.translate('TaxWorkspace.Title');
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

  get readinessComponents(): Array<{ key: keyof TaxReadinessDto['components']; label: string; value: TaxReadinessDto['components'][keyof TaxReadinessDto['components']] }> {
    if (!this.taxReadiness) return [];

    return [
      { key: 'documents', label: 'Dashboard.ReadinessDocuments', value: this.taxReadiness.components.documents },
      { key: 'transactions', label: 'Dashboard.ReadinessTransactions', value: this.taxReadiness.components.transactions },
      { key: 'evidence', label: 'Dashboard.ReadinessEvidence', value: this.taxReadiness.components.evidence },
      { key: 'required_data', label: 'Dashboard.ReadinessRequiredData', value: this.taxReadiness.components.required_data },
    ];
  }

  get readinessDelta(): number | null {
    if (this.taxReadiness?.score === null || this.taxReadiness?.score === undefined || this.previousTaxReadiness?.score === null || this.previousTaxReadiness?.score === undefined) return null;
    return this.taxReadiness.score - this.previousTaxReadiness.score;
  }

  readinessIssueMessage(issue: TaxReadinessIssueDto): string {
    const key = `Dashboard.ReadinessIssues.${issue.code}`;
    const translated = this.translations.translate(key);

    return translated === key ? issue.message : translated;
  }

  get recentDocuments(): DocumentDto[] {
    return this.filteredDocuments.slice(0, 5);
  }

  ngOnInit(): void {
    this.loadWidgetLayout();
    this.documentsApi.listDocuments().subscribe({
      next: (documents) => {
        this.documents = documents;
        this.isLoading = false;
        this.cdr.markForCheck();
      },
      error: () => {
        this.errorMessage = this.translations.translate('Dashboard.LoadFailed');
        this.isLoading = false;
        this.cdr.markForCheck();
      },
    });

    this.documentsApi.listFinancialYears().subscribe({
      next: (years) => {
        this.financialYears = years;
        this.loadTaxReadiness();
        this.cdr.markForCheck();
      },
      error: () => {
        this.errorMessage = this.translations.translate('Dashboard.FinancialYearLoadFailed');
        this.cdr.markForCheck();
      },
    });

    this.reviewService.listReviews().subscribe({
      next: (response) => {
        this.reviews = response.data;
        this.cdr.markForCheck();
      },
      error: () => {
        this.errorMessage = this.translations.translate('Dashboard.ReviewLoadFailed');
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
        this.financialAnalysisError = this.translations.translate('Dashboard.AnalysisLoadFailed');
        this.cdr.markForCheck();
      },
    });

    this.workspaceApi.listTaxNews('all', true, true).subscribe({
      next: (news) => {
        this.taxNews = news;
        this.cdr.markForCheck();
      },
    });

    refreshEvery(
      () => this.documentsApi.listDocuments(),
      () => this.setRefreshError(
        'documents',
        this.translations.translate('Dashboard.DocumentRefreshFailed'),
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
        this.translations.translate('Dashboard.ReviewRefreshFailed'),
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
        this.translations.translate('Dashboard.AnalysisRefreshFailed'),
      ),
    ).pipe(takeUntilDestroyed(this.destroyRef)).subscribe((transactions) => {
      this.transactions = transactions;
      this.financialAnalysisError = '';
      this.clearRefreshError('transactions');
      this.cdr.markForCheck();
    });

    refreshEvery(
      () => this.taxReadinessRequest(),
      () => this.setRefreshError(
        'readiness',
        this.translations.translate('Dashboard.ReadinessRefreshFailed'),
      ),
    ).pipe(takeUntilDestroyed(this.destroyRef)).subscribe((readiness) => {
      if (readiness) {
        this.taxReadiness = readiness;
        this.clearRefreshError('readiness');
        this.cdr.markForCheck();
      }
    });
  }

  private loadTaxReadiness(): void {
    const yearId = this.documentsApi.selectedFinancialYearId();
    if (!yearId) {
      this.taxReadiness = null;
      this.previousTaxReadiness = null;
      return;
    }

    this.taxReadinessLoading = true;
    const currentYear = this.financialYears.find((year) => year.id === yearId);
    const previousYear = currentYear
      ? this.financialYears
        .filter((year) => year.end_date < currentYear.end_date)
        .sort((a, b) => b.end_date.localeCompare(a.end_date))[0]
      : undefined;

    this.workspaceApi.getTaxReadiness(yearId).subscribe({
      next: (current) => {
        this.taxReadiness = current;
        this.taxReadinessLoading = false;
        this.cdr.markForCheck();
      },
      error: () => {
        this.taxReadiness = null;
        this.taxReadinessLoading = false;
        this.setRefreshError('readiness', this.translations.translate('Dashboard.ReadinessLoadFailed'));
      },
    });

    if (previousYear) {
      this.workspaceApi.getTaxReadiness(previousYear.id).subscribe({
        next: (previous) => {
          this.previousTaxReadiness = previous;
          this.cdr.markForCheck();
        },
      });
    } else {
      this.previousTaxReadiness = null;
    }
  }

  private taxReadinessRequest(): Observable<TaxReadinessDto | null> {
    const yearId = this.documentsApi.selectedFinancialYearId();

    return yearId ? this.workspaceApi.getTaxReadiness(yearId) : of(null);
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
      return this.translations.translate('Common.DateUnavailable');
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
