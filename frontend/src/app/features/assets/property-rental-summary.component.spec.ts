import { TestBed } from '@angular/core/testing';
import { signal } from '@angular/core';
import { provideRouter } from '@angular/router';
import { of } from 'rxjs';

import { DocumentApiService } from '../../shared/services/documents/document-api.service';
import {
  PropertyDto,
  RentalSummaryDto,
  TaxWorkspaceApiService,
} from '../../shared/services/tax-workspace-api.service';
import {
  atoCategoriesFor,
  atoCategoryLabel,
  isRentalCategory,
} from '../../shared/utils/ato-categories';
import { PropertyRentalSummaryComponent } from './property-rental-summary.component';

const summary: RentalSummaryDto = {
  property_id: 'p1',
  financial_year_id: 'fy1',
  income: [{ category: 'rental_income', label: 'Rental income', amount: 2000, records: 2 }],
  expenses: [
    { category: 'rental_council_rates', label: 'Council rates', amount: 350, records: 1 },
    { category: 'rental_interest', label: 'Interest on loans', amount: 2500, records: 0, source: 'mortgage_records' },
  ],
  total_income: 2000,
  total_expenses: 2850,
  net_rental_result: -850,
  interest_from_mortgage_records: 2500,
  interest_from_transactions: 0,
  interest_mismatch: false,
};

describe('ATO categories', () => {
  it('filters by direction and labels with the return reference', () => {
    expect(atoCategoriesFor('income').every((item) => item.direction === 'income')).toBe(true);
    expect(atoCategoriesFor('expense').some((item) => item.code === 'rental_water')).toBe(true);
    expect(atoCategoryLabel('work_other')).toBe('Other work-related expenses (D5)');
    expect(atoCategoryLabel(null)).toBe('');
    expect(isRentalCategory('rental_water')).toBe(true);
    expect(isRentalCategory('work_other')).toBe(false);
  });
});

describe('PropertyRentalSummaryComponent', () => {
  function setup(yearId: string | null) {
    const getRentalSummary = vi.fn().mockReturnValue(of(summary));
    const documents = {
      selectedFinancialYearId: signal<string | null>(yearId),
      financialYears: signal([{ id: 'fy1', year_code: '2025-26' }]),
    };

    TestBed.configureTestingModule({
      imports: [PropertyRentalSummaryComponent],
      providers: [
        provideRouter([]),
        { provide: TaxWorkspaceApiService, useValue: { getRentalSummary } },
        { provide: DocumentApiService, useValue: documents },
      ],
    });

    const fixture = TestBed.createComponent(PropertyRentalSummaryComponent);
    fixture.componentRef.setInput('property', { id: 'p1' } as PropertyDto);
    fixture.detectChanges();

    return { fixture, getRentalSummary };
  }

  it('asks for a financial year when none is selected', () => {
    const { fixture, getRentalSummary } = setup(null);

    expect(getRentalSummary).not.toHaveBeenCalled();
    expect(fixture.nativeElement.textContent).toContain('Select a financial year');
  });

  it('shows the schedule and a net loss for the selected year', () => {
    const { fixture, getRentalSummary } = setup('fy1');
    fixture.detectChanges();
    const text = fixture.nativeElement.textContent as string;

    expect(getRentalSummary).toHaveBeenCalledWith('p1', 'fy1');
    expect(text).toContain('Council rates');
    expect(text).toContain('from mortgage interest entries');
    expect(text).toContain('Net rental loss');
    expect(text).toContain('$850.00');
  });
});
