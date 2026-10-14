import {
  Component,
  OnInit,
  inject,
} from '@angular/core';
import { ActivatedRoute } from '@angular/router';

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
  imports: [],
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

  readonly Object = Object;

  review: Review | null = null;

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

  ngOnInit(): void {
    const id =
      this.route.snapshot.paramMap.get(
        'id',
      );

    if (!id) {
      return;
    }

    this.reviewService
      .getReview(id)
      .subscribe((review) => {
        this.review = review;
        this.initializeCorrectionValues();
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

    const request:
      import('../../../../shared/services/reviews/review.service')
        .ReviewDecisionRequest = {
      decision,
      notes,
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
      this.review.status !== 'pending' ||
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

    const fields =
      this.review?.extraction?.fields;

    if (fields) {
      for (const [
        fieldPath,
        field,
      ] of Object.entries(fields)) {
        this.correctionValues[fieldPath] =
          field.value;
      }
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
  }
}



