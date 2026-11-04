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
    deleteDocument: ReturnType<typeof vi.fn>;
    confirmDocumentPayment: ReturnType<typeof vi.fn>;
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
      deleteDocument: vi.fn(() => of(void 0)),
      confirmDocumentPayment: vi.fn((documentId, paymentDate, paymentMethod, amount) => of({
        ...document,
        id: documentId,
        payment_status: 'paid',
        payment_date: paymentDate,
        payment_method: paymentMethod,
        payment_amount: amount,
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
    expect(element.querySelector('.status-uploaded')).toBeTruthy();
    expect(element.textContent).not.toContain('Needs review');
    expect(element.textContent).not.toContain('Ready');
  });

  it('uses a distinct attention style for documents that need review', () => {
    component.documents = [{ ...document, status: 'review_required' }];
    fixture.detectChanges();

    expect(component.getStatusClass('review_required')).toBe('status status-review');
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

  it('permanently deletes a document after confirmation and removes it from the list', () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);

    component.deleteDocument(document);
    fixture.detectChanges();

    expect(apiMock.deleteDocument).toHaveBeenCalledWith(document.id);
    expect(component.documents).toEqual([]);
    expect(fixture.nativeElement.textContent).toContain('No documents yet');
  });

  it('does not delete a document when confirmation is declined', () => {
    vi.spyOn(window, 'confirm').mockReturnValue(false);

    component.deleteDocument(document);

    expect(apiMock.deleteDocument).not.toHaveBeenCalled();
    expect(component.documents).toEqual([document]);
  });

  it('keeps the document and shows the API error when deletion fails', () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    apiMock.deleteDocument.mockReturnValue(
      throwError(() => ({ error: { message: 'Document is locked.' } })),
    );

    component.deleteDocument(document);
    fixture.detectChanges();

    expect(component.documents).toEqual([document]);
    expect(component.deleteErrorMessage).toBe('Document is locked.');
    expect(component.failedDeletionDocumentId).toBe(document.id);
    expect(fixture.nativeElement.textContent).toContain('Document is locked.');
    expect(fixture.nativeElement.textContent).toContain('office-receipt.pdf');
  });

  it('confirms payment and records payment details without duplicating a document', () => {
    component.openPaymentConfirmation(document);
    component.paymentDate = '2026-10-06';
    component.paymentMethod = 'bpay';
    component.paymentAmount = '2085.63';
    component.confirmPayment(document);
    fixture.detectChanges();

    expect(apiMock.confirmDocumentPayment).toHaveBeenCalledWith(
      document.id,
      '2026-10-06',
      'bpay',
      '2085.63',
    );
    expect(component.documents).toHaveLength(1);
    expect(component.documents[0].payment_status).toBe('paid');
    expect(fixture.nativeElement.textContent).toContain('Paid');
    expect(fixture.nativeElement.textContent).toContain('2,085.63 AUD');
    expect(fixture.nativeElement.textContent).toContain('BPAY');
  });

  it('prefills the payment dialog with the reviewed date and amount when available', () => {
    component.openPaymentConfirmation({
      ...document,
      payment_suggestion: { date: '2026-08-14', amount: '548.80' },
    });

    expect(component.paymentDate).toBe('2026-08-14');
    expect(component.paymentAmount).toBe('548.80');
  });

  it('keeps default payment values when no date or amount was detected', () => {
    component.openPaymentConfirmation({
      ...document,
      payment_suggestion: { date: null, amount: null },
    });

    expect(component.paymentDate).toMatch(/^\d{4}-\d{2}-\d{2}$/);
    expect(component.paymentAmount).toBe('');
  });

  it('does not open payment confirmation until a financial year is assigned', () => {
    const unassignedDocument = { ...document, financial_year_id: null };

    component.openPaymentConfirmation(unassignedDocument);

    expect(component.confirmingPaymentDocumentId).toBeNull();
  });
});