import {
  ChangeDetectorRef,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  Injector,
  OnInit,
  effect,
  inject,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';

import {
  TaxIconComponent,
  type TaxIconName,
} from '../../../../shared/components/tax-icon/tax-icon.component';
import { refreshEvery } from '../../../../shared/utils/status-refresh';
import {
  Review,
  ReviewStatus,
  ReviewType,
} from '../../../../shared/models/reviews/review.model';

import {
  ReviewService,
  ReviewListResponse,
  ReviewListQuery,
} from '../../../../shared/services/reviews/review.service';
import { DocumentApiService } from '../../../../shared/services/documents/document-api.service';

@Component({
  selector: 'app-review-list',
  standalone: true,
  imports: [RouterLink, TaxIconComponent],
  templateUrl: './review-list.component.html',
  styleUrl: './review-list.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ReviewListComponent implements OnInit {
  private readonly reviewService =
    inject(ReviewService);
  private readonly documentsApi =
    inject(DocumentApiService);
  private readonly injector = inject(Injector);
  private readonly cdr = inject(ChangeDetectorRef);
  private readonly destroyRef = inject(DestroyRef);

  reviews: Review[] = [];

  currentPage = 1;
  lastPage = 1;
  total = 0;

  isLoading = false;
  errorMessage: string | null = null;
  refreshErrorMessage = '';

  selectedStatus: ReviewStatus | '' = '';
  selectedType: ReviewType | '' = '';

  readonly statusOptions: ReviewStatus[] = [
    'pending',
    'in_progress',
    'completed',
    'rejected',
    'superseded',
  ];

  readonly typeOptions: ReviewType[] = [
    'confidence',
    'classification',
    'deductibility',
    'manual',
  ];

  get financialYearLabel(): string {
    const financialYearId = this.documentsApi.selectedFinancialYearId();

    if (!financialYearId) {
      return 'All financial years';
    }

    return `Selected financial year`;
  }

  ngOnInit(): void {
    effect(() => {
      const financialYearId =
        this.documentsApi.selectedFinancialYearId();
      this.currentPage = 1;
      this.loadReviews(financialYearId);
    }, { injector: this.injector });

    refreshEvery(
      () => this.reviewService.listReviews(this.reviewQuery()),
      () => {
        this.refreshErrorMessage = 'Unable to refresh review statuses.';
        this.cdr.markForCheck();
      },
    ).pipe(takeUntilDestroyed(this.destroyRef)).subscribe((response) => {
      this.reviews = response.data;
      this.currentPage = response.currentPage;
      this.lastPage = response.lastPage;
      this.total = response.total;
      this.refreshErrorMessage = '';
      this.errorMessage = null;
      this.cdr.markForCheck();
    });
  }

  loadReviews(
    financialYearId = this.documentsApi.selectedFinancialYearId(),
  ): void {
    this.isLoading = true;
    this.errorMessage = null;

    const query = this.reviewQuery(financialYearId);

    this.reviewService
      .listReviews(query)
      .subscribe({
        next: (response: ReviewListResponse) => {
          this.reviews = response.data;
          this.currentPage = response.currentPage;
          this.lastPage = response.lastPage;
          this.total = response.total;
          this.isLoading = false;
          this.cdr.markForCheck();
        },
        error: () => {
          this.reviews = [];
          this.isLoading = false;
          this.errorMessage =
            'Failed to load reviews.';
          this.cdr.markForCheck();
        },
      });
  }

  private reviewQuery(
    financialYearId = this.documentsApi.selectedFinancialYearId(),
  ): ReviewListQuery {
    const query: ReviewListQuery = {};
    if (this.selectedStatus) {
      query.status = this.selectedStatus;
    }

    if (this.selectedType) {
      query.type = this.selectedType;
    }

    if (financialYearId) {
      query.financialYearId = financialYearId;
    }

    if (this.currentPage > 1) {
      query.page = this.currentPage;
    }

    return query;
  }

  onStatusChange(event: Event): void {
    const status = (event.target as HTMLSelectElement | null)?.value ?? '';
    this.selectedStatus = this.statusOptions.find(
      (option) => option === status,
    ) ?? '';

    this.currentPage = 1;
    this.loadReviews();
  }

  onTypeChange(event: Event): void {
    const type = (event.target as HTMLSelectElement | null)?.value ?? '';
    this.selectedType = this.typeOptions.find(
      (option) => option === type,
    ) ?? '';

    this.currentPage = 1;
    this.loadReviews();
  }

  clearFilters(): void {
    this.selectedStatus = '';
    this.selectedType = '';
    this.currentPage = 1;
    this.loadReviews();
  }

  statusLabel(status: ReviewStatus): string {
    const labels: Record<ReviewStatus, string> = {
      pending: 'Pending',
      in_progress: 'In progress',
      completed: 'Completed',
      rejected: 'Rejected',
      superseded: 'Superseded',
    };

    return labels[status];
  }

  typeLabel(type: ReviewType): string {
    const labels: Record<ReviewType, string> = {
      confidence: 'Data confidence',
      classification: 'Classification',
      deductibility: 'Deductibility',
      manual: 'Manual review',
    };

    return labels[type];
  }

  statusClass(status: ReviewStatus): string {
    return `status-${status.replace('_', '-')}`;
  }

  typeClass(type: ReviewType): string {
    return `type-${type}`;
  }

  typeIcon(type: ReviewType): TaxIconName {
    const icons: Record<ReviewType, TaxIconName> = {
      confidence: 'chart',
      classification: 'classification',
      deductibility: 'currency',
      manual: 'edit',
    };

    return icons[type];
  }

  fieldCount(fields: Record<string, unknown>): number {
    return Object.keys(fields).length;
  }

  lowConfidenceCount(review: Review): number {
    return Object.values(review.extraction?.fields ?? {})
      .filter((field) => field.confidence < 80)
      .length;
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

  goToPreviousPage(): void {
    if (this.currentPage <= 1) {
      return;
    }

    this.currentPage -= 1;
    this.loadReviews();
  }

  goToNextPage(): void {
    if (this.currentPage >= this.lastPage) {
      return;
    }

    this.currentPage += 1;
    this.loadReviews();
  }
}
