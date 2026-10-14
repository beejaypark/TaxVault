import { Injectable, inject } from '@angular/core';
import {
  HttpClient,
  HttpParams,
} from '@angular/common/http';
import {
  Observable,
  map,
} from 'rxjs';

import {
  Review,
  ReviewDecision,
  ReviewStatus,
  ReviewType,
} from '../../models/reviews/review.model';

import {
  ReviewDocument,
  ReviewExtraction,
  ReviewExtractionField,
} from '../../models/reviews/review-extraction.model';

import {
  ReviewCorrectionEvidence,
  ReviewCorrectionRequest,
  ReviewCorrectionResponse,
  ReviewDecisionRequest,
  ReviewListQuery,
  ReviewListResponse,
  ReviewService,
} from './review.service';

interface ReviewApiResponse {
  data: ReviewApiDto;
}

interface ReviewListApiResponse {
  data: ReviewApiDto[];
  current_page: number;
  last_page: number;
  total: number;
}

interface ReviewCorrectionApiResponse {
  data: {
    review: ReviewApiDto;
    evidence: ReviewCorrectionEvidenceApiDto;
  };
}

interface ReviewApiDto {
  id: string;
  reviewer_id: string;

  document_id?: string | null;
  document_extraction_id?: string | null;
  transaction_id?: string | null;

  review_type: ReviewType;
  status: ReviewStatus;

  decision?: string | null;
  reviewer_notes?: string | null;

  created_at: string;
  completed_at?: string | null;

  supersedes_review_id?: string | null;

  document?: ReviewDocumentApiDto | null;
  extraction?: ReviewExtractionApiDto | null;
  corrections?: ReviewCorrectionEvidenceApiDto[];
}

interface ReviewDocumentApiDto {
  id: string;
  original_filename?: string | null;
  mime_type?: string | null;
  size_bytes?: number | null;
  uploaded_at?: string | null;
}

interface ReviewExtractionApiDto {
  id: string;
  provider: string;
  model?: string | null;
  model_version?: string | null;
  extraction_version: string;
  status: string;

  output?: {
    normalized?: {
      schema_version?: string | null;
      fields?: Record<
        string,
        {
          value?: unknown;
          confidence?: number;
          source?: string;
        }
      >;
    };
  } | null;

  started_at?: string | null;
  completed_at?: string | null;
}

interface ReviewCorrectionEvidenceApiDto {
  id: string;
  user_id: string;
  financial_year_id: string;
  document_id: string;
  extraction_id: string;
  evidence_type: string;
  source_type: string;
  source_id: string;
  field_path: string;
  extracted_value?: {
    value?: unknown;
    source?: string;
    [key: string]: unknown;
  } | null;
  status: string;
  verification_status: string;
  confidence?: number | string | null;
  verified_at?: string | null;
}

@Injectable()
export class ReviewHttpService extends ReviewService {
  private readonly http = inject(HttpClient);

  private readonly apiBaseUrl = '/api/reviews';

  override getReview(
    id: string,
  ): Observable<Review> {
    return this.http
      .get<ReviewApiResponse>(
        `${this.apiBaseUrl}/${id}`,
      )
      .pipe(
        map((response) =>
          this.mapReview(response.data),
        ),
      );
  }

  override listReviews(
    query: ReviewListQuery = {},
  ): Observable<ReviewListResponse> {
    let params = new HttpParams();

    if (query.status) {
      params = params.set(
        'status',
        query.status,
      );
    }

    if (query.type) {
      params = params.set(
        'review_type',
        query.type,
      );
    }

    if (query.page !== undefined) {
      params = params.set(
        'page',
        query.page,
      );
    }

    if (query.perPage !== undefined) {
      params = params.set(
        'per_page',
        query.perPage,
      );
    }

    return this.http
      .get<ReviewListApiResponse>(
        this.apiBaseUrl,
        { params },
      )
      .pipe(
        map((response) => ({
          data: response.data.map(
            (review) =>
              this.mapReview(review),
          ),
          currentPage:
            response.current_page,
          lastPage:
            response.last_page,
          total:
            response.total,
        })),
      );
  }

  override submitDecision(
    id: string,
    request: ReviewDecisionRequest,
  ): Observable<Review> {
    return this.http
      .post<ReviewApiResponse>(
        `${this.apiBaseUrl}/${id}/decision`,
        {
          decision:
            this.mapDecisionToApi(
              request.decision,
            ),
          reviewer_notes:
            request.notes ?? null,
        },
      )
      .pipe(
        map((response) =>
          this.mapReview(response.data),
        ),
      );
  }

