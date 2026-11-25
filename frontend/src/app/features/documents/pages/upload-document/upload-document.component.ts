import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  OnInit,
  inject,
} from '@angular/core';
import { Router } from '@angular/router';
import { EMPTY, firstValueFrom, from } from 'rxjs';
import { catchError, concatMap, finalize, tap } from 'rxjs/operators';

import { TaxIconComponent } from '../../../../shared/components/tax-icon/tax-icon.component';
import { BackNavigationComponent } from '../../../../shared/components/back-navigation/back-navigation.component';
import { formatFinancialYear } from '../../../../shared/utils/financial-year';
import {
  DocumentApiService,
  DocumentTypeDto,
  FinancialYearDto,
  StorageProviderDto,
  StorageConnectionDto,
  StorageItemDto,
} from '../../../../shared/services/documents/document-api.service';
import { TranslatePipe } from '../../../../shared/i18n/translate.pipe';

@Component({
  selector: 'app-upload-document',
  imports: [TaxIconComponent, BackNavigationComponent, TranslatePipe],
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
  storageProviders: StorageProviderDto[] = [];
  defaultStorageProvider = 'taxvault';
  oneDriveConnections: StorageConnectionDto[] = [];
  selectedOneDriveConnectionId = '';
  oneDriveItems: StorageItemDto[] = [];
  oneDriveOpen = false;
  oneDriveLoading = false;
  oneDriveImportingItemId: string | null = null;
  oneDriveFolderImportingId: string | null = null;
  oneDriveFolderImportLabel = 'Importing…';
  oneDriveImportMessage = '';
  oneDrivePath: { id: string; name: string }[] = [{ id: 'root', name: 'OneDrive' }];
  googleDriveConnections: StorageConnectionDto[] = [];
  selectedGoogleDriveConnectionId = '';
  googleDriveItems: StorageItemDto[] = [];
  googleDriveOpen = false;
  googleDriveLoading = false;
  googleDrivePath: { id: string; name: string }[] = [{ id: 'root', name: 'Google Drive' }];
  googleDriveImportingItemId: string | null = null;

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

  get defaultStorageProviderLabel(): string {
    return this.storageProviders.find(
      (provider) => provider.key === this.defaultStorageProvider,
    )?.label ?? this.defaultStorageProvider;
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
    this.api.listStorageProviders().subscribe({
      next: (result) => {
        this.storageProviders = result.providers;
        this.defaultStorageProvider = result.default;
        this.cdr.markForCheck();
      },
    });
    this.api.listStorageConnections().subscribe({
      next: (connections) => {
        this.oneDriveConnections = connections.filter((connection) => connection.provider === 'onedrive' && connection.status === 'connected');
        this.selectedOneDriveConnectionId = this.oneDriveConnections[0]?.id ?? '';
        this.oneDrivePath = this.restoreExternalPath('onedrive', this.selectedOneDriveConnectionId, 'OneDrive');
        if (this.oneDrivePath.length > 1) {
          this.oneDriveOpen = true;
          this.loadOneDriveItems(this.oneDrivePath[this.oneDrivePath.length - 1].id);
        }
        this.googleDriveConnections = connections.filter((connection) => connection.provider === 'google_drive' && connection.status === 'connected');
        this.selectedGoogleDriveConnectionId = this.googleDriveConnections[0]?.id ?? '';
        this.googleDrivePath = this.restoreExternalPath('google_drive', this.selectedGoogleDriveConnectionId, 'Google Drive');
        if (this.googleDrivePath.length > 1) {
          this.googleDriveOpen = true;
          this.loadGoogleDriveItems(this.googleDrivePath[this.googleDrivePath.length - 1].id);
        }
        this.cdr.markForCheck();
      },
    });

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

  browseOneDrive(): void {
    const connection = this.oneDriveConnections.find((item) => item.id === this.selectedOneDriveConnectionId);
    if (!connection || this.oneDriveLoading) return;
    this.oneDriveOpen = true;
    this.loadOneDriveItems(this.oneDrivePath[this.oneDrivePath.length - 1]?.id ?? 'root');
  }

  openOneDriveFolder(item: StorageItemDto): void {
    if (!item.folder || this.oneDriveLoading) return;
    this.oneDrivePath = [...this.oneDrivePath, { id: item.id, name: item.name }];
    this.persistExternalPath('onedrive', this.selectedOneDriveConnectionId, this.oneDrivePath);
    this.loadOneDriveItems(item.id);
  }

  openOneDrivePath(id: string, index: number): void {
    if (this.oneDriveLoading) return;
    this.oneDrivePath = this.oneDrivePath.slice(0, index + 1);
    this.persistExternalPath('onedrive', this.selectedOneDriveConnectionId, this.oneDrivePath);
    this.loadOneDriveItems(id);
  }

  private loadOneDriveItems(parent: string): void {
    const connection = this.oneDriveConnections.find((item) => item.id === this.selectedOneDriveConnectionId);
    if (!connection) return;
    this.oneDriveLoading = true;
    this.api.listOneDriveItems(connection.id, parent).subscribe({
      next: (items) => { this.oneDriveItems = items; this.oneDriveLoading = false; this.cdr.markForCheck(); },
      error: () => { this.oneDriveLoading = false; this.errorMessage = 'Unable to load OneDrive files.'; this.cdr.markForCheck(); },
    });
  }

  browseGoogleDrive(): void { if (!this.googleDriveConnections.find((item) => item.id === this.selectedGoogleDriveConnectionId) || this.googleDriveLoading) return; this.googleDriveOpen = true; this.loadGoogleDriveItems(this.googleDrivePath[this.googleDrivePath.length - 1]?.id ?? 'root'); }
  openGoogleDriveFolder(item: StorageItemDto): void { if (!item.folder || this.googleDriveLoading) return; this.googleDrivePath = [...this.googleDrivePath, { id: item.id, name: item.name }]; this.persistExternalPath('google_drive', this.selectedGoogleDriveConnectionId, this.googleDrivePath); this.loadGoogleDriveItems(item.id); }
  openGoogleDrivePath(id: string, index: number): void { if (this.googleDriveLoading) return; this.googleDrivePath = this.googleDrivePath.slice(0, index + 1); this.persistExternalPath('google_drive', this.selectedGoogleDriveConnectionId, this.googleDrivePath); this.loadGoogleDriveItems(id); }
  private loadGoogleDriveItems(parent: string): void {
    const connection = this.googleDriveConnections.find((item) => item.id === this.selectedGoogleDriveConnectionId); if (!connection) return; this.googleDriveLoading = true;
    this.api.listGoogleDriveItems(connection.id, parent).subscribe({ next: (items) => { this.googleDriveItems = items; this.googleDriveLoading = false; this.cdr.markForCheck(); }, error: () => { this.googleDriveLoading = false; this.errorMessage = 'Unable to load Google Drive files.'; this.cdr.markForCheck(); } });
  }
  importGoogleDriveItem(item: StorageItemDto): void {
    const connection = this.googleDriveConnections.find((item) => item.id === this.selectedGoogleDriveConnectionId); if (!connection || item.folder || !this.selectedDocumentTypeId || this.googleDriveImportingItemId) return;
    this.googleDriveImportingItemId = item.id;
    this.api.importGoogleDriveDocument(connection.id, item.id, this.selectedFinancialYearId || null, this.selectedDocumentTypeId).subscribe({ next: () => { this.googleDriveImportingItemId = null; this.errorMessage = ''; this.cdr.markForCheck(); }, error: (error: { error?: { message?: string } }) => { this.googleDriveImportingItemId = null; this.errorMessage = error.error?.message ?? 'Unable to import the Google Drive file.'; this.cdr.markForCheck(); } });
  }

  importOneDriveItem(item: StorageItemDto): void {
    const connection = this.oneDriveConnections.find((item) => item.id === this.selectedOneDriveConnectionId);
    if (!connection || item.folder || !this.selectedDocumentTypeId || this.oneDriveImportingItemId) return;
    this.oneDriveImportingItemId = item.id;
    this.oneDriveImportMessage = '';
    this.api.importOneDriveDocument(connection.id, item.id, this.selectedFinancialYearId || null, this.selectedDocumentTypeId).subscribe({
      next: () => {
        this.oneDriveImportingItemId = null;
        this.errorMessage = '';
        this.oneDriveImportMessage = 'File imported. You can continue selecting files from OneDrive.';
        this.cdr.markForCheck();
      },
      error: (error: { status?: number; error?: { message?: string } }) => {
        this.oneDriveImportingItemId = null;
        this.errorMessage = '';
        this.oneDriveImportMessage = error.status === 409
          ? 'This file is already in TaxVault. You can continue with another file.'
          : error.error?.message ?? 'Unable to import the OneDrive file.';
        this.cdr.markForCheck();
      },
    });
  }

  async importOneDriveFolder(folder: StorageItemDto): Promise<void> {
    const connection = this.oneDriveConnections.find((item) => item.id === this.selectedOneDriveConnectionId);
    if (!connection || !folder.folder || !this.selectedDocumentTypeId || this.oneDriveFolderImportingId) return;

    this.oneDriveFolderImportingId = folder.id;
    this.oneDriveImportMessage = 'Scanning folder…';
    this.errorMessage = '';
    this.cdr.markForCheck();

    try {
      const files = await this.collectOneDriveFiles(connection.id, folder.id);
      let imported = 0;
      let skipped = 0;
      let failed = 0;

      for (const file of files) {
        this.oneDriveFolderImportLabel = `${imported + failed}/${files.length}`;
        this.oneDriveImportMessage = `Importing ${imported + failed + 1} of ${files.length}: ${file.name}`;
        this.cdr.markForCheck();
        try {
          await firstValueFrom(this.api.importOneDriveDocument(
            connection.id,
            file.id,
            this.selectedFinancialYearId || null,
            this.selectedDocumentTypeId,
          ));
          imported += 1;
        } catch (error: unknown) {
          if (typeof error === 'object' && error !== null && 'status' in error && error.status === 409) {
            skipped += 1;
          } else {
            failed += 1;
          }
        }
      }

      this.oneDriveImportMessage = files.length === 0
        ? 'No files were found in this folder or its subfolders.'
        : `${imported} imported${skipped > 0 ? `, ${skipped} already existed` : ''}${failed > 0 ? `, ${failed} failed` : ''}.`;
    } catch {
      this.oneDriveImportMessage = 'Unable to scan this OneDrive folder.';
    } finally {
      this.oneDriveFolderImportingId = null;
      this.oneDriveFolderImportLabel = 'Importing…';
      this.cdr.markForCheck();
    }
  }

  private async collectOneDriveFiles(connectionId: string, parentId: string): Promise<StorageItemDto[]> {
    const items = await firstValueFrom(this.api.listOneDriveItems(connectionId, parentId));
    const files: StorageItemDto[] = [];
    for (const item of items) {
      if (item.folder) {
        files.push(...await this.collectOneDriveFiles(connectionId, item.id));
      } else {
        files.push(item);
      }
    }
    return files;
  }

  selectOneDriveConnection(id: string): void {
    this.selectedOneDriveConnectionId = id;
    this.oneDrivePath = this.restoreExternalPath('onedrive', id, 'OneDrive');
    this.oneDriveOpen = false;
  }

  selectGoogleDriveConnection(id: string): void {
    this.selectedGoogleDriveConnectionId = id;
    this.googleDrivePath = this.restoreExternalPath('google_drive', id, 'Google Drive');
    this.googleDriveOpen = false;
  }

  private externalPathKey(provider: string, connectionId: string): string {
    return `taxvault.upload-path.${provider}.${connectionId}`;
  }

  private restoreExternalPath(provider: string, connectionId: string, rootName: string): { id: string; name: string }[] {
    if (!connectionId) return [{ id: 'root', name: rootName }];
    try {
      const value = JSON.parse(sessionStorage.getItem(this.externalPathKey(provider, connectionId)) ?? 'null');
      return Array.isArray(value) && value.length > 0 ? value : [{ id: 'root', name: rootName }];
    } catch {
      return [{ id: 'root', name: rootName }];
    }
  }

  private persistExternalPath(provider: string, connectionId: string, path: { id: string; name: string }[]): void {
    if (connectionId) sessionStorage.setItem(this.externalPathKey(provider, connectionId), JSON.stringify(path));
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
    // Android camera capture can return from the camera without triggering a
    // second OnPush check. Explicitly mark the view so the selection summary
    // and upload action are rendered immediately.
    this.cdr.markForCheck();
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
              errors?: Record<string, string[] | string>;
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
              const validationMessages = Object.values(error.error?.errors ?? {})
                .flatMap((messages) => Array.isArray(messages) ? messages : [messages])
                .filter(Boolean);
              item.status = 'failed';
              item.error = error.status === 401
                ? 'Session expired.'
                : error.status === 422
                  ? validationMessages[0] ?? error.error?.message ?? 'Rejected: check file type, size, and upload fields.'
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
    this.cdr.markForCheck();
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
