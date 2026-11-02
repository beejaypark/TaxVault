import { ComponentFixture, TestBed } from '@angular/core/testing';
import { signal } from '@angular/core';
import { provideRouter } from '@angular/router';
import { of } from 'rxjs';

import { AuthService } from '../../services/auth/auth.service';
import {
  DocumentApiService,
  FinancialYearDto,
} from '../../services/documents/document-api.service';
import { AppShellComponent } from './app-shell.component';

describe('AppShellComponent', () => {
  let fixture: ComponentFixture<AppShellComponent>;
  const selectedFinancialYearId = signal<string | null>(null);
  const financialYears = signal<FinancialYearDto[]>([]);
  const availableYears: FinancialYearDto[] = [
    {
      id: 'fy-2026-27',
      year_code: '2026-27',
      start_date: '2026-07-01',
      end_date: '2027-06-30',
      status: 'active',
    },
    {
      id: 'fy-2025-26',
      year_code: '2025-26',
      start_date: '2025-07-01',
      end_date: '2026-06-30',
      status: 'active',
    },
  ];
  const documentApi = {
    listFinancialYears: () => {
      financialYears.set(availableYears);
      return of(availableYears);
    },
    financialYears,
    selectedFinancialYearId,
    selectFinancialYear: (id: string) => selectedFinancialYearId.set(id),
    defaultFinancialYearId: (years: { id: string }[]) => years[0]?.id ?? null,
  };

  beforeEach(async () => {
    selectedFinancialYearId.set(null);
    financialYears.set([]);

    await TestBed.configureTestingModule({
      imports: [AppShellComponent],
      providers: [
        provideRouter([]),
        {
          provide: AuthService,
          useValue: {
            currentUser: null,
            logout: () => of(null),
          },
        },
        { provide: DocumentApiService, useValue: documentApi },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(AppShellComponent);
    fixture.detectChanges();
  });

  it('should keep financial years near the top of the main navigation', () => {
    const navigation = fixture.nativeElement.querySelector(
      'nav[aria-label="Main navigation"]',
    ) as HTMLElement;
    const links = Array.from(navigation.querySelectorAll('a'));
    const financialYearsLinkIndex = links.findIndex((link) =>
      link.textContent?.includes('Financial years'),
    );

    expect(financialYearsLinkIndex).toBe(2);
    expect(getComputedStyle(navigation).overflowY).toBe('auto');
  });

  it('should list each available financial year in the selector', () => {
    fixture.detectChanges();
    const select = fixture.nativeElement.querySelector(
      '#active-financial-year',
    ) as HTMLSelectElement;

    expect(select.disabled).toBe(false);
    expect(Array.from(select.options).map((option) => option.textContent?.trim()))
      .toEqual(['All financial years', 'FY 2026 - 27', 'FY 2025 - 26']);
    expect(select.value).toBe('fy-2026-27');
  });

  it('should publish the selected financial year to the shared workspace state', () => {
    const select = fixture.nativeElement.querySelector(
      '#active-financial-year',
    ) as HTMLSelectElement;
    select.value = 'fy-2025-26';
    select.dispatchEvent(new Event('change'));
    fixture.detectChanges();

    expect(selectedFinancialYearId()).toBe('fy-2025-26');
  });

  it('updates the year dropdown when another page creates a financial year', () => {
    financialYears.set([
      {
        id: 'fy-2027-28',
        year_code: '2027-28',
        start_date: '2027-07-01',
        end_date: '2028-06-30',
        status: 'active',
      },
      ...availableYears,
    ]);
    fixture.detectChanges();

    const select = fixture.nativeElement.querySelector(
      '#active-financial-year',
    ) as HTMLSelectElement;
    expect(Array.from(select.options).map((option) => option.textContent?.trim()))
      .toContain('FY 2027 - 28');
  });
});
