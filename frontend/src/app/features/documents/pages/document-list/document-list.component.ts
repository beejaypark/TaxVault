import {
  DestroyRef,
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  OnInit,
  inject,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { TaxIconComponent } from '../../../../shared/components/tax-icon/tax-icon.component';
import { formatFinancialYear } from '../../../../shared/utils/financial-year';
import { refreshEvery } from '../../../../shared/utils/status-refresh';
import {
  DocumentApiService,
  DocumentDto,
  DocumentTypeDto,
  FinancialYearDto,
} from '../../../../shared/services/documents/document-api.service';

@Component({
  selector: 'app-document-list',
  imports: [RouterLink, TaxIconComponent],
  templateUrl: './document-list.component.html',
  styleUrl: './document-list.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DocumentListComponent implements OnInit {
  private readonly api = inject(DocumentApiService);
  private readonly cdr = inject(ChangeDetectorRef);
  private readonly destroyRef = inject(DestroyRef);

  documents: DocumentDto[] = [];
  financialYears: FinancialYearDto[] = [];
  documentTypes: DocumentTypeDto[] = [];

  isLoading = true;
  errorMessage = '';
  optionsErrorMessage = '';
  assigningFinancialYearDocumentId: string | null = null;

  get financialYear(): string {
    const selectedYearId = this.api.selectedFinancialYearId();

    if (!selectedYearId) {
      return 'All financial years';
    }

    const selectedYear = this.financialYears.find(
      (year) => year.id === selectedYearId,
    );

    return selectedYear
      ? `FY ${formatFinancialYear(selectedYear.year_code)}`
      : 'Financial year';
  }

  get filteredDocuments(): DocumentDto[] {
    const selectedYearId = this.api.selectedFinancialYearId();

    return selectedYearId
      ? this.documents.filter(
          (document) => document.financial_year_id === selectedYearId,
        ).concat(this.documents.filter((document) => document.financial_year_id === null))
      : this.documents;
  }

  get hasSelectedFinancialYear(): boolean {
    return this.api.selectedFinancialYearId() !== null;
  }

  getFinancialYearLabel(document: DocumentDto): string {
    if (document.financial_year_id === null) {
      return 'Financial year needed';
    }

    const year = this.financialYears.find(
      (item) => item.id === document.financial_year_id,
    );

    return year
      ? `FY ${formatFinancialYear(year.year_code)}`
      : 'Financial year unavailable';
  }

  formatYearCode(yearCode: string): string {
    return formatFinancialYear(yearCode);
  }

  getSuggestedFinancialYear(document: DocumentDto): string | null {
    const dateValue = document.latest_extraction?.output?.normalized?.fields?.[
      'invoice_date'
    ]?.value;
    if (typeof dateValue !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(dateValue)) {
      return null;
    }

    const [yearText, monthText] = dateValue.split('-');
    const year = Number(yearText);
    const month = Number(monthText);
    const startYear = month >= 7 ? year : year - 1;
    return `${startYear}-${String((startYear + 1) % 100).padStart(2, '0')}`;
  }

  confirmSuggestedFinancialYear(document: DocumentDto): void {
    const yearCode = this.getSuggestedFinancialYear(document);
    if (!yearCode || this.assigningFinancialYearDocumentId !== null) {
      return;
    }

    this.assigningFinancialYearDocumentId = document.id;
    this.errorMessage = '';
    this.api.assignDocumentFinancialYear(document.id, yearCode).subscribe({
      next: (updatedDocument) => {
        this.replaceDocument(updatedDocument);
        if (this.api.selectedFinancialYearId() === null) {
          this.api.selectFinancialYear(updatedDocument.financial_year_id);
        }
        this.assigningFinancialYearDocumentId = null;
        this.cdr.markForCheck();
      },
      error: (error: { error?: { message?: string } }) => {
        this.assigningFinancialYearDocumentId = null;
        this.errorMessage = error.error?.message
          ?? 'Unable to create or assign the suggested financial year. Please try again.';
        this.cdr.markForCheck();
      },
    });
  }

  onManualFinancialYearChange(documentId: string, event: Event): void {
    const financialYearId = (event.target as HTMLSelectElement).value;
    if (!financialYearId || this.assigningFinancialYearDocumentId !== null) {
      return;
    }

    this.assigningFinancialYearDocumentId = documentId;
    this.errorMessage = '';
    this.api.assignDocumentToFinancialYear(documentId, financialYearId).subscribe({
      next: (updatedDocument) => {
        this.replaceDocument(updatedDocument);
        this.assigningFinancialYearDocumentId = null;
        this.cdr.markForCheck();
      },
      error: (error: { error?: { message?: string } }) => {
        this.assigningFinancialYearDocumentId = null;
        this.errorMessage = error.error?.message
          ?? 'Unable to assign the selected financial year. Please try again.';
        this.cdr.markForCheck();
      },
    });
  }

  ngOnInit(): void {
    this.api.listDocuments().subscribe({
      next: (documents) => {
        this.documents = documents;
        this.isLoading = false;
        this.cdr.markForCheck();
        this.pollProcessingDocuments();
      },
      error: () => {
        this.errorMessage = 'Unable to load your documents. Please try again.';
        this.isLoading = false;
        this.cdr.markForCheck();
      },
    });

    this.api.listFinancialYears().subscribe({
      next: (years) => {
        this.financialYears = years;
        this.cdr.markForCheck();
      },
      error: () => {
        this.optionsErrorMessage = 'Financial year information is unavailable.';
        this.cdr.markForCheck();
      },
    });

    this.api.listDocumentTypes().subscribe({
      next: (types) => {
        this.documentTypes = types;
        this.cdr.markForCheck();
      },
      error: () => {
        this.optionsErrorMessage = 'Document type information is unavailable.';
        this.cdr.markForCheck();
      },
    });
  }

  retryProcessing(document: DocumentDto): void {
    if (
      document.status !== 'processing_failed'
      && !(document.status === 'active' && !document.latest_extraction)
    ) {
      return;
    }

    const previousStatus = document.status;
    document.status = 'processing';
    this.errorMessage = '';
    this.api.retryDocumentProcessing(document.id).subscribe({
      next: (updatedDocument) => {
        this.replaceDocument(updatedDocument);
        this.pollProcessingDocuments();
        this.cdr.markForCheck();
      },
      error: () => {
        document.status = previousStatus;
        this.errorMessage = 'Unable to restart document processing. Please try again.';
        this.cdr.markForCheck();
      },
    });
  }

  getDocumentTypeName(document: DocumentDto): string {
    return this.documentTypes.find(
      (type) => type.id === document.document_type_id,
    )?.name ?? 'Tax document';
  }

  formatDate(value: string): string {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
      return 'Date unavailable';
    }

    return new Intl.DateTimeFormat('en-AU', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
    }).format(date);
  }

  formatFileSize(bytes: number): string {
    if (!Number.isFinite(bytes) || bytes < 0) {
      return 'Size unavailable';
    }

    if (bytes < 1024) {
      return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
      return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  }

  getStatusLabel(status: string): string {
    if (status === 'processing') {
      return 'Extracting text';
    }

    if (status === 'review_required') {
      return 'Review required';
    }

    if (status === 'processing_failed') {
      return 'Processing failed';
    }

    if (status === 'awaiting_financial_year') {
      return 'Financial year needed';
    }

    if (status.toLowerCase() === 'active') {
      return 'Uploaded';
    }

    return status
      .replace(/[_-]+/g, ' ')
      .replace(/\b\w/g, (character) => character.toUpperCase());
  }

  private pollProcessingDocuments(): void {
    refreshEvery(
      () => this.api.listDocuments(),
      () => {
        this.optionsErrorMessage = 'Unable to refresh document processing status. Reload the page to check again.';
        this.cdr.markForCheck();
      },
    ).pipe(
      takeUntilDestroyed(this.destroyRef),
    ).subscribe((documents) => {
      this.documents = documents;
      this.optionsErrorMessage = '';
      this.cdr.markForCheck();
    });
  }

  private replaceDocument(updatedDocument: DocumentDto): void {
    this.documents = this.documents.map((document) =>
      document.id === updatedDocument.id ? updatedDocument : document,
    );
  }
}