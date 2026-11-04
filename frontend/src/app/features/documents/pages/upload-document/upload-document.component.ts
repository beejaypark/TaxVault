import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  OnInit,
  inject,
} from '@angular/core';
import { Router } from '@angular/router';
import { EMPTY, from } from 'rxjs';
import { catchError, concatMap, finalize, tap } from 'rxjs/operators';

import { TaxIconComponent } from '../../../../shared/components/tax-icon/tax-icon.component';
import { BackNavigationComponent } from '../../../../shared/components/back-navigation/back-navigation.component';
import { formatFinancialYear } from '../../../../shared/utils/financial-year';
import {
  DocumentApiService,
  DocumentTypeDto,
  FinancialYearDto,
} from '../../../../shared/services/documents/document-api.service';

@Component({
  selector: 'app-upload-document',
  imports: [TaxIconComponent, BackNavigationComponent],
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
  selectedFiles: UploadFile[] = [];

  isDragging = false;
  isLoadingFinancialYears = true;
  isLoadingDocumentTypes = true;
  isUploading = false;
  errorMessage = '';
  inboundEmail = '';
  copied = false;

  readonly acceptedTypes = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/tiff',
  ];

  readonly acceptedExtensions = '.pdf,.jpg,.jpeg,.png,.webp,.tif,.tiff';
  readonly maxFileSize = 20 * 1024 * 1024;

  get isLoadingOptions(): boolean {
    return this.isLoadingFinancialYears || this.isLoadingDocumentTypes;
  }

  get financialYearLabel(): string {
    const year = this.financialYears.find(
      (item) => item.id === this.selectedFinancialYearId,
    );

    return year
      ? `FY ${formatFinancialYear(year.year_code)}`
      : 'Financial year pending';
  }

  formatYearCode(yearCode: string): string {
    return formatFinancialYear(yearCode);
  }

  get totalFileSize(): number {
    return this.selectedFiles.reduce((total, item) => total + item.file.size, 0);
  }

  get pendingFileCount(): number {
    return this.selectedFiles.filter(
      (item) => item.status === 'pending' || item.status === 'failed',
    ).length;
  }

  get duplicateFileCount(): number {
    return this.selectedFiles.filter((item) => item.status === 'duplicate').length;
  }

  ngOnInit(): void {
    this.api.getInboundEmailAddress().subscribe({
      next: (address) => {
        this.inboundEmail = address;
        this.cdr.markForCheck();
      },
    });

    this.api.listFinancialYears().subscribe({
      next: (years) => {
        this.financialYears = years;
        const currentYearId = this.api.selectedFinancialYearId();
        this.selectedFinancialYearId = years.some(
          (year) => year.id === currentYearId,
        )
          ? years.find((year) => year.id === currentYearId)?.id ?? ''
          : years[0]?.id ?? '';
        this.api.selectFinancialYear(this.selectedFinancialYearId || null);
        this.isLoadingFinancialYears = false;
        this.cdr.markForCheck();
      },
      error: () => {
        this.errorMessage = 'Unable to load financial years. Please try again.';
        this.isLoadingFinancialYears = false;
        this.cdr.markForCheck();
      },
    });

    this.api.listDocumentTypes().subscribe({
      next: (types) => {
        this.documentTypes = types;
        if (!this.selectedDocumentTypeId && types.length > 0) {
          this.selectedDocumentTypeId = types[0].id;
        }
        this.isLoadingDocumentTypes = false;
        this.cdr.markForCheck();
      },
      error: () => {
        this.errorMessage = 'Unable to load document types. Please try again.';
        this.isLoadingDocumentTypes = false;
        this.cdr.markForCheck();
      },
    });
  }

  onFinancialYearChange(event: Event): void {
    this.selectedFinancialYearId =
      (event.target as HTMLSelectElement).value;
    this.api.selectFinancialYear(this.selectedFinancialYearId || null);
  }

  onDocumentTypeChange(event: Event): void {
    this.selectedDocumentTypeId =
      (event.target as HTMLSelectElement).value;
  }

  async copyInboundEmail(): Promise<void> {
    await navigator.clipboard?.writeText(this.inboundEmail);
    this.copied = true;
    this.cdr.markForCheck();
  }

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.addFiles(Array.from(input.files ?? []));
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
    this.addFiles(Array.from(event.dataTransfer?.files ?? []));
  }

  removeFile(index: number): void {
    if (this.isUploading) {
      return;
    }

    this.selectedFiles = this.selectedFiles.filter((_, itemIndex) => itemIndex !== index);
    this.errorMessage = '';
  }

  continue(): void {
    if (this.isUploading) {
      return;
    }

    const filesToUpload = this.selectedFiles.filter(
      (item) => item.status === 'pending' || item.status === 'failed',
    );

    if (filesToUpload.length === 0) {
      this.errorMessage = 'Please choose at least one file to upload.';
      return;
    }

    if (!this.selectedDocumentTypeId) {
      this.errorMessage =
        'An active document type is required before uploading.';
      return;
    }

    this.errorMessage = '';
    this.isUploading = true;

    from(filesToUpload).pipe(
      concatMap((item) => {
        item.status = 'uploading';
        item.error = '';
        this.cdr.markForCheck();

        return this.api.uploadDocument(
          this.selectedFinancialYearId,
          this.selectedDocumentTypeId,
          item.file,
        ).pipe(
          tap(() => {
            item.status = 'uploaded';
            this.cdr.markForCheck();
          }),
          catchError((error: {
            status?: number;
            error?: {
              code?: string;
              message?: string;
              duplicate_document?: DuplicateDocument;
            };
          }) => {
            if (
              error.status === 409
              && error.error?.code === 'duplicate_document'
              && error.error.duplicate_document
            ) {
              item.status = 'duplicate';
              item.duplicate = error.error.duplicate_document;
              item.error = '';
            } else {
              item.status = 'failed';
              item.error = error.status === 401
                ? 'Session expired.'
                : error.status === 422
                  ? 'Rejected: check file type, size, and upload fields.'
                  : error.error?.message ?? 'Upload failed.';
            }
            this.cdr.markForCheck();
            return EMPTY;
          }),
        );
      }),
      finalize(() => {
        this.isUploading = false;
        const failedCount = this.selectedFiles.filter(
          (item) => item.status === 'failed',
        ).length;
        const duplicateCount = this.duplicateFileCount;
        if (failedCount === 0 && duplicateCount === 0) {
          void this.router.navigateByUrl('/documents');
        } else {
          const messages = [];
          if (failedCount > 0) {
            messages.push(
              `${failedCount} file${failedCount === 1 ? '' : 's'} failed. Fix the listed issues or retry them.`,
            );
          }
          if (duplicateCount > 0) {
            messages.push(
              `${duplicateCount} duplicate file${duplicateCount === 1 ? ' was' : 's were'} not uploaded; the existing document was kept.`,
            );
          }
          this.errorMessage = messages.join(' ');
        }
        this.cdr.markForCheck();
      }),
    ).subscribe();
  }

  viewDocuments(): void {
    void this.router.navigateByUrl('/documents');
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

  private addFiles(files: File[]): void {
    this.errorMessage = '';

    if (files.length === 0) {
      return;
    }

    const accepted: UploadFile[] = [];
    const rejected: string[] = [];
    for (const file of files) {
      if (!this.isSupportedFile(file)) {
        rejected.push(`${file.name}: unsupported file type`);
      } else if (file.size > this.maxFileSize) {
        rejected.push(`${file.name}: larger than 20 MB`);
      } else {
        accepted.push({ file, status: 'pending', error: '' });
      }
    }

    this.selectedFiles = [...this.selectedFiles, ...accepted];
    if (rejected.length > 0) {
      this.errorMessage = `${rejected.length} file${rejected.length === 1 ? '' : 's'} not added: ${rejected.slice(0, 3).join('; ')}${rejected.length > 3 ? '; …' : ''}.`;
    }
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

interface UploadFile {
  file: File;
  status: 'pending' | 'uploading' | 'uploaded' | 'failed' | 'duplicate';
  error: string;
  duplicate?: DuplicateDocument;
}

interface DuplicateDocument {
  id: string;
  original_filename: string | null;
  financial_year_code: string | null;
  uploaded_at: string;
  status: string;
}