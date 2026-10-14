import { provideRouter } from '@angular/router';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { of, throwError } from 'rxjs';

import { DocumentApiService } from '../../../../shared/services/documents/document-api.service';
import { DocumentListComponent } from './document-list.component';

describe('DocumentListComponent', () => {
  let fixture: ComponentFixture<DocumentListComponent>;
  let component: DocumentListComponent;
  let apiMock: {
    listDocuments: () => ReturnType<DocumentApiService['listDocuments']>;
    listFinancialYears: () => ReturnType<DocumentApiService['listFinancialYears']>;
    listDocumentTypes: () => ReturnType<DocumentApiService['listDocumentTypes']>;
  };

  const financialYear = {
    id: 'fy-uuid',
    year_code: '2026-27',
    start_date: '2026-07-01',
    end_date: '2027-06-30',
    status: 'active',
  };

  const documentType = {
    id: 'type-uuid',
    code: 'invoice',
    name: 'Invoice',
    description: null,
    status: 'active',
  };

  const document = {
    id: 'document-uuid',
    financial_year_id: 'fy-uuid',
    document_type_id: 'type-uuid',
    original_filename: 'office-receipt.pdf',
    mime_type: 'application/pdf',
    size_bytes: 2048,
    uploaded_at: '2026-10-08T10:30:00Z',
    status: 'active',
  };

  beforeEach(async () => {
    apiMock = {
      listDocuments: () => of([document]),
      listFinancialYears: () => of([financialYear]),
      listDocumentTypes: () => of([documentType]),
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

  it('should display the financial year returned by the API', () => {
    expect(fixture.nativeElement.textContent).toContain('FY 2026-27');
  });

  it('should display the document count', () => {
    expect(fixture.nativeElement.textContent).toContain('1 document');
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