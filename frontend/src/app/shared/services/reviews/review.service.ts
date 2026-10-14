import { Observable } from 'rxjs';

import {
  Review,
  ReviewDecision,
  ReviewStatus,
  ReviewType,
} from '../../models/reviews/review.model';

export interface ReviewListQuery {
  status?: ReviewStatus;
  type?: ReviewType;
  page?: number;
  perPage?: number;
}

export interface ReviewListResponse {
  data: Review[];
  currentPage: number;
  lastPage: number;
  total: number;
}

export interface ReviewDecisionRequest {
  decision: ReviewDecision;
  notes?: string;
}

export interface ReviewCorrectionRequest {
  fieldPath: string;
  value: unknown;
}

export interface ReviewCorrectionEvidence {
  id: string;
  userId: string;
  financialYearId: string;
  documentId: string;
  extractionId: string;
  evidenceType: string;
  sourceType: string;
  sourceId: string;
  fieldPath: string;
  extractedValue: {
    value?: unknown;
    source?: string;
    [key: string]: unknown;
  };
  status: string;
  verificationStatus: string;
  confidence: number;
  verifiedAt: string | null;
}

export interface ReviewCorrectionResponse {
  review: Review;
  evidence: ReviewCorrectionEvidence;
}

export abstract class ReviewService {
  abstract getReview(id: string): Observable<Review>;

  abstract listReviews(
    query?: ReviewListQuery,
  ): Observable<ReviewListResponse>;

  abstract submitDecision(
    id: string,
    request: ReviewDecisionRequest,
  ): Observable<Review>;

  abstract submitCorrection(
    id: string,
    request: ReviewCorrectionRequest,
  ): Observable<ReviewCorrectionResponse>;
}
