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
import { ReviewService } from '../../../../shared/services/reviews/review.service';

@Component({
  selector: 'app-dashboard',
  imports: [RouterLink, TaxIconComponent],
  templateUrl: './dashboard.component.html',
  styleUrl: './dashboard.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DashboardComponent implements OnInit {
  private readonly documentsApi = inject(DocumentApiService);
  private readonly reviewService = inject(ReviewService);
  private readonly cdr = inject(ChangeDetectorRef);
  private readonly destroyRef = inject(DestroyRef);
  private readonly refreshErrors = new Map<string, string>();

  documents: DocumentDto[] = [];
  reviews: Review[] = [];
  financialYears: FinancialYearDto[] = [];
  isLoading = true;
  errorMessage = '';
  refreshErrorMessage = '';

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
}
