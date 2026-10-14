import { ReviewDocument } from './review-extraction.model';
import { ReviewExtraction } from './review-extraction.model';
import type { ReviewCorrectionEvidence } from '../../services/reviews/review.service';

export type ReviewStatus =
  | 'pending'
  | 'in_progress'
  | 'completed'
  | 'rejected'
  | 'superseded';

export type ReviewDecision =
  | 'approved'
  | 'rejected'
  | 'corrected'
  | 'needs_revision';

export type ReviewType =
  | 'confidence'
  | 'classification'
  | 'deductibility'
  | 'manual';

export interface ReviewTarget {
  documentId?: string;
  documentExtractionId?: string;
  transactionId?: string;
}

export interface Review {
  id: string;
  userId: string;

  type: ReviewType;
  status: ReviewStatus;

  target: ReviewTarget;

  document?: ReviewDocument | null;
  extraction?: ReviewExtraction | null;

  /**
   * Active authoritative corrections persisted for this review's
   * extraction fields, as returned by the review GET/list/decision/
   * correction endpoints.
   */
  corrections?: ReviewCorrectionEvidence[];

  decision?: ReviewDecision | null;
  reviewerId?: string | null;

  notes?: string | null;

  createdAt: string;
  completedAt?: string | null;

  supersedesReviewId?: string | null;
}
