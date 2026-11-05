import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of } from 'rxjs';

import { AuthService } from '../../shared/services/auth/auth.service';
import { DocumentApiService } from '../../shared/services/documents/document-api.service';
import { TaxWorkspaceApiService } from '../../shared/services/tax-workspace-api.service';
import { TransactionsComponent } from './transactions.component';

const transactions = [
  { id: '1', financial_year_id: 'fy', description: 'Officeworks stationery', direction: 'expense', amount: '10', transaction_date: '2025-08-01' },
  { id: '2', financial_year_id: 'fy', description: 'Salary payment', direction: 'income', amount: '100', transaction_date: '2025-08-02' },
];

describe('TransactionsComponent search', () => {
  it('filters rows by description as the user types', async () => {
    await TestBed.configureTestingModule({
      imports: [TransactionsComponent],
      providers: [
        provideRouter([]),
        { provide: AuthService, useValue: { canEdit: true } },
        {
          provide: TaxWorkspaceApiService,
          useValue: { listProperties: () => of([]), listTransactions: () => of(transactions) },
        },
        {
          provide: DocumentApiService,
          useValue: {
            selectedFinancialYearId: signal<string | null>(null),
            financialYears: signal([]),
            listAllFinancialYears: () => of([{ id: 'fy', year_code: '2025-26' }]),
          },
        },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(TransactionsComponent);
    fixture.detectChanges();
    fixture.detectChanges();

    const input = fixture.nativeElement.querySelector('#transaction-search') as HTMLInputElement;
    input.value = 'salary';
    input.dispatchEvent(new Event('input'));
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent as string;
    expect(text).toContain('Salary payment');
    expect(text).not.toContain('Officeworks stationery');
  });
});
