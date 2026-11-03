import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  OnInit,
  inject,
} from '@angular/core';
import { FormsModule } from '@angular/forms';

import { TaxIconComponent } from '../../shared/components/tax-icon/tax-icon.component';
import { BackNavigationComponent } from '../../shared/components/back-navigation/back-navigation.component';
import {
  formatFinancialYear,
  normalizeFinancialYearInput,
} from '../../shared/utils/financial-year';
import {
  DocumentApiService,
  FinancialYearDto,
} from '../../shared/services/documents/document-api.service';

@Component({
  selector: 'app-financial-years',
  imports: [FormsModule, TaxIconComponent, BackNavigationComponent],
  templateUrl: './financial-years.component.html',
  styleUrl: './financial-years.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class FinancialYearsComponent implements OnInit {
  private readonly api = inject(DocumentApiService);
  private readonly cdr = inject(ChangeDetectorRef);

  financialYears: FinancialYearDto[] = [];
  yearCode = '';
  isLoading = true;
  isSaving = false;
  showForm = false;
  errorMessage = '';
  successMessage = '';

  get isValidYearCode(): boolean {
    const normalizedCode = normalizeFinancialYearInput(this.yearCode.trim());
    const match = /^(\d{4})-(\d{2})$/.exec(normalizedCode);

    return !!match && Number(match[2]) === (Number(match[1]) + 1) % 100;
  }

  formatYearCode(yearCode: string): string {
    return formatFinancialYear(yearCode);
  }

  ngOnInit(): void {
    this.loadFinancialYears();
  }

  loadFinancialYears(): void {
    this.api.listAllFinancialYears().subscribe({
      next: (years) => {
        this.financialYears = years;
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

  createFinancialYear(): void {
    const code = normalizeFinancialYearInput(this.yearCode.trim());

    if (this.isSaving || !this.isValidYearCode) {
      return;
    }

    this.isSaving = true;
    this.errorMessage = '';
    this.successMessage = '';

    this.api.createFinancialYear(code).subscribe({
      next: (year) => {
        this.financialYears = [
          year,
          ...this.financialYears.filter((existing) => existing.id !== year.id),
        ].sort((a, b) => b.start_date.localeCompare(a.start_date));
        this.yearCode = '';
        this.showForm = false;
        this.isSaving = false;
        this.successMessage = `FY ${formatFinancialYear(year.year_code)} added.`;
        this.cdr.markForCheck();
      },
      error: (error: { status?: number }) => {
        this.errorMessage = error.status === 422
          ? 'That financial year is invalid or already exists.'
          : 'Unable to save this financial year. Please try again.';
        this.isSaving = false;
        this.cdr.markForCheck();
      },
    });
  }

  formatDate(value: string): string {
    const date = new Date(value);

    return Number.isNaN(date.getTime())
      ? 'Date unavailable'
      : new Intl.DateTimeFormat('en-AU', {
          day: 'numeric',
          month: 'long',
          year: 'numeric',
        }).format(date);
  }
}
