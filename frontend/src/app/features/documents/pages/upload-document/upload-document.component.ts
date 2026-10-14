import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  OnInit,
  inject,
} from '@angular/core';
import { Router, RouterLink } from '@angular/router';

import {
  DocumentApiService,
  DocumentTypeDto,
  FinancialYearDto,
} from '../../../../shared/services/documents/document-api.service';

@Component({
  selector: 'app-upload-document',
  imports: [RouterLink],
  templateUrl: './upload-document.component.html',
  styleUrl: './upload-document.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class UploadDocumentComponent implements OnInit {
  private readonly api = inject(DocumentApiService);
  private readonly router = inject(Router);
  private readonly cdr = inject(ChangeDetectorRef);

  financialYears: FinancialYearDto[] = [];
  documentTypes: DocumentTypeDto[] = [];

  selectedFinancialYearId = '';
  selectedDocumentTypeId = '';
  selectedFile: File | null = null;

  isDragging = false;
  isLoadingOptions = true;
  isUploading = false;
  errorMessage = '';

  readonly acceptedTypes = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/tiff',
  ];

  readonly acceptedExtensions = '.pdf,.jpg,.jpeg,.png,.webp,.tif,.tiff';
  readonly maxFileSize = 20 * 1024 * 1024;

  get financialYearLabel(): string {
    const year = this.financialYears.find(
      (item) => item.id === this.selectedFinancialYearId,
    );

    return year ? `FY ${year.year_code}` : 'Select financial year';
  }

  ngOnInit(): void {
    this.api.listFinancialYears().subscribe({
      next: (years) => {
        this.financialYears = years;
        if (!this.selectedFinancialYearId && years.length > 0) {
          this.selectedFinancialYearId = years[0].id;
        }
        this.isLoadingOptions = false;
        this.cdr.markForCheck();
      },
      error: () => {
        this.errorMessage = 'Unable to load financial years. Please try again.';
        this.isLoadingOptions = false;
        this.cdr.markForCheck();
      },
    });

    this.api.listDocumentTypes().subscribe({
      next: (types) => {
        this.documentTypes = types;
        if (!this.selectedDocumentTypeId && types.length > 0) {
          this.selectedDocumentTypeId = types[0].id;
        }
        this.cdr.markForCheck();
      },
      error: () => {
        this.errorMessage = 'Unable to load document types. Please try again.';
        this.cdr.markForCheck();
      },
    });
  }

  onFinancialYearChange(event: Event): void {
    this.selectedFinancialYearId =
      (event.target as HTMLSelectElement).value;
  }

  onDocumentTypeChange(event: Event): void {
    this.selectedDocumentTypeId =
      (event.target as HTMLSelectElement).value;
  }

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.selectFile(input.files?.[0] ?? null);
    input.value = '';
  }

  onDragOver(event: DragEvent): void {
    event.preventDefault();
    event.stopPropagation();
    this.isDragging = true;
  }

  onDragLeave(event: DragEvent): void {
    event.preventDefault();
    event.stopPropagation();
    this.isDragging = false;
  }

  onDrop(event: DragEvent): void {
    event.preventDefault();
    event.stopPropagation();
    this.isDragging = false;
    this.selectFile(event.dataTransfer?.files?.[0] ?? null);
  }

  removeFile(): void {
    this.selectedFile = null;
    this.errorMessage = '';
  }

  continue(): void {
    if (this.isUploading) {
      return;
    }

    if (!this.selectedFile) {
      this.errorMessage = 'Please choose a file first.';
      return;
    }

    if (!this.selectedFinancialYearId || !this.selectedDocumentTypeId) {
      this.errorMessage =
        'An active financial year and document type are required before uploading.';
      return;
    }

    this.errorMessage = '';
    this.isUploading = true;

    this.api.uploadDocument(
      this.selectedFinancialYearId,
      this.selectedDocumentTypeId,
      this.selectedFile,
    ).subscribe({
      next: () => {
        this.isUploading = false;
        this.cdr.markForCheck();
        void this.router.navigateByUrl('/documents');
      },
      error: (error: { status?: number; error?: { message?: string } }) => {
        this.isUploading = false;

        if (error.status === 401) {
          this.errorMessage = 'Your session may have expired. Please sign in again.';
        } else if (error.status === 422) {
          this.errorMessage =
            'The upload was rejected. Check the selected file and required fields.';
        } else {
          this.errorMessage =
            error.error?.message ??
            'Upload failed. Please try again.';
        }

        this.cdr.markForCheck();
      },
    });
  }

  formatFileSize(bytes: number): string {
    if (bytes < 1024) {
      return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
      return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  }

  private selectFile(file: File | null): void {
    this.errorMessage = '';

    if (!file) {
      return;
    }

    if (!this.isSupportedFile(file)) {
      this.errorMessage =
        'Please choose a PDF, JPG, PNG, WEBP, or TIFF file.';
      return;
    }

    if (file.size > this.maxFileSize) {
      this.errorMessage = 'The maximum file size is 20 MB.';
      return;
    }

    this.selectedFile = file;
  }

  private isSupportedFile(file: File): boolean {
    if (this.acceptedTypes.includes(file.type)) {
      return true;
    }

    const extension = file.name.split('.').pop()?.toLowerCase();

    return ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'tif', 'tiff'].includes(
      extension ?? '',
    );
  }
}