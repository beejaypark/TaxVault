import { TestBed } from '@angular/core/testing';
import {
  HttpTestingController,
  provideHttpClientTesting,
} from '@angular/common/http/testing';
import { provideHttpClient } from '@angular/common/http';

import {
  Review,
} from '../../models/reviews/review.model';

import {
  ReviewCorrectionRequest,
  ReviewDecisionRequest,
} from './review.service';

import { ReviewHttpService } from './review-http.service';

describe('ReviewHttpService', () => {
  let service: ReviewHttpService;
  let httpMock: HttpTestingController;

  const reviewId = 'review-123';

  const apiReview = {
    id: reviewId,
    reviewer_id: 'user-123',

    document_id: 'document-123',
    document_extraction_id: 'extraction-123',
    transaction_id: 'transaction-123',

    review_type: 'confidence' as const,
    status: 'pending' as const,

    decision: null,

    reviewer_notes: 'Initial reviewer note',

    created_at: '2026-09-02T01:00:00Z',
    completed_at: null,

    supersedes_review_id: null,
  };

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        ReviewHttpService,
      ],
    });

    service = TestBed.inject(ReviewHttpService);
    httpMock = TestBed.inject(
      HttpTestingController,
    );
  });

  afterEach(() => {
    httpMock.verify();
  });

  it('should load a review', () => {
    let result: Review | undefined;

    service
      .getReview(reviewId)
      .subscribe((review) => {
        result = review;
      });

    const request = httpMock.expectOne(
      `/api/reviews/${reviewId}`,
    );

    expect(request.request.method).toBe('GET');

    request.flush({
      data: apiReview,
    });

    expect(result).toEqual({
      id: reviewId,
      userId: 'user-123',

      type: 'confidence',
      status: 'pending',

      target: {
        documentId: 'document-123',
        documentExtractionId: 'extraction-123',
        transactionId: 'transaction-123',
      },

      document: null,
      extraction: null,

      corrections: [],

      decision: null,
      reviewerId: 'user-123',

      notes: 'Initial reviewer note',

      createdAt: '2026-09-02T01:00:00Z',
      completedAt: null,

      supersedesReviewId: null,
    });
  });

  it('should map persisted authoritative corrections from the review response', () => {
    let result: Review | undefined;

    service
      .getReview(reviewId)
      .subscribe((review) => {
        result = review;
      });

    const request = httpMock.expectOne(
      `/api/reviews/${reviewId}`,
    );

    expect(request.request.method).toBe('GET');

    request.flush({
      data: {
        ...apiReview,

        corrections: [
          {
            id: 'evidence-correction-123',
            user_id: 'user-123',
            financial_year_id: 'fy-2026',
            document_id: 'document-123',
            extraction_id: 'extraction-123',
            evidence_type: 'review_correction',
            source_type: 'document_extraction',
            source_id: 'extraction-123',
            field_path: 'transaction.total',
            extracted_value: {
              value: 135.75,
              source: 'reviewer',
            },
            status: 'active',
            verification_status: 'verified',
            confidence: '100.00',
            verified_at: '2026-09-02T03:00:00Z',
          },
        ],
      },
    });

    expect(result?.corrections).toEqual([
      {
        id: 'evidence-correction-123',
        userId: 'user-123',
        financialYearId: 'fy-2026',
        documentId: 'document-123',
        extractionId: 'extraction-123',
        evidenceType: 'review_correction',
        sourceType: 'document_extraction',
        sourceId: 'extraction-123',
        fieldPath: 'transaction.total',
        extractedValue: {
          value: 135.75,
          source: 'reviewer',
        },
        status: 'active',
        verificationStatus: 'verified',
        confidence: 100,
        verifiedAt: '2026-09-02T03:00:00Z',
      },
    ]);
  });

  it('should map source document and extraction fields', () => {
    let result: Review | undefined;

    service
      .getReview(reviewId)
      .subscribe((review) => {
        result = review;
      });

    const request = httpMock.expectOne(
      `/api/reviews/${reviewId}`,
    );

    expect(request.request.method).toBe('GET');

    request.flush({
      data: {
        ...apiReview,

        document: {
          id: 'document-123',
          original_filename:
            'receipt-2026-08-15.pdf',
          mime_type: 'application/pdf',
          size_bytes: 245678,
          uploaded_at:
            '2026-08-15T03:20:00Z',
        },

        extraction: {
          id: 'extraction-123',
          provider: 'test',
          model: 'test-ocr',
          model_version: '1',
          extraction_version: 'ocr-v1',
          status: 'completed',

          output: {
            normalized: {
              schema_version:
                'ocr-extraction-v1',

              fields: {
                'merchant.name': {
                  value: 'Example Store',
                  confidence: 98.5,
                  source: 'ocr',
                },

                'transaction.total': {
                  value: 125.5,
                  confidence: 91.25,
                  source: 'ocr',
                },
              },
            },
          },

          started_at:
            '2026-08-15T03:20:01Z',

          completed_at:
            '2026-08-15T03:20:03Z',
        },
      },
    });

    expect(result).toBeDefined();

    expect(result?.document).toEqual({
      id: 'document-123',
      originalFilename:
        'receipt-2026-08-15.pdf',
      mimeType: 'application/pdf',
      sizeBytes: 245678,
      uploadedAt:
        '2026-08-15T03:20:00Z',
    });

    expect(result?.extraction).toEqual({
      id: 'extraction-123',
      provider: 'test',
      model: 'test-ocr',
      modelVersion: '1',
      extractionVersion: 'ocr-v1',
      status: 'completed',
      schemaVersion:
        'ocr-extraction-v1',

      fields: {
        'merchant.name': {
          value: 'Example Store',
          confidence: 98.5,
          source: 'ocr',
        },

        'transaction.total': {
          value: 125.5,
          confidence: 91.25,
          source: 'ocr',
        },
      },

      startedAt:
        '2026-08-15T03:20:01Z',

      completedAt:
        '2026-08-15T03:20:03Z',
    });
  });

  it('should list reviews with filters', () => {
    let result:
      | {
          data: Review[];
          currentPage: number;
          lastPage: number;
          total: number;
        }
      | undefined;

    service
      .listReviews({
        status: 'pending',
        type: 'confidence',
        page: 2,
        perPage: 10,
      })
      .subscribe((response) => {
        result = response;
      });

    const request = httpMock.expectOne(
      (req) =>
        req.url === '/api/reviews' &&
        req.params.get('status') === 'pending' &&
        req.params.get('review_type') ===
          'confidence' &&
        req.params.get('page') === '2' &&
        req.params.get('per_page') === '10',
    );

    expect(request.request.method).toBe('GET');

    request.flush({
      data: [
        apiReview,
      ],
      current_page: 1,
      last_page: 1,
      total: 1,
    });

    expect(result?.data).toHaveLength(1);
    expect(result?.data[0].id).toBe(reviewId);
    expect(result?.data[0].type).toBe(
      'confidence',
    );
    expect(result?.data[0].status).toBe(
      'pending',
    );

    expect(result?.currentPage).toBe(1);
    expect(result?.lastPage).toBe(1);
    expect(result?.total).toBe(1);
  });

  it('should map pagination metadata from the list response', () => {
    let result:
      | {
          data: Review[];
          currentPage: number;
          lastPage: number;
          total: number;
        }
      | undefined;

    service
      .listReviews({
        page: 3,
        perPage: 10,
      })
      .subscribe((response) => {
        result = response;
      });

    const request = httpMock.expectOne(
      (req) =>
        req.url === '/api/reviews' &&
        req.params.get('page') === '3' &&
        req.params.get('per_page') === '10',
    );

    expect(request.request.method).toBe('GET');

    request.flush({
      data: [
        apiReview,
      ],
      current_page: 3,
      last_page: 7,
      total: 67,
    });

    expect(result?.data).toHaveLength(1);
    expect(result?.data[0].id).toBe(reviewId);

    expect(result?.currentPage).toBe(3);
    expect(result?.lastPage).toBe(7);
    expect(result?.total).toBe(67);
  });
  it('should submit a review decision', () => {
    let result: Review | undefined;

    const requestBody: ReviewDecisionRequest = {
      decision: 'approved',
      notes: 'Verified by reviewer',
    };

    service
      .submitDecision(
        reviewId,
        requestBody,
      )
      .subscribe((review) => {
        result = review;
      });

    const request = httpMock.expectOne(
      `/api/reviews/${reviewId}/decision`,
    );

    expect(request.request.method).toBe('POST');

    expect(request.request.body).toEqual({
      decision: 'approve',
      reviewer_notes:
        'Verified by reviewer',
    });

    request.flush({
      data: {
        ...apiReview,
        status: 'completed',
        decision: 'approve',
        reviewer_notes:
          'Verified by reviewer',
        completed_at:
          '2026-09-02T02:00:00Z',
      },
    });

    expect(result).toBeDefined();

    expect(result?.decision).toBe(
      'approved',
    );

    expect(result?.status).toBe(
      'completed',
    );

    expect(result?.notes).toBe(
      'Verified by reviewer',
    );

    expect(result?.completedAt).toBe(
      '2026-09-02T02:00:00Z',
    );
  });

  it('should map a rejected decision to the backend API value', () => {
    let result: Review | undefined;

    const requestBody: ReviewDecisionRequest = {
      decision: 'rejected',
      notes: 'Evidence is insufficient',
    };

    service
      .submitDecision(
        reviewId,
        requestBody,
      )
      .subscribe((review) => {
        result = review;
      });

    const request = httpMock.expectOne(
      `/api/reviews/${reviewId}/decision`,
    );

    expect(request.request.method).toBe('POST');

    expect(request.request.body).toEqual({
      decision: 'reject',
      reviewer_notes:
        'Evidence is insufficient',
    });

    request.flush({
      data: {
        ...apiReview,
        status: 'completed',
        decision: 'reject',
        reviewer_notes:
          'Evidence is insufficient',
        completed_at:
          '2026-09-02T02:00:00Z',
      },
    });

    expect(result).toBeDefined();

    expect(result?.decision).toBe(
      'rejected',
    );

    expect(result?.status).toBe(
      'completed',
    );

    expect(result?.notes).toBe(
      'Evidence is insufficient',
    );
  });

  it('should submit a review correction and map the authoritative evidence response', () => {
    let result:
      | {
          review: Review;
          evidence: {
            id: string;
            fieldPath: string;
            extractedValue: {
              value?: unknown;
              source?: string;
              [key: string]: unknown;
            };
            evidenceType: string;
            sourceType: string;
            status: string;
            verificationStatus: string;
            confidence: number;
          };
        }
      | undefined;

    const requestBody: ReviewCorrectionRequest = {
      fieldPath: 'transaction.total',
      value: 135.75,
    };

    service
      .submitCorrection(
        reviewId,
        requestBody,
      )
      .subscribe((response) => {
        result = response;
      });

    const request = httpMock.expectOne(
      `/api/reviews/${reviewId}/correction`,
    );

    expect(request.request.method).toBe('POST');

    expect(request.request.body).toEqual({
      field_path: 'transaction.total',
      value: 135.75,
    });

    request.flush({
      data: {
        review: {
          ...apiReview,
        },

        evidence: {
          id: 'evidence-correction-123',

          user_id: 'user-123',

          financial_year_id:
            'fy-2026',

          document_id:
            'document-123',

          extraction_id:
            'extraction-123',

          evidence_type:
            'review_correction',

          source_type:
            'document_extraction',

          source_id:
            'extraction-123',

          field_path:
            'transaction.total',

          extracted_value: {
            value: 135.75,
            source: 'reviewer',
          },

          status: 'active',

          verification_status:
            'verified',

          confidence: '100.00',

          verified_at:
            '2026-09-02T03:00:00Z',
        },
      },
    });

    expect(result).toBeDefined();

    expect(result?.review.id).toBe(
      reviewId,
    );

    expect(result?.review.status).toBe(
      'pending',
    );

    expect(result?.evidence.id).toBe(
      'evidence-correction-123',
    );

    expect(result?.evidence.fieldPath).toBe(
      'transaction.total',
    );

    expect(
      result?.evidence.extractedValue,
    ).toEqual({
      value: 135.75,
      source: 'reviewer',
    });

    expect(
      result?.evidence.evidenceType,
    ).toBe('review_correction');

    expect(
      result?.evidence.sourceType,
    ).toBe('document_extraction');

    expect(result?.evidence.status).toBe(
      'active',
    );

    expect(
      result?.evidence.verificationStatus,
    ).toBe('verified');

    expect(
      result?.evidence.confidence,
    ).toBe(100);

    expect(
      result?.evidence.fieldPath,
    ).toBe('transaction.total');
  });
});



