import { provideRouter } from '@angular/router';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { signal } from '@angular/core';
import { of, throwError } from 'rxjs';

import { Review } from '../../../../shared/models/reviews/review.model';
import {
  DocumentApiService,
  DocumentDto,
} from '../../../../shared/services/documents/document-api.service';
import { ReviewService } from '../../../../shared/services/reviews/review.service';
import { DashboardComponent } from './dashboard.component';

describe('DashboardComponent', () => {
  let fixture: ComponentFixture<DashboardComponent>;
  let documentsApi: {
    listDocuments: ReturnType<typeof vi.fn>;
    listFinancialYears: ReturnType<typeof vi.fn>;
    selectedFinancialYearId: ReturnType<typeof signal<string | null>>;
  };
  let reviewService: {
    listReviews: ReturnType<typeof vi.fn>;
  };

  const documents: DocumentDto[] = [
    {
      id: 'document-1',
      financial_year_id: 'year-1',
      document_type_id: 'type-1',
      original_filename: 'rental-statement.pdf',
      mime_type: 'application/pdf',
      size_bytes: 2048,
      uploaded_at: '2026-07-12T10:00:00Z',
      status: 'active',
    },
  ];

  const reviews: Review[] = [
    {
      id: 'review-1',
      userId: 'user-1',
      type: 'confidence',
      status: 'pending',
      financialYearId: 'year-1',
      target: { documentId: 'document-1' },
      document: {
        id: 'document-1',
        originalFilename: 'rental-statement.pdf',
        mimeType: 'application/pdf',
        sizeBytes: 2048,
        uploadedAt: '2026-07-12T10:00:00Z',
      },
      createdAt: '2026-07-12T10:00:00Z',
    },
    {
      id: 'review-2',
      userId: 'user-1',
      type: 'manual',
      status: 'completed',
      target: {},
      createdAt: '2026-07-11T10:00:00Z',
    },
  ];

  beforeEach(async () => {
    documentsApi = {
      listDocuments: vi.fn().mockReturnValue(of(documents)),
      selectedFinancialYearId: signal<string | null>('year-1'),
      listFinancialYears: vi.fn().mockReturnValue(of([{
        id: 'year-1',
        year_code: '2025-26',
        start_date: '2025-07-01',
        end_date: '2026-06-30',
        status: 'active',
      }])),
    };
    reviewService = {
      listReviews: vi.fn().mockReturnValue(of({ data: reviews })),
    };

    await TestBed.configureTestingModule({
      imports: [DashboardComponent],
      providers: [
        provideRouter([]),
        { provide: DocumentApiService, useValue: documentsApi },
        { provide: ReviewService, useValue: reviewService },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(DashboardComponent);
    fixture.detectChanges();
  });

  it('shows live document, review, and financial-year information', () => {
    const text = (fixture.nativeElement as HTMLElement).textContent ?? '';

    expect(text).toContain('FY 2025 - 26');
    expect(text).toContain('rental-statement.pdf');
    expect(text).toContain('Needs attention');
    expect(text).toContain('1');
    expect(text).toContain('Reviewed');
  });

  it('refreshes dashboard document and review statuses automatically', async () => {
    fixture.destroy();
    vi.useFakeTimers();
    documentsApi.listDocuments
      .mockReturnValueOnce(of(documents))
      .mockReturnValue(of([{ ...documents[0], status: 'review_required' }]));
    reviewService.listReviews
      .mockReturnValueOnce(of({ data: reviews }))
      .mockReturnValue(of({
        data: [{ ...reviews[0], status: 'completed' }, reviews[1]],
      }));

    fixture = TestBed.createComponent(DashboardComponent);
    fixture.detectChanges();

    await vi.advanceTimersByTimeAsync(5000);
    fixture.detectChanges();

    expect(fixture.componentInstance.documents[0].status).toBe('review_required');
    expect(fixture.componentInstance.openReviews).toHaveLength(0);

    fixture.destroy();
    vi.useRealTimers();
  });

  it('reports dashboard service errors without showing seeded sample records', () => {
    documentsApi.listDocuments.mockReturnValue(
      throwError(() => new Error('API unavailable')),
    );

    const errorFixture = TestBed.createComponent(DashboardComponent);
    errorFixture.detectChanges();

    const text = (errorFixture.nativeElement as HTMLElement).textContent ?? '';
    expect(text).toContain('Unable to load your dashboard');
    expect(text).not.toContain('Bunnings');
    expect(text).not.toContain('Woolworths');
  });

  it('filters dashboard document and review summaries by the shared financial year', () => {
    const component = fixture.componentInstance;
    component.documents = [
      ...documents,
      { ...documents[0], id: 'document-2', financial_year_id: 'year-2' },
    ];
    component.reviews = [
      reviews[0],
      { ...reviews[0], id: 'review-2', financialYearId: 'year-2' },
    ];
    documentsApi.selectedFinancialYearId.set('year-2');
    fixture.detectChanges();

    expect(component.filteredDocuments.map((document) => document.id))
      .toEqual(['document-2']);
    expect(component.openReviews.map((review) => review.id))
      .toEqual(['review-2']);
  });
});
