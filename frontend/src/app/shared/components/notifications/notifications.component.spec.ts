import { signal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of } from 'rxjs';

import { DocumentApiService } from '../../services/documents/document-api.service';
import { TaxWorkspaceApiService } from '../../services/tax-workspace-api.service';
import { UserPreferencesService } from '../../services/user-preferences.service';
import { NotificationsComponent } from './notifications.component';

describe('NotificationsComponent', () => {
  let fixture: ComponentFixture<NotificationsComponent>;
  const preferences = signal({
    notifyDocuments: true,
    notifyUncategorised: true,
    defaultFinancialYear: 'current' as const,
  });

  const documents = [
    { id: '1', status: 'review_required' },
    { id: '2', status: 'review_required' },
    { id: '3', status: 'processing_failed' },
    { id: '4', status: 'active' },
  ];
  const transactions = [
    { id: 't1', ato_category: null, financial_year_id: 'fy1' },
    { id: 't2', ato_category: null, financial_year_id: 'fy2' },
    { id: 't3', ato_category: 'D5', financial_year_id: 'fy1' },
  ];

  async function create(yearId: string | null) {
    await TestBed.configureTestingModule({
      imports: [NotificationsComponent],
      providers: [
        provideRouter([]),
        {
          provide: DocumentApiService,
          useValue: { selectedFinancialYearId: signal(yearId), listDocuments: () => of(documents) },
        },
        { provide: TaxWorkspaceApiService, useValue: { listTransactions: () => of(transactions) } },
        { provide: UserPreferencesService, useValue: { preferences } },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(NotificationsComponent);
    fixture.detectChanges();
  }

  beforeEach(() => preferences.set({ ...preferences(), notifyDocuments: true, notifyUncategorised: true }));

  it('summarises documents and uncategorised transactions for the selected year', async () => {
    await create('fy1');

    const titles = fixture.componentInstance.notifications.map((item) => item.title);

    expect(titles).toEqual([
      '2 documents need your review',
      '1 document failed processing',
      '1 transaction without a tax return item',
    ]);
  });

  it('counts every year when none is selected', async () => {
    await create(null);

    expect(fixture.componentInstance.notifications.at(-1)?.title).toBe(
      '2 transactions without a tax return item',
    );
  });

  it('hides categories the user turned off', async () => {
    preferences.set({ ...preferences(), notifyDocuments: false });
    await create(null);

    expect(fixture.componentInstance.notifications.map((item) => item.id)).toEqual(['txn-uncategorised']);
  });
});
