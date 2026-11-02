import { provideHttpClient } from '@angular/common/http';
import {
  HttpTestingController,
  provideHttpClientTesting,
} from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';

import { DocumentApiService } from './document-api.service';

describe('DocumentApiService', () => {
  let service: DocumentApiService;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        DocumentApiService,
      ],
    });

    service = TestBed.inject(DocumentApiService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('updates shared financial years after creating a year', () => {
    const newYear = {
      id: 'fy-2027-28',
      year_code: '2027-28',
      start_date: '2027-07-01',
      end_date: '2028-06-30',
      status: 'active',
    };
    service.financialYears.set([
      {
        id: 'fy-2025-26',
        year_code: '2025-26',
        start_date: '2025-07-01',
        end_date: '2026-06-30',
        status: 'active',
      },
    ]);

    service.createFinancialYear('2027-28').subscribe();

    const request = http.expectOne('/api/financial-years');
    expect(request.request.method).toBe('POST');
    expect(request.request.body).toEqual({
      year_code: '2027-28',
      start_date: '2027-07-01',
      end_date: '2028-06-30',
    });
    request.flush({ data: newYear });

    expect(service.financialYears().map((year) => year.id))
      .toEqual(['fy-2027-28', 'fy-2025-26']);
  });
});
