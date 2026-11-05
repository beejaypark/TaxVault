import {
  convertToParamMap,
  provideRouter,
} from '@angular/router';
import {
  ComponentFixture,
  TestBed,
} from '@angular/core/testing';
import { ActivatedRoute } from '@angular/router';
import {
  of,
  throwError,
  Subject,
} from 'rxjs';

import { Review } from '../../../../shared/models/reviews/review.model';
import {
  ReviewCorrectionEvidence,
  ReviewService,
} from '../../../../shared/services/reviews/review.service';

import { ReviewDetailComponent } from './review-detail.component';

describe('ReviewDetailComponent', () => {
  let fixture: ComponentFixture<ReviewDetailComponent>;
  let component: ReviewDetailComponent;

  const review: Review = {
    id: 'review-1',
    userId: 'user-1',
    type: 'confidence',
    status: 'pending',
    target: {
      documentId: 'document-1',
      documentExtractionId: 'extraction-1',
    },
    decision: null,
    reviewerId: 'user-1',
    notes: 'Low confidence field',
    createdAt: '2026-09-01T00:00:00Z',
    completedAt: null,
    supersedesReviewId: null,
  };

  const reviewWithExtraction: Review = {
    ...review,
    extraction: {
      id: 'extraction-1',
      provider: 'test',
      model: 'test-ocr',
      modelVersion: '1',
      extractionVersion: 'ocr-v1',
      schemaVersion: '1',
      status: 'completed',
      startedAt: '2026-09-01T00:00:00Z',
      completedAt: '2026-09-01T00:00:01Z',
      fields: {
        'transaction.total': {
          value: 125.5,
          confidence: 72,
          source: 'ocr',
        },
        'transaction.date': {
          value: '2026-08-31',
          confidence: 91,
          source: 'ocr',
        },
      },
    },
  };

  const reviewService = {
    getReview: vi.fn(() => of(review)),
    listReviews: vi.fn(),
    submitDecision: vi.fn(),
    submitCorrection: vi.fn(),
  };

  beforeEach(async () => {
    vi.clearAllMocks();

    reviewService.getReview.mockReturnValue(
      of(review),
    );

    await TestBed.configureTestingModule({
      imports: [ReviewDetailComponent],
      providers: [
        provideRouter([]),
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: {
              paramMap: convertToParamMap({
                id: 'review-1',
              }),
            },
          },
        },
        {
          provide: ReviewService,
          useValue: reviewService,
        },
      ],
    }).compileComponents();

    fixture =
      TestBed.createComponent(
        ReviewDetailComponent,
      );

    component =
      fixture.componentInstance;

    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should load the review from the route id', () => {
    expect(
      reviewService.getReview,
    ).toHaveBeenCalledWith(
      'review-1',
    );
  });

  it('should display the review status and type', () => {
    const element =
      fixture.nativeElement as HTMLElement;

    expect(
      element.textContent,
    ).toContain('Pending review');

    expect(
      element.textContent,
    ).toContain('Data confidence');
  });

  it('should submit an approved decision', () => {
    reviewService.submitDecision.mockReturnValue(
      of({
        ...review,
        status: 'completed',
        decision: 'approved',
        notes: 'Verified by reviewer',
        completedAt:
          '2026-09-01T01:00:00Z',
      }),
    );

    component.submitDecision(
      'approved',
      'Verified by reviewer',
    );

    expect(
      reviewService.submitDecision,
    ).toHaveBeenCalledWith(
      'review-1',
      {
        decision: 'approved',
        notes: 'Verified by reviewer',
      },
    );
  });

  it('should include a verified transaction when selected', () => {
    reviewService.submitDecision.mockReturnValue(
      of({
        ...review,
        status: 'completed',
        decision: 'approved',
      }),
    );
    component.recordExpense = true;
    component.expenseDraft = {
      transaction_date: '2026-07-15',
      direction: 'expense',
      amount: 145.6,
      description: 'Brisbane City Council rates - Q1',
    };

    component.submitDecision('approved');

    expect(reviewService.submitDecision).toHaveBeenCalledWith(
      'review-1',
      {
        decision: 'approved',
        notes: undefined,
        expense: {
          transaction_date: '2026-07-15',
          direction: 'expense',
          amount: 145.6,
          description: 'Brisbane City Council rates - Q1',
        },
      },
    );
  });

  it('should prefill an amount from the extracted bill text when no normalized amount exists', () => {
    reviewService.getReview.mockReturnValueOnce(
      of({
        ...reviewWithExtraction,
        document: {
          id: 'document-1',
          originalFilename: 'q1.pdf',
          mimeType: 'application/pdf',
          sizeBytes: 100,
          uploadedAt: '2026-09-01T00:00:00Z',
        },
        extraction: {
          ...reviewWithExtraction.extraction!,
          fields: {
            'transaction.date': {
              value: '2026-08-31',
              confidence: 91,
              source: 'ocr',
            },
            supplier: {
              value: 'Brisbane City Council',
              confidence: 95,
              source: 'local_ocr',
            },
            'document_text': {
              value: 'Gross Amount 548.82\nNett Amount Payable 548.80\nOptional donation 563.80',
              confidence: 80,
              source: 'local_ocr',
            },
          },
        },
      }),
    );

    component.retryLoad();

    expect(component.expenseDraft.amount).toBe(548.8);
    expect(component.expenseDraft.description).toBe('Brisbane City Council rates - Q1');
    expect(component.expenseDraft.transaction_date).toBe('2026-08-31');
    expect(component.recordExpense).toBe(false);
  });

  it('should update the review after a successful decision', () => {
    const completedReview:
      Review = {
      ...review,
      status: 'completed',
      decision: 'approved',
      notes: 'Verified by reviewer',
      completedAt:
        '2026-09-01T01:00:00Z',
    };

    reviewService.submitDecision.mockReturnValue(
      of(completedReview),
    );

    component.submitDecision(
      'approved',
      'Verified by reviewer',
    );

    expect(
      component.review,
    ).toEqual(
      completedReview,
    );

    expect(
      component.isSubmitting,
    ).toBe(false);

    expect(
      component.decisionSuccess,
    ).toBe(true);

    expect(
      component.decisionError,
    ).toBeNull();
  });

  it('should prevent duplicate decision submission', () => {
    reviewService.submitDecision.mockReturnValue(
      of({
        ...review,
        status: 'completed',
        decision: 'approved',
      }),
    );

    component.isSubmitting = true;

    component.submitDecision(
      'approved',
    );

    expect(
      reviewService.submitDecision,
    ).not.toHaveBeenCalled();
  });

  it('should handle decision submission errors', () => {
    reviewService.submitDecision.mockReturnValue(
      throwError(
        () =>
          new Error(
            'Request failed',
          ),
      ),
    );

    component.submitDecision(
      'rejected',
      'Insufficient evidence',
    );

    expect(
      component.isSubmitting,
    ).toBe(false);

    expect(
      component.decisionSuccess,
    ).toBe(false);

    expect(
      component.decisionError,
    ).toBe(
      'Failed to submit review decision.',
    );
  });

  it('should reset previous decision state before submitting', () => {
    reviewService.submitDecision.mockReturnValue(
      of({
        ...review,
        status: 'completed',
        decision: 'approved',
      }),
    );

    component.decisionSuccess = true;
    component.decisionError =
      'Previous error';

    component.submitDecision(
      'approved',
    );

    expect(
      component.decisionError,
    ).toBeNull();

    expect(
      component.decisionSuccess,
    ).toBe(true);

    expect(
      component.isSubmitting,
    ).toBe(false);
  });

  it('should initialize correction values from extracted fields', () => {
    component.review =
      reviewWithExtraction;

    component[
      'initializeCorrectionValues'
    ]();

    expect(
      component.correctionValues[
        'transaction.total'
      ],
    ).toBe(125.5);

    expect(
      component.correctionValues[
        'transaction.date'
      ],
    ).toBe('2026-08-31');
  });

  it('should offer manual amount and date entry only for values OCR did not find', () => {
    component.review = reviewWithExtraction;
    expect(component.missingManualFields).toEqual([]);

    component.review = {
      ...reviewWithExtraction,
      extraction: {
        ...reviewWithExtraction.extraction!,
        fields: { 'transaction.total': { value: 10, confidence: 90, source: 'ocr' } },
      },
    };
    expect(component.missingManualFields.map((f) => f.path)).toEqual(['invoice_date']);

    component.review = {
      ...reviewWithExtraction,
      extraction: { ...reviewWithExtraction.extraction!, fields: {} },
    };
    expect(component.missingManualFields.map((f) => f.path)).toEqual(['total_amount', 'invoice_date']);
  });

  it('should rehydrate persisted authoritative corrections when the review loads', () => {
    const persistedCorrection: ReviewCorrectionEvidence = {
      id: 'evidence-persisted-1',
      userId: 'user-1',
      financialYearId: 'fy-1',
      documentId: 'document-1',
      extractionId: 'extraction-1',
      evidenceType: 'review_correction',
      sourceType: 'document_extraction',
      sourceId: 'extraction-1',
      fieldPath: 'transaction.total',
      extractedValue: {
        value: 140.25,
        source: 'reviewer',
      },
      status: 'active',
      verificationStatus: 'verified',
      confidence: 100,
      verifiedAt: '2026-09-02T03:00:00Z',
    };

    reviewService.getReview.mockReturnValue(
      of({
        ...reviewWithExtraction,
        corrections: [persistedCorrection],
      }),
    );

    fixture.destroy();

    fixture = TestBed.createComponent(
      ReviewDetailComponent,
    );

    component = fixture.componentInstance;

    fixture.detectChanges();

    expect(
      component.getCorrectionEvidence(
        'transaction.total',
      ),
    ).toEqual(persistedCorrection);

    expect(
      component.correctionValues[
        'transaction.total'
      ],
    ).toBe(140.25);

    const element =
      fixture.nativeElement as HTMLElement;

    expect(
      element.textContent,
    ).toContain(
      'Authoritative Correction',
    );
  });

  it('should submit a correction for a pending review', () => {
    component.review =
      reviewWithExtraction;

    component.setCorrectionValue(
      'transaction.total',
      135.75,
    );

    reviewService.submitCorrection.mockReturnValue(
      of({
        review:
          reviewWithExtraction,
        evidence: {
          id: 'evidence-1',
          fieldPath:
            'transaction.total',
          extractedValue: {
            value: 135.75,
            source: 'reviewer',
          },
          evidenceType:
            'review_correction',
          sourceType:
            'document_extraction',
          status: 'active',
          verificationStatus:
            'verified',
          confidence: 100,
          verifiedAt:
            '2026-09-02T03:00:00Z',
        },
      }),
    );

    component.submitCorrection(
      'transaction.total',
    );

    expect(
      reviewService.submitCorrection,
    ).toHaveBeenCalledWith(
      'review-1',
      {
        fieldPath:
          'transaction.total',
        value: 135.75,
      },
    );

    expect(
      component.correctionSubmittingField,
    ).toBeNull();

    expect(
      component.correctionSuccessField,
    ).toBe(
      'transaction.total',
    );

    expect(
      component.correctionError,
    ).toBeNull();
  });

  it('should retain authoritative correction evidence after successful correction', () => {
    component.review =
      reviewWithExtraction;

    const evidence: ReviewCorrectionEvidence = {
      id: 'evidence-1',
      userId: 'user-1',
      financialYearId: 'fy-1',
      documentId: 'document-1',
      extractionId: 'extraction-1',
      evidenceType: 'review_correction',
      sourceType: 'document_extraction',
      sourceId: 'extraction-1',
      fieldPath: 'transaction.total',
      extractedValue: {
        value: 135.75,
        source: 'reviewer',
      },
      status: 'active',
      verificationStatus: 'verified',
      confidence: 100,
      verifiedAt: '2026-09-02T03:00:00Z',
    };

    reviewService.submitCorrection.mockReturnValue(
      of({
        review: reviewWithExtraction,
        evidence,
      }),
    );

    component.setCorrectionValue(
      'transaction.total',
      135.75,
    );

    component.submitCorrection(
      'transaction.total',
    );

    expect(
      component.getCorrectionEvidence(
        'transaction.total',
      ),
    ).toEqual(evidence);

    expect(
      component.correctionValues[
        'transaction.total'
      ],
    ).toBe(135.75);
  });

  it('should replace the active authoritative correction when the same field is corrected again', () => {
    component.review =
      reviewWithExtraction;

    const firstEvidence: ReviewCorrectionEvidence = {
      id: 'evidence-1',
      userId: 'user-1',
      financialYearId: 'fy-1',
      documentId: 'document-1',
      extractionId: 'extraction-1',
      evidenceType: 'review_correction',
      sourceType: 'document_extraction',
      sourceId: 'extraction-1',
      fieldPath: 'transaction.total',
      extractedValue: {
        value: 135.75,
        source: 'reviewer',
      },
      status: 'superseded',
      verificationStatus: 'verified',
      confidence: 100,
      verifiedAt: '2026-09-02T03:00:00Z',
    };

    const secondEvidence: ReviewCorrectionEvidence = {
      ...firstEvidence,
      id: 'evidence-2',
      extractedValue: {
        value: 140.25,
        source: 'reviewer',
      },
      status: 'active',
    };

    reviewService.submitCorrection.mockReturnValue(
      of({
        review: reviewWithExtraction,
        evidence: firstEvidence,
      }),
    );

    component.setCorrectionValue(
      'transaction.total',
      135.75,
    );

    component.submitCorrection(
      'transaction.total',
    );

    expect(
      component.getCorrectionEvidence(
        'transaction.total',
      )?.id,
    ).toBe('evidence-1');

    reviewService.submitCorrection.mockReturnValue(
      of({
        review: reviewWithExtraction,
        evidence: secondEvidence,
      }),
    );

    component.setCorrectionValue(
      'transaction.total',
      140.25,
    );

    component.submitCorrection(
      'transaction.total',
    );

    expect(
      component.getCorrectionEvidence(
        'transaction.total',
      )?.id,
    ).toBe('evidence-2');

    expect(
      component.getCorrectionEvidence(
        'transaction.total',
      )?.extractedValue.value,
    ).toBe(140.25);
  });

  it('should keep the original OCR extraction value unchanged after correction', () => {
    component.review =
      reviewWithExtraction;

    const evidence: ReviewCorrectionEvidence = {
      id: 'evidence-1',
      userId: 'user-1',
      financialYearId: 'fy-1',
      documentId: 'document-1',
      extractionId: 'extraction-1',
      evidenceType: 'review_correction',
      sourceType: 'document_extraction',
      sourceId: 'extraction-1',
      fieldPath: 'transaction.total',
      extractedValue: {
        value: 135.75,
        source: 'reviewer',
      },
      status: 'active',
      verificationStatus: 'verified',
      confidence: 100,
      verifiedAt: '2026-09-02T03:00:00Z',
    };

    reviewService.submitCorrection.mockReturnValue(
      of({
        review: reviewWithExtraction,
        evidence,
      }),
    );

    component.setCorrectionValue(
      'transaction.total',
      135.75,
    );

    component.submitCorrection(
      'transaction.total',
    );

    expect(
      component.review?.extraction?.fields[
        'transaction.total'
      ].value,
    ).toBe(125.5);

    expect(
      component.getCorrectionEvidence(
        'transaction.total',
      )?.extractedValue.value,
    ).toBe(135.75);
  });

  it('should prevent duplicate correction submission', () => {
    component.review =
      reviewWithExtraction;

    component.correctionSubmittingField =
      'transaction.total';

    component.submitCorrection(
      'transaction.total',
    );

    expect(
      reviewService.submitCorrection,
    ).not.toHaveBeenCalled();
  });

  it('should prevent correction submission for completed reviews until editing is enabled', () => {
    component.review = {
      ...reviewWithExtraction,
      status: 'completed',
    };

    component.setCorrectionValue(
      'transaction.total',
      135.75,
    );

    component.submitCorrection(
      'transaction.total',
    );

    expect(
      reviewService.submitCorrection,
    ).not.toHaveBeenCalled();

    component.toggleCorrectionEditing();
    expect(component.canEditCorrections).toBe(true);

    reviewService.submitCorrection.mockReturnValue(
      of({
        review: {
          ...reviewWithExtraction,
          status: 'completed',
        },
        evidence: {
          id: 'evidence-completed',
          fieldPath: 'transaction.total',
          extractedValue: {
            value: 135.75,
            source: 'reviewer',
          },
          evidenceType: 'review_correction',
          sourceType: 'document_extraction',
          status: 'active',
          verificationStatus: 'verified',
          confidence: 100,
          verifiedAt: '2026-09-02T03:00:00Z',
        },
      }),
    );

    component.submitCorrection('transaction.total');

    expect(reviewService.submitCorrection).toHaveBeenCalledWith(
      'review-1',
      {
        fieldPath: 'transaction.total',
        value: 135.75,
      },
    );
    expect(component.review?.status).toBe('completed');
  });

  it('should handle correction submission errors', () => {
    component.review =
      reviewWithExtraction;

    component.setCorrectionValue(
      'transaction.total',
      135.75,
    );

    reviewService.submitCorrection.mockReturnValue(
      throwError(
        () =>
          new Error(
            'Request failed',
          ),
      ),
    );

    component.submitCorrection(
      'transaction.total',
    );

    expect(
      component.correctionSubmittingField,
    ).toBeNull();

    expect(
      component.correctionSuccessField,
    ).toBeNull();

    expect(
      component.correctionError,
    ).toBe(
      'Failed to submit review correction.',
    );
  });

  it('should render correction controls for extracted fields', () => {
    reviewService.getReview.mockReturnValue(
      of(reviewWithExtraction),
    );

    fixture.destroy();

    fixture =
      TestBed.createComponent(
        ReviewDetailComponent,
      );

    component =
      fixture.componentInstance;

    fixture.detectChanges();

    const element =
      fixture.nativeElement as HTMLElement;

    expect(
      component.review?.extraction,
    ).toBeTruthy();

    expect(
      element.textContent,
    ).toContain(
      'Document Extraction',
    );

    expect(
      element.textContent,
    ).toContain(
      'Extracted Fields',
    );

    expect(
      element.textContent,
    ).toContain(
      'Corrected value',
    );

    expect(
      element.textContent,
    ).toContain(
      'Save Correction',
    );

    expect(
      element.textContent,
    ).toContain(
      'transaction.total',
    );
  });

  it('should render Approve and Reject buttons for pending review', () => {
    const element =
      fixture.nativeElement as HTMLElement;

    const buttons =
      Array.from(
        element.querySelectorAll('button'),
      ).map((button) =>
        button.textContent?.trim(),
      );

    expect(buttons).toContain('Approve');
    expect(buttons).toContain('Reject');
  });

  it('should submit approved decision when Approve is clicked', () => {
    const completedReview: Review = {
      ...review,
      status: 'completed',
      decision: 'approved',
      notes: 'Verified by reviewer',
      completedAt:
        '2026-09-07T07:00:00Z',
    };

    reviewService.submitDecision.mockReturnValue(
      of(completedReview),
    );

    const element =
      fixture.nativeElement as HTMLElement;

    const textarea =
      element.querySelector(
        '#review-notes',
      ) as HTMLTextAreaElement;

    textarea.value =
      'Verified by reviewer';

    const approveButton =
      Array.from(
        element.querySelectorAll('button'),
      ).find(
        (button) =>
          button.textContent?.trim() ===
          'Approve',
      ) as HTMLButtonElement;

    expect(approveButton).toBeTruthy();

    approveButton.click();

    expect(
      reviewService.submitDecision,
    ).toHaveBeenCalledWith(
      'review-1',
      {
        decision: 'approved',
        notes: 'Verified by reviewer',
      },
    );

    expect(
      component.review,
    ).toEqual(
      completedReview,
    );
  });

  it('should submit rejected decision when Reject is clicked', () => {
    const completedReview: Review = {
      ...review,
      status: 'completed',
      decision: 'rejected',
      notes: 'Insufficient evidence',
      completedAt:
        '2026-09-07T07:00:00Z',
    };

    reviewService.submitDecision.mockReturnValue(
      of(completedReview),
    );

    const element =
      fixture.nativeElement as HTMLElement;

    const textarea =
      element.querySelector(
        '#review-notes',
      ) as HTMLTextAreaElement;

    textarea.value =
      'Insufficient evidence';

    const rejectButton =
      Array.from(
        element.querySelectorAll('button'),
      ).find(
        (button) =>
          button.textContent?.trim() ===
          'Reject',
      ) as HTMLButtonElement;

    expect(rejectButton).toBeTruthy();

    rejectButton.click();

    expect(
      reviewService.submitDecision,
    ).toHaveBeenCalledWith(
      'review-1',
      {
        decision: 'rejected',
        notes: 'Insufficient evidence',
      },
    );

    expect(
      component.review,
    ).toEqual(
      completedReview,
    );
  });

  it('should not render decision buttons for completed review', () => {
    reviewService.getReview.mockReturnValue(
      of({
        ...reviewWithExtraction,
        status: 'completed',
        decision: 'approved',
        completedAt: '2026-09-07T07:00:00Z',
      }),
    );

    fixture.destroy();

    fixture = TestBed.createComponent(
      ReviewDetailComponent,
    );

    component = fixture.componentInstance;

    fixture.detectChanges();

    const element =
      fixture.nativeElement as HTMLElement;

    const buttons =
      Array.from(
        element.querySelectorAll('button'),
      ).map((button) =>
        (button as HTMLButtonElement).textContent?.trim(),
      );

    expect(buttons).not.toContain('Approve');
    expect(buttons).not.toContain('Reject');
    expect(buttons).toContain('Edit');
  });

  it('should show correction controls after Edit is clicked for a completed review', () => {
    reviewService.getReview.mockReturnValue(
      of({
        ...reviewWithExtraction,
        status: 'completed',
        decision: 'approved',
      }),
    );

    fixture.destroy();
    fixture = TestBed.createComponent(ReviewDetailComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    const buttons = Array.from(
      fixture.nativeElement.querySelectorAll('button'),
    ) as HTMLButtonElement[];
    buttons.find((button) => button.textContent?.trim() === 'Edit')?.click();
    fixture.detectChanges();

    expect(component.canEditCorrections).toBe(true);
    expect(fixture.nativeElement.textContent).toContain('Save Correction');
  });

  it('should submit correction when Save Correction is clicked', () => {
    reviewService.getReview.mockReturnValue(
      of(reviewWithExtraction),
    );

    fixture.destroy();

    fixture = TestBed.createComponent(
      ReviewDetailComponent,
    );

    component = fixture.componentInstance;

    fixture.detectChanges();

    const input = fixture.nativeElement.querySelector(
      'input[id^="correction-"]',
    ) as HTMLInputElement;

    expect(input).not.toBeNull();

    input.value = '150';
    input.dispatchEvent(new Event('input'));
    fixture.detectChanges();

    const buttons =
      Array.from(
        fixture.nativeElement.querySelectorAll('button'),
      ) as HTMLButtonElement[];

    const saveButton = buttons.find(
      (button) =>
        button.textContent?.trim() === 'Save Correction',
    );

    expect(saveButton).toBeDefined();

    reviewService.submitCorrection.mockReturnValue(
      of({
        review: {
          ...reviewWithExtraction,
          status: 'pending',
        },
        evidence: {
          id: 'correction-1',
          userId: 'user-1',
          financialYearId: 'fy-1',
          documentId: 'document-1',
          extractionId: 'extraction-1',
          evidenceType: 'review_correction',
          sourceType: 'document_extraction',
          sourceId: 'extraction-1',
          fieldPath: 'transaction.total',
          extractedValue: {
            value: 150,
            source: 'reviewer',
          },
          status: 'active',
          verificationStatus: 'verified',
          confidence: 100,
          verifiedAt: '2026-09-02T03:00:00Z',
        },
      }),
    );

    saveButton!.click();
    fixture.detectChanges();

    expect(
      reviewService.submitCorrection,
    ).toHaveBeenCalledWith(
      'review-1',
      {
        fieldPath: 'transaction.total',
        value: '150',
      },
    );
  });

  it('should disable correction controls while saving a correction', () => {
    reviewService.getReview.mockReturnValue(
      of(reviewWithExtraction),
    );

    fixture.destroy();

    fixture = TestBed.createComponent(
      ReviewDetailComponent,
    );

    component = fixture.componentInstance;

    fixture.detectChanges();

    const input = fixture.nativeElement.querySelector(
      'input[id^="correction-"]',
    ) as HTMLInputElement;

    expect(input).not.toBeNull();

    input.value = '150';
    input.dispatchEvent(new Event('input'));
    fixture.detectChanges();

    const buttons =
      Array.from(
        fixture.nativeElement.querySelectorAll('button'),
      ) as HTMLButtonElement[];

    const saveButton = buttons.find(
      (button) =>
        button.textContent?.trim() === 'Save Correction',
    );

    expect(saveButton).toBeDefined();

    reviewService.submitCorrection.mockImplementation(
      () =>
        new Subject<{
          review: Review;
          evidence: ReviewCorrectionEvidence;
        }>(),
    );

    saveButton!.click();
    fixture.detectChanges();

    const correctionInputs =
      Array.from(
        fixture.nativeElement.querySelectorAll(
          'input[id^="correction-"]',
        ),
      ) as HTMLInputElement[];

    expect(
      correctionInputs.every(
        (correctionInput) => correctionInput.disabled,
      ),
    ).toBe(true);

    expect(saveButton!.disabled).toBe(true);
  });
});
