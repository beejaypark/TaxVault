import {
  DestroyRef,
  ChangeDetectorRef,
  Component,
  OnInit,
  inject,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { DecimalPipe } from '@angular/common';
import { ActivatedRoute } from '@angular/router';
import { takeWhile } from 'rxjs';

import { TaxIconComponent } from '../../../../shared/components/tax-icon/tax-icon.component';
import { BackNavigationComponent } from '../../../../shared/components/back-navigation/back-navigation.component';
import { refreshEvery } from '../../../../shared/utils/status-refresh';
import {
  Review,
  ReviewDecision,
} from '../../../../shared/models/reviews/review.model';

import {
  ReviewCorrectionEvidence,
  ReviewCorrectionRequest,
  ReviewService,
} from '../../../../shared/services/reviews/review.service';

@Component({
  imports: [DecimalPipe, FormsModule, TaxIconComponent, BackNavigationComponent],
  selector: 'app-review-detail',
  styleUrl: './review-detail.component.scss',
  templateUrl: './review-detail.component.html',
})
export class ReviewDetailComponent
  implements OnInit
{
  private readonly route =
    inject(ActivatedRoute);

  private readonly reviewService =
    inject(ReviewService);
  private readonly cdr = inject(ChangeDetectorRef);
  private readonly destroyRef = inject(DestroyRef);

  readonly Object = Object;
  private statusRefreshStarted = false;

  review: Review | null = null;
  isLoading = true;
  loadError: string | null = null;
  refreshError: string | null = null;

  isSubmitting = false;
  decisionError: string | null = null;
  decisionSuccess = false;

  correctionValues: Record<string, unknown> = {};
  correctionEvidenceByField: Record<
    string,
    ReviewCorrectionEvidence
  > = {};
  correctionSubmittingField: string | null = null;
  correctionError: string | null = null;
  correctionSuccessField: string | null = null;
  isEditingCompletedReview = false;
  recordExpense = false;
  expenseDraft: {
    transaction_date: string;
    direction: 'income' | 'expense';
    amount: number | null;
    description: string;
  } = this.emptyExpenseDraft();

  ngOnInit(): void {
    const id =
      this.route.snapshot.paramMap.get(
        'id',
      );

    if (!id) {
      this.isLoading = false;
      this.loadError = 'Review identifier is missing.';
      return;
    }

    this.reviewService
      .getReview(id)
      .subscribe({
        next: (review) => {
          this.review = review;
          this.initializeCorrectionValues();
          this.isLoading = false;
          this.cdr.markForCheck();
          this.startStatusRefresh(id, review);
        },
        error: () => {
          this.isLoading = false;
          this.loadError = 'Unable to load this review. Please try again.';
          this.cdr.markForCheck();
        },
      });
  }

  printReview(): void {
    window.print();
  }

  retryLoad(): void {
    const id = this.route.snapshot.paramMap.get('id');

    if (!id) {
      return;
    }

    this.isLoading = true;
    this.loadError = null;
    this.reviewService.getReview(id).subscribe({
      next: (review) => {
        this.review = review;
        this.initializeCorrectionValues();
        this.isLoading = false;
        this.cdr.markForCheck();
        this.startStatusRefresh(id, review);
      },
      error: () => {
        this.isLoading = false;
        this.loadError = 'Unable to load this review. Please try again.';
        this.cdr.markForCheck();
      },
    });
  }

  private startStatusRefresh(id: string, review: Review): void {
    if (
      this.statusRefreshStarted
      || (review.status !== 'pending' && review.status !== 'in_progress')
    ) {
      return;
    }

    this.statusRefreshStarted = true;
    refreshEvery(
      () => this.reviewService.getReview(id),
      () => {
        this.refreshError = 'Unable to refresh this review status.';
        this.cdr.markForCheck();
      },
    ).pipe(
      takeWhile(
        (updatedReview) =>
          updatedReview.status === 'pending'
          || updatedReview.status === 'in_progress',
        true,
      ),
      takeUntilDestroyed(this.destroyRef),
    ).subscribe((updatedReview) => {
      this.review = updatedReview;
      this.refreshError = null;
      this.cdr.markForCheck();
    });
  }

  statusLabel(status: Review['status']): string {
    const labels: Record<Review['status'], string> = {
      pending: 'Pending review',
      in_progress: 'In progress',
      completed: 'Completed',
      rejected: 'Rejected',
      superseded: 'Superseded',
    };

    return labels[status];
  }

  typeLabel(type: Review['type']): string {
    const labels: Record<Review['type'], string> = {
      confidence: 'Data confidence',
      classification: 'Classification',
      deductibility: 'Deductibility',
      manual: 'Manual review',
    };

    return labels[type];
  }

  formatDate(value: string | null | undefined): string {
    if (!value) {
      return 'Not available';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
      ? 'Not available'
      : new Intl.DateTimeFormat('en-AU', {
          day: 'numeric',
          month: 'long',
          year: 'numeric',
          hour: 'numeric',
          minute: '2-digit',
        }).format(date);
  }

  confidenceClass(confidence: number): string {
    if (confidence < 80) {
      return 'confidence-low';
    }

    if (confidence < 95) {
      return 'confidence-medium';
    }

    return 'confidence-high';
  }

  get isBrisbaneCityCouncilDocument(): boolean {
    return Object.values(this.review?.extraction?.fields ?? {}).some(
      (field) => field.value === 'Brisbane City Council',
    );
  }

  get canEditCorrections(): boolean {
    return this.review?.status === 'pending'
      || (
        this.review?.status === 'completed'
        && this.isEditingCompletedReview
      );
  }

  toggleCorrectionEditing(): void {
    if (
      this.review?.status !== 'completed'
      || this.correctionSubmittingField !== null
    ) {
      return;
    }

    this.isEditingCompletedReview = !this.isEditingCompletedReview;
  }

  submitDecision(
    decision: ReviewDecision,
    notes?: string,
  ): void {
    const id =
      this.review?.id;

    if (
      !id ||
      this.isSubmitting
    ) {
      return;
    }

    if (
      decision === 'approved'
      && this.recordExpense
      && (
        !this.expenseDraft.transaction_date
        || !this.expenseDraft.amount
        || this.expenseDraft.amount <= 0
        || !this.expenseDraft.description.trim()
      )
    ) {
      this.decisionError = 'Enter a transaction date, positive amount, and description before saving this expense.';
      return;
    }

    const request:
      import('../../../../shared/services/reviews/review.service')
        .ReviewDecisionRequest = {
      decision,
      notes,
      ...(decision === 'approved' && this.recordExpense
        ? {
            expense: {
              transaction_date: this.expenseDraft.transaction_date,
              direction: this.expenseDraft.direction,
              amount: this.expenseDraft.amount ?? 0,
              description: this.expenseDraft.description.trim(),
            },
          }
        : {}),
    };

    this.isSubmitting = true;
    this.decisionError = null;
    this.decisionSuccess = false;

    this.reviewService
      .submitDecision(
        id,
        request,
      )
      .subscribe({
        next: (review) => {
          this.review = review;
          this.isSubmitting = false;
          this.decisionSuccess = true;
          this.cdr.markForCheck();
        },

        error: () => {
          this.isSubmitting = false;
          this.decisionError =
            'Failed to submit review decision.';
        },
      });
  }

  setCorrectionValue(
    fieldPath: string,
    value: unknown,
  ): void {
    this.correctionValues[fieldPath] =
      value;
  }

  submitCorrection(
    fieldPath: string,
  ): void {
    const id =
      this.review?.id;

    if (
      !id ||
      !this.review ||
      !this.canEditCorrections ||
      this.correctionSubmittingField !== null
    ) {
      return;
    }

    const request:
      ReviewCorrectionRequest = {
      fieldPath,
      value:
        this.correctionValues[fieldPath],
    };

    this.correctionSubmittingField =
      fieldPath;
    this.correctionError = null;
    this.correctionSuccessField = null;

    this.reviewService
      .submitCorrection(
        id,
        request,
      )
      .subscribe({
        next: (response) => {
          this.review =
            response.review;

          this.correctionEvidenceByField[fieldPath] =
            response.evidence;

          this.correctionValues[fieldPath] =
            response.evidence.extractedValue.value;

          this.correctionSubmittingField =
            null;
          this.correctionSuccessField =
            fieldPath;
        },

        error: () => {
          this.correctionSubmittingField =
            null;
          this.correctionError =
            'Failed to submit review correction.';
        },
      });
  }

  isCorrectionSubmitting(
    fieldPath: string,
  ): boolean {
    return (
      this.correctionSubmittingField ===
      fieldPath
    );
  }

  getCorrectionEvidence(
    fieldPath: string,
  ): ReviewCorrectionEvidence | null {
    return (
      this.correctionEvidenceByField[fieldPath] ??
      null
    );
  }

  private initializeCorrectionValues(): void {
    this.correctionValues = {};
    this.correctionEvidenceByField = {};

    const fields = this.review?.extraction?.fields ?? {};

    for (const [
      fieldPath,
      field,
    ] of Object.entries(fields)) {
      this.correctionValues[fieldPath] =
        field.value;
    }

    for (const correction of this.review
      ?.corrections ?? []) {
      this.correctionEvidenceByField[
        correction.fieldPath
      ] = correction;

      this.correctionValues[
        correction.fieldPath
      ] =
        correction.extractedValue.value;
    }

    const supplier = Object.values(fields).find(
      (field) => field.value === 'Brisbane City Council',
    )?.value;
    const extractedAmount =
      fields['total_amount']?.value
      ?? fields['transaction.total']?.value
      ?? this.amountCandidateFromText(fields['document_text']?.value);
    const invoiceDate =
      fields['invoice_date']?.value
      ?? fields['transaction.date']?.value;
    const filename = this.review?.document?.originalFilename ?? 'Tax document';
    const quarter = filename.match(/\bq([1-4])\b/i)?.[1];

    this.expenseDraft = {
      transaction_date: typeof invoiceDate === 'string' ? invoiceDate : '',
      direction: 'expense',
      amount: typeof extractedAmount === 'string' || typeof extractedAmount === 'number'
        ? Number(extractedAmount)
        : null,
      description: supplier
        ? `Brisbane City Council rates${quarter ? ` - Q${quarter}` : ''}`
        : filename.replace(/\.[^.]+$/, ''),
    };
    this.recordExpense = false;
  }

  private amountCandidateFromText(value: unknown): number | null {
    if (typeof value !== 'string') {
      return null;
    }

    const match = /(?:net(?:t)?\s+amount\s+payable|total\s+(?:amount\s+)?due|amount\s+due|pay\s+this\s+amount|total\s+payable|balance\s+due)\s*:?\s*\$?\s*(\d{1,3}(?:,\d{3})*\.\d{2}|\d+\.\d{2})/i.exec(value);

    return match ? Number(match[1].replace(/,/g, '')) : null;
  }

  private emptyExpenseDraft(): {
    transaction_date: string;
    direction: 'income' | 'expense';
    amount: number | null;
    description: string;
  } {
    return {
      transaction_date: '',
      direction: 'expense',
      amount: null,
      description: '',
    };
  }
}
