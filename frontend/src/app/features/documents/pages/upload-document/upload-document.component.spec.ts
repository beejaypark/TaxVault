import { ComponentFixture, TestBed } from '@angular/core/testing';
import { signal } from '@angular/core';
import { provideRouter, Router } from '@angular/router';
import { of, throwError } from 'rxjs';

import {
  DocumentApiService,
  DocumentDto,
} from '../../../../shared/services/documents/document-api.service';
import { UploadDocumentComponent } from './upload-document.component';

describe('UploadDocumentComponent', () => {
  let fixture: ComponentFixture<UploadDocumentComponent>;
  let component: UploadDocumentComponent;
  let documentApi: {
    listFinancialYears: () => ReturnType<DocumentApiService['listFinancialYears']>;
    listDocumentTypes: () => ReturnType<DocumentApiService['listDocumentTypes']>;
    selectedFinancialYearId: ReturnType<typeof signal<string | null>>;
    selectFinancialYear: (financialYearId: string | null) => void;
    uploadDocument: ReturnType<typeof vi.fn>;
  };
  let selectedFinancialYearId: ReturnType<typeof signal<string | null>>;
  let router: Router;

  beforeEach(async () => {
    selectedFinancialYearId = signal<string | null>(null);
    documentApi = {
      listFinancialYears: () => of([
        {
          id: 'financial-year-1',
          year_code: '2026-27',
          start_date: '2026-07-01',
          end_date: '2027-06-30',
          status: 'active',
        },
        {
          id: 'financial-year-2',
          year_code: '2025-26',
          start_date: '2025-07-01',
          end_date: '2026-06-30',
          status: 'active',
        },
      ]),
      listDocumentTypes: () => of([{
        id: 'document-type-1',
        code: 'receipt',
        name: 'Receipt',
        status: 'active',
      }]),
      selectedFinancialYearId,
      selectFinancialYear: (financialYearId) =>
        selectedFinancialYearId.set(financialYearId),
      uploadDocument: vi.fn().mockReturnValue(of({} as DocumentDto)),
    };

    await TestBed.configureTestingModule({
      imports: [UploadDocumentComponent],
      providers: [
        provideRouter([]),
        { provide: DocumentApiService, useValue: documentApi },
      ],
    }).compileComponents();

    router = TestBed.inject(Router);
    vi.spyOn(router, 'navigateByUrl');
    fixture = TestBed.createComponent(UploadDocumentComponent);
    component = fixture.componentInstance;

    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should display the upload heading', () => {
    const element = fixture.nativeElement as HTMLElement;

    expect(element.textContent).toContain('Upload documents');
    expect(element.textContent).toContain('Upload documents in bulk');
  });

  it('should display supported file types', () => {
    const element = fixture.nativeElement as HTMLElement;

    expect(element.textContent).toContain('PDF');
    expect(element.textContent).toContain('JPG');
    expect(element.textContent).toContain('PNG');
    expect(element.textContent).toContain('WEBP');
    expect(element.textContent).toContain('TIFF');
  });

  it('should enable the financial year and document type selectors when options load', () => {
    const element = fixture.nativeElement as HTMLElement;
    const selects = element.querySelectorAll('select');

    expect(selects.length).toBe(2);
    expect((selects[0] as HTMLSelectElement).disabled).toBe(false);
    expect((selects[1] as HTMLSelectElement).disabled).toBe(false);
    expect(selects[0].textContent).toContain('FY 2026 - 27');
    expect(selects[0].textContent).toContain('FY 2025 - 26');
    expect(selects[1].textContent).toContain('Receipt');
    expect((selects[0] as HTMLSelectElement).value).toBe('financial-year-1');
    expect((selects[1] as HTMLSelectElement).value).toBe('document-type-1');
    expect(selects[0].textContent).not.toContain('Select financial year');
    expect(selects[1].textContent).not.toContain('Select document type');
  });

  it('should use the financial year selected in the workspace', () => {
    selectedFinancialYearId.set('financial-year-2');
    fixture = TestBed.createComponent(UploadDocumentComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    expect(component.selectedFinancialYearId).toBe('financial-year-2');
    expect(component.financialYearLabel).toBe('FY 2025 - 26');
    expect(
      (fixture.nativeElement.querySelectorAll('select')[0] as HTMLSelectElement).value,
    ).toBe('financial-year-2');
  });

  it('should update the workspace year when changed on the upload form', () => {
    const financialYearSelect = fixture.nativeElement.querySelector(
      'select',
    ) as HTMLSelectElement;
    financialYearSelect.value = 'financial-year-2';
    financialYearSelect.dispatchEvent(new Event('change'));

    expect(selectedFinancialYearId()).toBe('financial-year-2');
  });

  it('should allow upload setup when no year is configured', () => {
    documentApi.listFinancialYears = () => of([]);
    fixture = TestBed.createComponent(UploadDocumentComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    const element = fixture.nativeElement as HTMLElement;
    const financialYearSelect = element.querySelector('select');

    expect(financialYearSelect?.disabled).toBe(true);
    expect(element.textContent).toContain('No financial years yet');
    expect(element.textContent).toContain('after local text extraction');
  });

  it('should allow upload without a financial year so extraction can suggest one', () => {
    documentApi.listFinancialYears = () => of([]);
    fixture = TestBed.createComponent(UploadDocumentComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    const file = new File(['invoice'], 'invoice.pdf', { type: 'application/pdf' });
    component['addFiles']([file]);
    component.continue();

    expect(documentApi.uploadDocument).toHaveBeenCalledWith(
      '',
      'document-type-1',
      file,
    );
    expect(router.navigateByUrl).toHaveBeenCalledWith('/documents');
  });

  it('should accept multiple supported files', () => {
    const file = new File(
      ['receipt'],
      'bunnings-receipt.pdf',
      { type: 'application/pdf' },
    );
    const secondFile = new File(['invoice'], 'invoice.png', { type: 'image/png' });

    component['addFiles']([file, secondFile]);

    expect(component.selectedFiles.map((item) => item.file)).toEqual([file, secondFile]);
    expect(component.errorMessage).toBe('');
  });

  it('should keep supported files and report unsupported files in a mixed selection', () => {
    const file = new File(['receipt'], 'receipt.pdf', { type: 'application/pdf' });
    const unsupported = new File(['document'], 'document.exe', {
      type: 'application/octet-stream',
    });

    component['addFiles']([file, unsupported]);

    expect(component.selectedFiles.map((item) => item.file)).toEqual([file]);
    expect(component.errorMessage).toContain('document.exe');
  });

  it('should reject files larger than 20 MB', () => {
    const file = new File([new Uint8Array(20 * 1024 * 1024 + 1)], 'large.pdf', {
      type: 'application/pdf',
    });

    component['addFiles']([file]);

    expect(component.selectedFiles).toEqual([]);
    expect(component.errorMessage).toContain('larger than 20 MB');
  });

  it('should remove the selected file at the requested index', () => {
    const file = new File(
      ['receipt'],
      'receipt.jpg',
      { type: 'image/jpeg' },
    );
    const secondFile = new File(['bill'], 'bill.pdf', { type: 'application/pdf' });

    component['addFiles']([file, secondFile]);
    component.removeFile(0);

    expect(component.selectedFiles.map((item) => item.file)).toEqual([secondFile]);
    expect(component.errorMessage).toBe('');
  });

  it('should upload all selected files and navigate when all succeed', () => {
    const files = [
      new File(['first'], 'first.pdf', { type: 'application/pdf' }),
      new File(['second'], 'second.pdf', { type: 'application/pdf' }),
    ];
    component['addFiles'](files);

    component.continue();

    expect(documentApi.uploadDocument).toHaveBeenCalledTimes(2);
    expect(documentApi.uploadDocument.mock.calls[0][2]).toBe(files[0]);
    expect(documentApi.uploadDocument.mock.calls[1][2]).toBe(files[1]);
    expect(router.navigateByUrl).toHaveBeenCalledWith('/documents');
  });

  it('should retain successful uploads and retry only failed files', () => {
    const files = [
      new File(['first'], 'first.pdf', { type: 'application/pdf' }),
      new File(['second'], 'second.pdf', { type: 'application/pdf' }),
    ];
    documentApi.uploadDocument
      .mockReturnValueOnce(of({} as DocumentDto))
      .mockReturnValueOnce(throwError(() => ({ status: 422 })));

    component['addFiles'](files);
    component.continue();

    expect(component.selectedFiles.map((item) => item.status)).toEqual([
      'uploaded',
      'failed',
    ]);
    expect(router.navigateByUrl).not.toHaveBeenCalled();

    documentApi.uploadDocument.mockReturnValue(of({} as DocumentDto));
    component.continue();

    expect(documentApi.uploadDocument).toHaveBeenCalledTimes(3);
    expect(documentApi.uploadDocument.mock.calls[2][2]).toBe(files[1]);
    expect(router.navigateByUrl).toHaveBeenCalledWith('/documents');
  });

  it('should mark a duplicate response and keep the existing document', () => {
    const file = new File(['same document'], 'duplicate.pdf', {
      type: 'application/pdf',
    });
    documentApi.uploadDocument.mockReturnValue(throwError(() => ({
      status: 409,
      error: {
        code: 'duplicate_document',
        message: 'This file was already uploaded to your account.',
        duplicate_document: {
          id: 'existing-document',
          original_filename: 'original.pdf',
          financial_year_code: '2025-26',
          uploaded_at: '2026-11-02T00:00:00Z',
          status: 'review_required',
        },
      },
    })));
    component['addFiles']([file]);

    component.continue();

    expect(component.selectedFiles[0].status).toBe('duplicate');
    expect(component.duplicateFileCount).toBe(1);
    expect(component.selectedFiles[0].duplicate?.original_filename).toBe('original.pdf');
    expect(component.errorMessage).toContain('duplicate file was not uploaded');
    expect(router.navigateByUrl).not.toHaveBeenCalled();

    component.viewDocuments();

    expect(router.navigateByUrl).toHaveBeenCalledWith('/documents');
  });

  it('should format file sizes', () => {
    expect(component.formatFileSize(500)).toBe('500 B');
    expect(component.formatFileSize(2048)).toBe('2.0 KB');
    expect(component.formatFileSize(2 * 1024 * 1024)).toBe('2.0 MB');
  });
});
