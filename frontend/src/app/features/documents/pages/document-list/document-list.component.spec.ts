import { provideRouter } from '@angular/router';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { signal } from '@angular/core';
import { of, throwError } from 'rxjs';

import { DocumentApiService } from '../../../../shared/services/documents/document-api.service';
import { DocumentListComponent } from './document-list.component';

describe('DocumentListComponent', () => {
  let fixture: ComponentFixture<DocumentListComponent>;
  let component: DocumentListComponent;
  let apiMock: {
    selectedFinancialYearId: ReturnType<typeof signal<string | null>>;
    selectFinancialYear: (financialYearId: string | null) => void;
    listDocuments: () => ReturnType<DocumentApiService['listDocuments']>;
    listFinancialYears: () => ReturnType<DocumentApiService['listFinancialYears']>;
    listDocumentTypes: () => ReturnType<DocumentApiService['listDocumentTypes']>;
    assignDocumentFinancialYear: ReturnType<typeof vi.fn>;
    assignDocumentToFinancialYear: ReturnType<typeof vi.fn>;
  };

  const financialYears = [
    {
    id: 'fy-2026-27',
    year_code: '2026-27',
    start_date: '2026-07-01',
    end_date: '2027-06-30',
    status: 'active',
    },
    {
    id: 'fy-2025-26',
    year_code: '2025-26',
    start_date: '2025-07-01',
    end_date: '2026-06-30',
    status: 'active',
    },
  ];

  const documentType = {
    id: 'type-uuid',
    code: 'invoice',
    name: 'Invoice',
    description: null,
    status: 'active',
  };

  const document = {
    id: 'document-uuid',
    financial_year_id: 'fy-2025-26',
    document_type_id: 'type-uuid',
    original_filename: 'office-receipt.pdf',
    mime_type: 'application/pdf',
    size_bytes: 2048,
    uploaded_at: '2026-10-08T10:30:00Z',
    status: 'active',
  };

  beforeEach(async () => {
    apiMock = {
      selectedFinancialYearId: signal<string | null>(null),
      selectFinancialYear: (financialYearId) =>
        apiMock.selectedFinancialYearId.set(financialYearId),
      listDocuments: () => of([document]),
      listFinancialYears: () => of(financialYears),
      listDocumentTypes: () => of([documentType]),
      assignDocumentFinancialYear: vi.fn((documentId, yearCode) => of({
        ...document,
        id: documentId,
        financial_year_id: yearCode === '2025-26' ? 'fy-2025-26' : 'fy-2026-27',
        status: 'review_required',
      })),
      assignDocumentToFinancialYear: vi.fn((documentId, yearId) => of({
        ...document,
        id: documentId,
        financial_year_id: yearId,
        status: 'review_required',
      })),
    };

    await TestBed.configureTestingModule({
      imports: [DocumentListComponent],
      providers: [
        provideRouter([]),
        { provide: DocumentApiService, useValue: apiMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(DocumentListComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should load documents from the API', () => {
    expect(component.documents.length).toBe(1);
    expect(fixture.nativeElement.textContent).toContain('office-receipt.pdf');
  });

  it('refreshes document processing statuses automatically', async () => {
    fixture.destroy();
    vi.useFakeTimers();
    const processingDocument = { ...document, status: 'processing' };
    apiMock.listDocuments = vi.fn()
      .mockReturnValueOnce(of([document]))
      .mockReturnValue(of([processingDocument]));

    fixture = TestBed.createComponent(DocumentListComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    await vi.advanceTimersByTimeAsync(5000);
    fixture.detectChanges();

    expect(apiMock.listDocuments).toHaveBeenCalledTimes(2);
    expect(component.documents[0].status).toBe('processing');

    fixture.destroy();
    vi.useRealTimers();
  });

  it('should identify all years in the page header and show the year for each document', () => {
    const element = fixture.nativeElement as HTMLElement;

    expect(element.querySelector('.eyebrow')?.textContent).toContain('All financial years');
    expect(element.querySelector('.document-financial-year')?.textContent)
      .toContain('FY 2025 - 26');
  });

  it('should display the document count', () => {
    expect(fixture.nativeElement.textContent).toContain('1 document');
  });

  it('should apply the shared financial-year selection to the document list', () => {
    component.documents = [
      document,
      { ...document, id: 'document-other-year', financial_year_id: 'fy-2026-27' },
    ];
    apiMock.selectedFinancialYearId.set('fy-2025-26');
    fixture.detectChanges();

    expect(component.filteredDocuments).toEqual([document]);
    expect(fixture.nativeElement.textContent).toContain('FY 2025 - 26');
    expect(fixture.nativeElement.textContent).not.toContain('document-other-year');
  });

  it('should keep unassigned documents visible under a selected year', () => {
    const unassignedDocument = { ...document, id: 'unassigned', financial_year_id: null };
    component.documents = [document, unassignedDocument];
    apiMock.selectedFinancialYearId.set('fy-2025-26');

    expect(component.filteredDocuments).toEqual([document, unassignedDocument]);
  });

  it('should suggest a financial year from the extracted invoice date', () => {
    const unassignedDocument = {
      ...document,
      financial_year_id: null,
      latest_extraction: {
        id: 'extraction-1',
        status: 'completed',
        provider: 'local',
        output: {
          normalized: {
            fields: { invoice_date: { value: '2025-07-03' } },
          },
        },
      },
    };

    expect(component.getSuggestedFinancialYear(unassignedDocument)).toBe('2025-26');
  });

  it('should create and assign the confirmed suggested financial year', () => {
    const unassignedDocument = {
      ...document,
      financial_year_id: null,
      latest_extraction: {
        id: 'extraction-1',
        status: 'completed',
        provider: 'local',
        output: {
          normalized: {
            fields: { invoice_date: { value: '2025-07-03' } },
          },
        },
      },
    };
    component.documents = [unassignedDocument];

    component.confirmSuggestedFinancialYear(unassignedDocument);

    expect(apiMock.assignDocumentFinancialYear).toHaveBeenCalledWith(
      'document-uuid',
      '2025-26',
    );
    expect(component.documents[0].financial_year_id).toBe('fy-2025-26');
    expect(component.documents[0].status).toBe('review_required');
  });

  it('should display the document type, upload date and file size', () => {
    const element = fixture.nativeElement as HTMLElement;

    expect(element.textContent).toContain('Invoice');
    expect(element.textContent).toContain('8 Oct 2026');
    expect(element.textContent).toContain('2.0 KB');
  });

  it('should display uploaded status without inventing an OCR result', () => {
    const element = fixture.nativeElement as HTMLElement;

    expect(element.textContent).toContain('Uploaded');
    expect(element.textContent).not.toContain('Needs review');
    expect(element.textContent).not.toContain('Ready');
  });

  it('should show an empty state when there are no documents', () => {
    apiMock.listDocuments = () => of([]);

    fixture = TestBed.createComponent(DocumentListComponent);
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('No documents yet');
  });

  it('should show an error when the document API fails', () => {
    apiMock.listDocuments = () =>
      throwError(() => new Error('API unavailable'));

    fixture = TestBed.createComponent(DocumentListComponent);
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain(
      'Unable to load your documents',
    );
  });

  it('should provide an upload receipt link', () => {
    const element = fixture.nativeElement as HTMLElement;
    const link = element.querySelector('a[routerLink="/documents/upload"]');

    expect(link).toBeTruthy();
    expect(link?.textContent).toContain('Upload receipt');
  });
});