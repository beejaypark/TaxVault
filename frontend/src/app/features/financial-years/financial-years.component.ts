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

import { AuthService } from '../../shared/services/auth/auth.service';

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
  readonly auth = inject(AuthService);

  financialYears: FinancialYearDto[] = [];
  yearCode = '';
  status: 'active' | 'inactive' = 'active';
  editingId: string | null = null;
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

    if (this.editingId) {
      const editing = this.editingId;

      this.api.updateFinancialYear(editing, { year_code: code, status: this.status }).subscribe({
        next: (year) => {
          this.financialYears = this.financialYears
            .map((existing) => (existing.id === year.id ? year : existing))
            .sort((a, b) => b.start_date.localeCompare(a.start_date));
          this.closeForm();
          this.isSaving = false;
          this.successMessage = `FY ${formatFinancialYear(year.year_code)} updated.`;
          this.cdr.markForCheck();
        },
        error: (error: { error?: { errors?: Record<string, string[]> } }) => this.fail(
          error.error?.errors?.['year_code']?.[0] ?? 'Unable to update this financial year.',
        ),
      });

      return;
    }

    this.api.createFinancialYear(code).subscribe({
      next: (year) => {
        this.financialYears = [
          year,
          ...this.financialYears.filter((existing) => existing.id !== year.id),
        ].sort((a, b) => b.start_date.localeCompare(a.start_date));
        this.closeForm();
        this.isSaving = false;
        this.successMessage = `FY ${formatFinancialYear(year.year_code)} added.`;
        this.cdr.markForCheck();
      },
      error: (error: { status?: number }) => this.fail(
        error.status === 422
          ? 'That financial year is invalid or already exists.'
          : 'Unable to save this financial year. Please try again.',
      ),
    });
  }

  startCreate(): void {
    this.closeForm();
    this.showForm = true;
  }

  startEdit(year: FinancialYearDto): void {
    this.errorMessage = '';
    this.successMessage = '';
    this.editingId = year.id;
    this.yearCode = formatFinancialYear(year.year_code);
    this.status = year.status === 'active' ? 'active' : 'inactive';
    this.showForm = true;
  }

  closeForm(): void {
    this.showForm = false;
    this.editingId = null;
    this.yearCode = '';
    this.status = 'active';
  }

  deleteFinancialYear(year: FinancialYearDto): void {
    if (!confirm(`Delete FY ${formatFinancialYear(year.year_code)}? This cannot be undone.`)) {
      return;
    }

    this.errorMessage = '';
    this.successMessage = '';

    this.api.deleteFinancialYear(year.id).subscribe({
      next: () => {
        this.financialYears = this.financialYears.filter((existing) => existing.id !== year.id);
        this.successMessage = `FY ${formatFinancialYear(year.year_code)} deleted.`;
        this.cdr.markForCheck();
      },
      error: (error: { error?: { message?: string } }) => this.fail(
        error.error?.message ?? 'Unable to delete this financial year.',
      ),
    });
  }

  private fail(message: string): void {
    this.errorMessage = message;
    this.isSaving = false;
    this.cdr.markForCheck();
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
