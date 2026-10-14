import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { UploadDocumentComponent } from './upload-document.component';

describe('UploadDocumentComponent', () => {
  let fixture: ComponentFixture<UploadDocumentComponent>;
  let component: UploadDocumentComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [UploadDocumentComponent],
      providers: [
        provideRouter([]),
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(UploadDocumentComponent);
    component = fixture.componentInstance;

    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should display the upload heading', () => {
    const element = fixture.nativeElement as HTMLElement;

    expect(element.textContent).toContain('Upload receipt');
    expect(element.textContent).toContain('Upload your receipt');
  });

  it('should display supported file types', () => {
    const element = fixture.nativeElement as HTMLElement;

    expect(element.textContent).toContain('PDF');
    expect(element.textContent).toContain('JPG');
    expect(element.textContent).toContain('PNG');
    expect(element.textContent).toContain('WEBP');
    expect(element.textContent).toContain('TIFF');
  });

  it('should accept a supported file', () => {
    const file = new File(
      ['receipt'],
      'bunnings-receipt.pdf',
      { type: 'application/pdf' },
    );

    component['selectFile'](file);

    expect(component.selectedFile).toBe(file);
    expect(component.errorMessage).toBe('');
  });

  it('should reject an unsupported file', () => {
    const file = new File(
      ['document'],
      'document.exe',
      { type: 'application/octet-stream' },
    );

    component['selectFile'](file);

    expect(component.selectedFile).toBeNull();
    expect(component.errorMessage).toContain('PDF');
  });

  it('should remove the selected file', () => {
    const file = new File(
      ['receipt'],
      'receipt.jpg',
      { type: 'image/jpeg' },
    );

    component['selectFile'](file);
    component.removeFile();

    expect(component.selectedFile).toBeNull();
    expect(component.errorMessage).toBe('');
  });

  it('should format file sizes', () => {
    expect(component.formatFileSize(500)).toBe('500 B');
    expect(component.formatFileSize(2048)).toBe('2.0 KB');
    expect(component.formatFileSize(2 * 1024 * 1024)).toBe('2.0 MB');
  });
});
