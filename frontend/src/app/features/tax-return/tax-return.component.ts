import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  OnInit,
  inject,
} from '@angular/core';
import { FormsModule } from '@angular/forms';

import { TaxIconComponent } from '../../shared/components/tax-icon/tax-icon.component';
import { formatFinancialYear } from '../../shared/utils/financial-year';
import {
  DocumentApiService,
  FinancialYearDto,
} from '../../shared/services/documents/document-api.service';
import {
  ExportDto,
  TaxWorkspaceApiService,
} from '../../shared/services/tax-workspace-api.service';

@Component({
  selector: 'app-tax-return',
  imports: [FormsModule, TaxIconComponent],
  templateUrl: './tax-return.component.html',
  styleUrl: './tax-return.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TaxReturnComponent implements OnInit {
  private readonly documentsApi = inject(DocumentApiService);
  private readonly workspaceApi = inject(TaxWorkspaceApiService);
  private readonly cdr = inject(ChangeDetectorRef);

  financialYears: FinancialYearDto[] = [];
  selectedFinancialYearId = '';
  latestExport: ExportDto | null = null;
  isLoading = true;
  isExporting = false;
  errorMessage = '';
  successMessage = '';

  get exportedYearCode(): string {
    const yearCode = this.financialYears.find(
      (year) => year.id === this.latestExport?.financial_year_id,
    )?.year_code;

    return yearCode ? formatFinancialYear(yearCode) : 'Tax year';
  }

  formatYearCode(yearCode: string): string {
    return formatFinancialYear(yearCode);
  }

  ngOnInit(): void {
    this.documentsApi.listAllFinancialYears().subscribe({
      next: (years) => {
        this.financialYears = years;
        const selectedYearId = this.documentsApi.selectedFinancialYearId();
        this.selectedFinancialYearId = years.some(
          (year) => year.id === selectedYearId,
        )
          ? selectedYearId ?? ''
          : this.documentsApi.defaultFinancialYearId(years) ?? '';
        this.documentsApi.selectFinancialYear(
          this.selectedFinancialYearId || null,
        );
        this.isLoading = false;
        this.cdr.markForCheck();
      },
      error: () => {
        this.errorMessage = 'Unable to load financial years. Please try again.';
        this.isLoading = false;
        this.cdr.markForCheck();
      },
    });
  }

  onFinancialYearChange(financialYearId: string): void {
    this.selectedFinancialYearId = financialYearId;
    this.documentsApi.selectFinancialYear(financialYearId || null);
  }

  generateExport(): void {
    if (this.isExporting || !this.selectedFinancialYearId) {
      return;
    }

    this.isExporting = true;
    this.errorMessage = '';
    this.successMessage = '';

    this.workspaceApi.createExport(this.selectedFinancialYearId).subscribe({
      next: (result) => {
        this.latestExport = result;
        this.successMessage = 'Tax return pack generated and saved.';
        this.isExporting = false;
        this.cdr.markForCheck();
      },
      error: (error: { status?: number }) => {
        this.errorMessage = error.status === 422
          ? 'This financial year cannot be exported yet. Check the year and required records.'
          : 'Unable to generate the tax return pack. Please try again.';
        this.isExporting = false;
        this.cdr.markForCheck();
      },
    });
  }

  downloadExport(): void {
    if (!this.latestExport) {
      return;
    }

    const file = new Blob(
      [JSON.stringify(this.latestExport.payload, null, 2)],
      { type: 'application/json' },
    );
    const url = URL.createObjectURL(file);
    const link = document.createElement('a');
    const year = this.financialYears.find(
      (item) => item.id === this.latestExport?.financial_year_id,
    )?.year_code ?? 'tax-year';

    link.href = url;
    link.download = `taxvault-${year}-return-pack.json`;
    link.click();
    URL.revokeObjectURL(url);
  }

  formatDate(value: string): string {
    const date = new Date(value);

    return Number.isNaN(date.getTime())
      ? 'Date unavailable'
      : new Intl.DateTimeFormat('en-AU', {
          day: 'numeric',
          month: 'long',
          year: 'numeric',
          hour: 'numeric',
          minute: '2-digit',
        }).format(date);
  }
}
