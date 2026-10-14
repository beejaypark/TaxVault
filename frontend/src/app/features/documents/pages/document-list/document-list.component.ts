import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  OnInit,
  inject,
} from '@angular/core';
import { RouterLink } from '@angular/router';
import {
  DocumentApiService,
  DocumentDto,
  DocumentTypeDto,
  FinancialYearDto,
} from '../../../../shared/services/documents/document-api.service';

@Component({
  selector: 'app-document-list',
  imports: [RouterLink],
  templateUrl: './document-list.component.html',
  styleUrl: './document-list.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DocumentListComponent implements OnInit {
  private readonly api = inject(DocumentApiService);
  private readonly cdr = inject(ChangeDetectorRef);

  documents: DocumentDto[] = [];
  financialYears: FinancialYearDto[] = [];
  documentTypes: DocumentTypeDto[] = [];

  isLoading = true;
  errorMessage = '';
  optionsErrorMessage = '';

  get financialYear(): string {
    const year = this.financialYears[0];
    return year ? `FY ${year.year_code}` : 'Your financial years';
  }

  ngOnInit(): void {
    this.api.listDocuments().subscribe({
      next: (documents) => {
        this.documents = documents;
        this.isLoading = false;
        this.cdr.markForCheck();
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
    if (status.toLowerCase() === 'active') {
      return 'Uploaded';
    }

    return status
      .replace(/[_-]+/g, ' ')
      .replace(/\b\w/g, (character) => character.toUpperCase());
  }
}