  override submitCorrection(
    id: string,
    request: ReviewCorrectionRequest,
  ): Observable<ReviewCorrectionResponse> {
    return this.http
      .post<ReviewCorrectionApiResponse>(
        `${this.apiBaseUrl}/${id}/correction`,
        {
          field_path:
            request.fieldPath,
          value:
            request.value,
        },
      )
      .pipe(
        map((response) => ({
          review:
            this.mapReview(
              response.data.review,
            ),

          evidence:
            this.mapCorrectionEvidence(
              response.data.evidence,
            ),
        })),
      );
  }

  private mapReview(
    dto: ReviewApiDto,
  ): Review {
    return {
      id: dto.id,
      userId: dto.reviewer_id,

      type: dto.review_type,
      status: dto.status,

      target: {
        ...(dto.document_id
          ? {
              documentId:
                dto.document_id,
            }
          : {}),

        ...(dto.document_extraction_id
          ? {
              documentExtractionId:
                dto.document_extraction_id,
            }
          : {}),

        ...(dto.transaction_id
          ? {
              transactionId:
                dto.transaction_id,
            }
          : {}),
      },

      document: dto.document
        ? this.mapDocument(dto.document)
        : null,

      extraction: dto.extraction
        ? this.mapExtraction(
            dto.extraction,
          )
        : null,

      corrections: (
        dto.corrections ?? []
      ).map((correction) =>
        this.mapCorrectionEvidence(
          correction,
        ),
      ),

      decision:
        this.mapDecisionFromApi(
          dto.decision,
        ),

      reviewerId:
        dto.reviewer_id,

      notes:
        dto.reviewer_notes ?? null,

      createdAt:
        dto.created_at,

      completedAt:
        dto.completed_at ?? null,

      supersedesReviewId:
        dto.supersedes_review_id ?? null,
    };
  }

  private mapDocument(
    dto: ReviewDocumentApiDto,
  ): ReviewDocument {
    return {
      id: dto.id,

      originalFilename:
        dto.original_filename ?? dto.id,

      mimeType:
        dto.mime_type ?? null,

      sizeBytes:
        dto.size_bytes ?? null,

      uploadedAt:
        dto.uploaded_at ?? null,
    };
  }

  private mapExtraction(
    dto: ReviewExtractionApiDto,
  ): ReviewExtraction {
    const normalized =
      dto.output?.normalized;

    const fields: Record<
      string,
      ReviewExtractionField
    > = {};

    for (const [
      fieldPath,
      field,
    ] of Object.entries(
      normalized?.fields ?? {},
    )) {
      if (
        field.confidence === undefined ||
        field.source === undefined
      ) {
        continue;
      }

      fields[fieldPath] = {
        value: field.value,
        confidence: Number(
          field.confidence,
        ),
        source: field.source,
      };
    }

    return {
      id: dto.id,

      provider: dto.provider,

      model:
        dto.model ?? null,

      modelVersion:
        dto.model_version ?? null,

      extractionVersion:
        dto.extraction_version,

      status:
        dto.status,

      schemaVersion:
        normalized?.schema_version ??
        null,

      fields,

      startedAt:
        dto.started_at ?? null,

      completedAt:
        dto.completed_at ?? null,
    };
  }

  private mapCorrectionEvidence(
    dto: ReviewCorrectionEvidenceApiDto,
  ): ReviewCorrectionEvidence {
    return {
      id: dto.id,

      userId:
        dto.user_id,

      financialYearId:
        dto.financial_year_id,

      documentId:
        dto.document_id,

      extractionId:
        dto.extraction_id,

      evidenceType:
        dto.evidence_type,

      sourceType:
        dto.source_type,

      sourceId:
        dto.source_id,

      fieldPath:
        dto.field_path,

      extractedValue:
        dto.extracted_value ?? {},

      status:
        dto.status,

      verificationStatus:
        dto.verification_status,

      confidence:
        Number(
          dto.confidence ?? 0,
        ),

      verifiedAt:
        dto.verified_at ?? null,
    };
  }

  private mapDecisionToApi(
    decision: ReviewDecision,
  ): string {
    switch (decision) {
      case 'approved':
        return 'approve';

      case 'rejected':
        return 'reject';

      default:
        return decision;
    }
  }

  private mapDecisionFromApi(
    decision:
      | string
      | null
      | undefined,
  ): ReviewDecision | null {
    if (
      decision === null ||
      decision === undefined
    ) {
      return null;
    }

    switch (decision) {
      case 'approve':
        return 'approved';

      case 'reject':
        return 'rejected';

      default:
        return decision as ReviewDecision;
    }
  }
}

