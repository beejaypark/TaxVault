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
import { CollapsibleCardDirective } from '../../../../shared/directives/collapsible-card.directive';
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

import { AuthService } from '../../../../shared/services/auth/auth.service';
import { documentDisplayName } from '../../../../shared/utils/document-display-name';
import { TaxWorkspaceApiService } from '../../../../shared/services/tax-workspace-api.service';
import { ReviewDocumentTransaction } from '../../../../shared/models/reviews/review-extraction.model';
import { TranslationService } from '../../../../shared/i18n/translation.service';

@Component({
  imports: [DecimalPipe, FormsModule, TaxIconComponent, BackNavigationComponent, CollapsibleCardDirective],
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
  readonly auth = inject(AuthService);
  private readonly i18n = inject(TranslationService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly workspaceApi = inject(TaxWorkspaceApiService);

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
  correctionValidationErrors: Record<string, string | null> = {};
  correctionEvidenceByField: Record<
    string,
    ReviewCorrectionEvidence
  > = {};
  correctionSubmittingField: string | null = null;
  correctionError: string | null = null;
  correctionSuccessField: string | null = null;
  isEditingCompletedReview = false;
  transactionSavingId: string | null = null;
  transactionEditError: string | null = null;
  recordExpense = false;
  transactionDrafts: {
    transaction_date: string;
    direction: 'income' | 'expense';
    amount: number | null;
    description: string;
  }[] = [this.emptyExpenseDraft()];

  // Kept as a compatibility alias for existing callers and tests.
  get expenseDraft() {
    return this.transactionDrafts[0];
  }

  set expenseDraft(value: {
    transaction_date: string;
    direction: 'income' | 'expense';
    amount: number | null;
    description: string;
  }) {
    this.transactionDrafts[0] = value;
  }

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

  readonly manualFields = [
    { path: 'total_amount', alias: 'transaction.total', label: 'Total amount (AUD)', type: 'number' },
    { path: 'invoice_date', alias: 'transaction.date', label: 'Invoice / issue date', type: 'date' },
  ];

  /** Amount and date can be typed in when OCR did not find them. */
  get missingManualFields() {
    const fields = this.review?.extraction?.fields ?? {};

    return this.manualFields.filter((f) => !(f.path in fields) && !(f.alias in fields));
  }

  get canEditCorrections(): boolean {
    if (!this.auth.canEdit) {
      return false;
    }

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
    this.transactionEditError = null;
  }

  updateReviewedTransaction(transaction: ReviewDocumentTransaction): void {
    if (this.transactionSavingId !== null) {
      return;
    }

    this.transactionSavingId = transaction.id;
    this.transactionEditError = null;
    this.workspaceApi.updateTransaction(transaction.id, {
      transaction_date: transaction.transactionDate.slice(0, 10),
      direction: transaction.direction,
      amount: Number(transaction.amount),
      description: transaction.description.trim(),
      currency: transaction.currency || 'AUD',
      ato_category: transaction.atoCategory ?? null,
      property_id: transaction.propertyId ?? null,
    }).subscribe({
      next: () => {
        this.transactionSavingId = null;
        this.cdr.markForCheck();
        this.reviewService.getReview(this.review?.id ?? '').subscribe((review) => {
          this.review = review;
          this.cdr.markForCheck();
        });
      },
      error: () => {
        this.transactionSavingId = null;
        this.transactionEditError = 'Unable to update this transaction.';
        this.cdr.markForCheck();
      },
    });
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

    if (decision === 'approved' && this.recordExpense && this.transactionDrafts.some((draft) =>
      !draft.transaction_date
      || !draft.amount
      || draft.amount <= 0
      || !draft.description.trim()
    )) {
      this.decisionError = 'Enter a date, positive amount, and description for every transaction before approving.';
      return;
    }

    if (decision === 'approved' && this.recordExpense && this.hasInvalidTransactionDates()) {
      this.decisionError = this.i18n.translate('Common.ValidationFailedDate');
      return;
    }

    const request:
      import('../../../../shared/services/reviews/review.service')
        .ReviewDecisionRequest = {
      decision,
      notes,
      ...(decision === 'approved' && this.recordExpense
        ? this.transactionDrafts.length === 1
          ? {
            expense: {
              transaction_date: this.transactionDrafts[0].transaction_date,
              direction: this.transactionDrafts[0].direction,
              amount: this.transactionDrafts[0].amount ?? 0,
              description: this.transactionDrafts[0].description.trim(),
            },
          }
          : {
            transactions: this.transactionDrafts.map((draft) => ({
              transaction_date: draft.transaction_date,
              direction: draft.direction,
              amount: draft.amount ?? 0,
              description: draft.description.trim(),
            })),
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
    this.correctionValidationErrors[fieldPath] =
      this.correctionValidationMessage(fieldPath, value);
    this.correctionSuccessField = null;
  }

  correctionValidationMessage(
    fieldPath: string,
    value: unknown,
  ): string | null {
    if (!this.isDateField(fieldPath)) {
      return null;
    }

    if (
      typeof value !== 'string' ||
      value.trim() === ''
    ) {
      return this.i18n.translate('Common.Required');
    }

    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
      return this.i18n.translate('Common.ValidationFailedDateFormat');
    }

    const year = Number(value.slice(0, 4));
    const date = new Date(`${value}T00:00:00Z`);

    if (
      year < 1900 ||
      year > 2100 ||
      Number.isNaN(date.getTime()) ||
      date.toISOString().slice(0, 10) !== value
    ) {
      return this.i18n.translate('Common.ValidationFailedDate');
    }

    return null;
  }

  isCorrectionInvalid(fieldPath: string): boolean {
    return this.correctionValidationMessage(
      fieldPath,
      this.correctionValues[fieldPath],
    ) !== null;
  }

  transactionDateValidationMessage(value: unknown): string | null {
    return this.correctionValidationMessage('transaction_date', value);
  }

  isTransactionDateInvalid(value: unknown): boolean {
    return this.transactionDateValidationMessage(value) !== null;
  }

  hasInvalidTransactionDates(): boolean {
    return this.transactionDrafts.some((draft) =>
      this.isTransactionDateInvalid(draft.transaction_date),
    );
  }

  private isDateField(fieldPath: string): boolean {
    return fieldPath.endsWith('_date') || fieldPath.endsWith('.date');
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

        error: (error) => {
          this.correctionSubmittingField =
            null;
          this.correctionError =
            (error?.error?.errors?.value?.[0] as string | undefined)
            ?? 'Failed to submit review correction.';
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
    this.correctionValidationErrors = {};
    this.correctionEvidenceByField = {};

    const fields = this.review?.extraction?.fields ?? {};

    for (const [
      fieldPath,
      field,
    ] of Object.entries(fields)) {
      this.correctionValues[fieldPath] =
        field.value;
      this.correctionValidationErrors[fieldPath] =
        this.correctionValidationMessage(fieldPath, field.value);
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
      this.correctionValidationErrors[correction.fieldPath] =
        this.correctionValidationMessage(
          correction.fieldPath,
          correction.extractedValue.value,
        );
    }

    const extractedAmount =
      fields['total_amount']?.value
      ?? fields['transaction.total']?.value
      ?? this.amountCandidateFromText(fields['document_text']?.value);
    const invoiceDate =
      fields['invoice_date']?.value
      ?? fields['transaction.date']?.value;
    const filename = this.review?.document?.originalFilename ?? 'Tax document';
    const displayName = this.review?.document
      ? documentDisplayName(
        this.review.document.originalFilename,
        this.review.document.description,
        fields,
      )
      : filename.replace(/\.[^.]+$/, '');

    this.transactionDrafts = [{
      transaction_date: typeof invoiceDate === 'string' ? invoiceDate : '',
      direction: 'expense',
      amount: typeof extractedAmount === 'string' || typeof extractedAmount === 'number'
        ? Number(extractedAmount)
        : null,
      description: displayName,
    }];
    this.recordExpense = false;
  }

  addTransactionDraft(): void {
    this.transactionDrafts.push(this.emptyExpenseDraft());
  }

  removeTransactionDraft(index: number): void {
    if (this.transactionDrafts.length > 1) {
      this.transactionDrafts.splice(index, 1);
    }
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
