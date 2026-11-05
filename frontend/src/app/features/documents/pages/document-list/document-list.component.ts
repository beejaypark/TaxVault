import {
  DestroyRef,
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  OnInit,
  inject,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TaxIconComponent } from '../../../../shared/components/tax-icon/tax-icon.component';
import { BackNavigationComponent } from '../../../../shared/components/back-navigation/back-navigation.component';
import { ModalDialogComponent } from '../../../../shared/components/modal-dialog/modal-dialog.component';
import { formatFinancialYear } from '../../../../shared/utils/financial-year';
import { refreshEvery } from '../../../../shared/utils/status-refresh';
import {
  DocumentApiService,
  DocumentDto,
  DocumentTypeDto,
  FinancialYearDto,
} from '../../../../shared/services/documents/document-api.service';

import { AuthService } from '../../../../shared/services/auth/auth.service';

@Component({
  selector: 'app-document-list',
  imports: [RouterLink, FormsModule, TaxIconComponent, BackNavigationComponent, ModalDialogComponent],
  templateUrl: './document-list.component.html',
  styleUrl: './document-list.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DocumentListComponent implements OnInit {
  private readonly api = inject(DocumentApiService);
  private readonly cdr = inject(ChangeDetectorRef);
  readonly auth = inject(AuthService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly route = inject(ActivatedRoute, { optional: true });

  documents: DocumentDto[] = [];
  financialYears: FinancialYearDto[] = [];
  documentTypes: DocumentTypeDto[] = [];

  isLoading = true;
  keyword = this.route?.snapshot.queryParamMap.get('q') ?? '';
  typeFilter = '';
  statusFilter = this.route?.snapshot.queryParamMap.get('status') ?? '';
  paymentFilter = '';
  dateFrom = '';
  dateTo = '';
  errorMessage = '';
  optionsErrorMessage = '';
  assigningFinancialYearDocumentId: string | null = null;
  deletingDocumentId: string | null = null;
  failedDeletionDocumentId: string | null = null;
  deleteErrorMessage = '';
  confirmingPaymentDocumentId: string | null = null;
  confirmingPaymentRequestId: string | null = null;
  paymentDate = new Date().toISOString().slice(0, 10);
  paymentAmount = '';
  paymentMethod = 'bpay';
  failedPaymentDocumentId: string | null = null;
  paymentErrorMessage = '';
  private readonly deletedDocumentIds = new Set<string>();

  readonly paymentMethods = [
    { value: 'bpay', label: 'BPAY' },
    { value: 'bank_transfer', label: 'Bank transfer' },
    { value: 'card', label: 'Card' },
    { value: 'direct_debit', label: 'Direct debit' },
    { value: 'cash', label: 'Cash' },
    { value: 'other', label: 'Other' },
  ];

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

    const byYear = selectedYearId
      ? this.documents.filter(
          (document) => document.financial_year_id === selectedYearId,
        ).concat(this.documents.filter((document) => document.financial_year_id === null))
      : this.documents;

    return byYear.filter((document) => this.matchesFilters(document));
  }

  get statusOptions(): string[] {
    return [...new Set(this.documents.map((document) => document.status))].sort();
  }

  get hasActiveFilters(): boolean {
    return !!(
      this.keyword.trim()
      || this.typeFilter
      || this.statusFilter
      || this.paymentFilter
      || this.dateFrom
      || this.dateTo
    );
  }

  clearFilters(): void {
    this.keyword = '';
    this.typeFilter = '';
    this.statusFilter = '';
    this.paymentFilter = '';
    this.dateFrom = '';
    this.dateTo = '';
  }

  // Issue date is the extracted invoice date, falling back to the upload date.
  issueDate(document: DocumentDto): string {
    const extracted = document.latest_extraction?.output?.normalized?.fields?.[
      'invoice_date'
    ]?.value;

    return typeof extracted === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(extracted)
      ? extracted
      : (document.uploaded_at ?? '').slice(0, 10);
  }

  private matchesFilters(document: DocumentDto): boolean {
    if (this.typeFilter && document.document_type_id !== this.typeFilter) {
      return false;
    }

    if (this.statusFilter && document.status !== this.statusFilter) {
      return false;
    }

    if (this.paymentFilter) {
      const paid = document.payment_status === 'paid';

      if ((this.paymentFilter === 'paid') !== paid) {
        return false;
      }
    }

    const issued = this.issueDate(document);

    if (this.dateFrom && (!issued || issued < this.dateFrom)) {
      return false;
    }

    if (this.dateTo && (!issued || issued > this.dateTo)) {
      return false;
    }

    const keyword = this.keyword.trim().toLowerCase();

    if (!keyword) {
      return true;
    }

    const extractedValues = Object.values(
      document.latest_extraction?.output?.normalized?.fields ?? {},
    ).map((field) => (field?.value === null || field?.value === undefined ? '' : String(field.value)));
    const haystack = [
      document.original_filename,
      this.getDocumentTypeName(document),
      ...extractedValues,
    ].join(' ').toLowerCase();

    return keyword.split(/\s+/).every((part) => haystack.includes(part));
  }

  get hasSelectedFinancialYear(): boolean {
    return this.api.selectedFinancialYearId() !== null;
  }

  get paymentConfirmationDocument(): DocumentDto | null {
    return this.documents.find(
      (document) => document.id === this.confirmingPaymentDocumentId,
    ) ?? null;
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
    this.route?.queryParamMap.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((params) => {
      this.keyword = params.get('q') ?? this.keyword;
      this.statusFilter = params.get('status') ?? this.statusFilter;
      this.cdr.markForCheck();
    });
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

  openPaymentConfirmation(document: DocumentDto): void {
    if (document.financial_year_id === null || document.payment_status === 'paid') {
      return;
    }

    this.confirmingPaymentDocumentId = document.id;
    this.paymentDate = document.payment_suggestion?.date ?? this.today();
    this.paymentAmount = document.payment_suggestion?.amount ?? '';
    this.paymentMethod = 'bpay';
    this.failedPaymentDocumentId = null;
    this.paymentErrorMessage = '';
  }

  cancelPaymentConfirmation(): void {
    this.confirmingPaymentDocumentId = null;
    this.paymentErrorMessage = '';
  }

  confirmPayment(document: DocumentDto): void {
    if (
      this.confirmingPaymentDocumentId !== document.id
      || this.confirmingPaymentRequestId !== null
      || !this.paymentDate
      || !Number.isFinite(Number(this.paymentAmount))
      || Number(this.paymentAmount) <= 0
    ) {
      return;
    }

    this.confirmingPaymentRequestId = document.id;
    this.failedPaymentDocumentId = null;
    this.paymentErrorMessage = '';
    this.api.confirmDocumentPayment(
      document.id,
      this.paymentDate,
      this.paymentMethod,
      this.paymentAmount,
    ).subscribe({
      next: (updatedDocument) => {
        this.replaceDocument(updatedDocument);
        this.confirmingPaymentDocumentId = null;
        this.confirmingPaymentRequestId = null;
        this.cdr.markForCheck();
      },
      error: (error: { error?: { message?: string } }) => {
        this.confirmingPaymentRequestId = null;
        this.failedPaymentDocumentId = document.id;
        this.paymentErrorMessage = error.error?.message
          ?? 'Unable to confirm payment. Please try again.';
        this.cdr.markForCheck();
      },
    });
  }

  deleteDocument(document: DocumentDto): void {
    if (
      document.status === 'processing'
      || this.deletingDocumentId !== null
      || !window.confirm(
        `Permanently delete "${document.original_filename}"? The original file, document entry, and its review items will be removed. OCR extractions, evidence, and transactions will be kept, but detached from the document. This cannot be undone.`,
      )
    ) {
      return;
    }

    this.deletingDocumentId = document.id;
    this.failedDeletionDocumentId = null;
    this.deleteErrorMessage = '';
    this.api.deleteDocument(document.id).subscribe({
      next: () => {
        this.deletedDocumentIds.add(document.id);
        this.documents = this.documents.filter((item) => item.id !== document.id);
        this.deletingDocumentId = null;
        this.cdr.markForCheck();
      },
      error: (error: { error?: { message?: string } }) => {
        this.deletingDocumentId = null;
        this.failedDeletionDocumentId = document.id;
        this.deleteErrorMessage = error.error?.message
          ?? 'Unable to delete the document. Please try again.';
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

  formatPaymentAmount(amount: number | string | null | undefined): string {
    const value = Number(amount);
    return Number.isFinite(value)
      ? new Intl.NumberFormat('en-AU', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        }).format(value)
      : 'Amount unavailable';
  }

  paymentMethodLabel(method: string | null | undefined): string {
    return this.paymentMethods.find((item) => item.value === method)?.label
      ?? 'Payment method unavailable';
  }

  private today(): string {
    const today = new Date();
    return [
      today.getFullYear(),
      String(today.getMonth() + 1).padStart(2, '0'),
      String(today.getDate()).padStart(2, '0'),
    ].join('-');
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

  getStatusClass(status: string): string {
    const statusClass: Record<string, string> = {
      processing: 'status-processing',
      review_required: 'status-review',
      processing_failed: 'status-failed',
      awaiting_financial_year: 'status-attention',
      active: 'status-uploaded',
    };

    return `status ${statusClass[status] ?? 'status-neutral'}`;
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
      this.documents = documents.filter(
        (document) => !this.deletedDocumentIds.has(document.id),
      );
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