import {
  Component,
  OnInit,
  inject,
} from '@angular/core';

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

@Component({
  selector: 'app-review-list',
  standalone: true,
  imports: [],
  templateUrl: './review-list.component.html',
  styleUrl: './review-list.component.scss',
})
export class ReviewListComponent implements OnInit {
  private readonly reviewService =
    inject(ReviewService);

  reviews: Review[] = [];

  currentPage = 1;
  lastPage = 1;
  total = 0;

  isLoading = false;
  errorMessage: string | null = null;

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

  ngOnInit(): void {
    this.loadReviews();
  }

  loadReviews(): void {
    this.isLoading = true;
    this.errorMessage = null;

    const query: ReviewListQuery = {};

    if (this.selectedStatus) {
      query.status = this.selectedStatus;
    }

    if (this.selectedType) {
      query.type = this.selectedType;
    }

    if (this.currentPage > 1) {
      query.page = this.currentPage;
    }

    this.reviewService
      .listReviews(query)
      .subscribe({
        next: (response: ReviewListResponse) => {
          this.reviews = response.data;
          this.currentPage = response.currentPage;
          this.lastPage = response.lastPage;
          this.total = response.total;
          this.isLoading = false;
        },
        error: () => {
          this.reviews = [];
          this.isLoading = false;
          this.errorMessage =
            'Failed to load reviews.';
        },
      });
  }

  onStatusChange(status: string): void {
    this.selectedStatus =
      status as ReviewStatus | '';

    this.currentPage = 1;
    this.loadReviews();
  }

  onTypeChange(type: string): void {
    this.selectedType =
      type as ReviewType | '';

    this.currentPage = 1;
    this.loadReviews();
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

