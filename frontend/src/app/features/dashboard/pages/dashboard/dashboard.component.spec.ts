import { provideRouter } from '@angular/router';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { signal } from '@angular/core';
import { of, throwError } from 'rxjs';

import { Review } from '../../../../shared/models/reviews/review.model';
import {
  DocumentApiService,
  DocumentDto,
} from '../../../../shared/services/documents/document-api.service';
import {
  TaxWorkspaceApiService,
  TransactionDto,
} from '../../../../shared/services/tax-workspace-api.service';
import { ReviewService } from '../../../../shared/services/reviews/review.service';
import { DashboardComponent } from './dashboard.component';

describe('DashboardComponent', () => {
  let fixture: ComponentFixture<DashboardComponent>;
  let documentsApi: {
    listDocuments: ReturnType<typeof vi.fn>;
    listFinancialYears: ReturnType<typeof vi.fn>;
    selectedFinancialYearId: ReturnType<typeof signal<string | null>>;
  financialYears: ReturnType<typeof signal<unknown[]>>;
  };
  let reviewService: {
    listReviews: ReturnType<typeof vi.fn>;
  };
  let workspaceApi: {
    listTransactions: ReturnType<typeof vi.fn>;
    getDeadlines: ReturnType<typeof vi.fn>;
  getWealth: ReturnType<typeof vi.fn>;
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

  const transactions: TransactionDto[] = [
    {
      id: 'transaction-1',
      financial_year_id: 'year-1',
      document_id: 'document-1',
      transaction_date: '2025-07-12',
      description: 'Rental income',
      direction: 'income',
      amount: '2500.00',
      currency: 'AUD',
      source_system: 'manual',
    },
    {
      id: 'transaction-2',
      financial_year_id: 'year-1',
      document_id: null,
      transaction_date: '2025-07-14',
      description: 'Repairs',
      direction: 'expense',
      amount: '400.00',
      currency: 'AUD',
      source_system: 'manual',
    },
    {
      id: 'transaction-3',
      financial_year_id: 'year-2',
      document_id: null,
      transaction_date: '2026-07-14',
      description: 'Another year',
      direction: 'expense',
      amount: '900.00',
      currency: 'AUD',
      source_system: 'manual',
    },
  ];

  beforeEach(async () => {
    documentsApi = {
      listDocuments: vi.fn().mockReturnValue(of(documents)),
      selectedFinancialYearId: signal<string | null>('year-1'),
      financialYears: signal([]),
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
    workspaceApi = {
      listTransactions: vi.fn().mockReturnValue(of(transactions)),
      getDeadlines: vi.fn().mockReturnValue(of([])),
      getWealth: vi.fn().mockReturnValue(of({ assets: [], total_assets: 0, total_liabilities: 0, net_worth: 0, average_interest_rate: 0, history: [], cash_flow: [] })),
    };

    await TestBed.configureTestingModule({
      imports: [DashboardComponent],
      providers: [
        provideRouter([]),
        { provide: DocumentApiService, useValue: documentsApi },
        { provide: ReviewService, useValue: reviewService },
        { provide: TaxWorkspaceApiService, useValue: workspaceApi },
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

  it('summarizes and charts transactions for the selected financial year', () => {
    const component = fixture.componentInstance;

    expect(component.totalIncome).toBe(2500);
    expect(component.totalExpenses).toBe(400);
    expect(component.netAmount).toBe(2100);
    expect(component.monthlyTransactions).toHaveLength(12);
    expect(component.monthlyTransactions[0]).toMatchObject({
      key: '2025-07',
      income: 2500,
      expenses: 400,
      incomeHeight: 100,
      expensesHeight: 16,
    });
    expect(component.monthlyTransactions[11].key).toBe('2026-06');
  });

  it('shows financial analysis and review progress in the dashboard', () => {
    const text = (fixture.nativeElement as HTMLElement).textContent ?? '';

    expect(text).toContain('Financial year at a glance');
    expect(text).toContain('Monthly income & expenses');
    expect(text).toContain('$2,500');
    expect(text).toContain('$400');
    expect(text).toContain('Document review progress');
  });

  it('reports transaction analysis errors instead of showing an empty chart', () => {
    workspaceApi.listTransactions.mockReturnValue(
      throwError(() => new Error('API unavailable')),
    );

    const errorFixture = TestBed.createComponent(DashboardComponent);
    errorFixture.detectChanges();

    expect(errorFixture.componentInstance.financialAnalysisError)
      .toBe('Unable to load financial analysis for this year.');
    expect((errorFixture.nativeElement as HTMLElement).textContent)
      .toContain('Unable to load financial analysis for this year.');
    expect((errorFixture.nativeElement as HTMLElement).textContent)
      .not.toContain('No transactions recorded for this financial year yet.');
  });
});
