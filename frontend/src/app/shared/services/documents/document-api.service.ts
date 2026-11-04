import { Injectable, inject, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map, tap } from 'rxjs';

interface ApiList<T> {
  data: T[];
}

interface ApiItem<T> {
  data: T;
}

export interface FinancialYearDto {
  id: string;
  year_code: string;
  start_date: string;
  end_date: string;
  status: string;
}

export interface DocumentTypeDto {
  id: string;
  code: string;
  name: string;
  description?: string | null;
  status: string;
}

export interface DocumentDto {
  id: string;
  financial_year_id: string | null;
  document_type_id: string;
  original_filename: string;
  mime_type: string | null;
  size_bytes: number;
  uploaded_at: string;
  status: string;
  payment_status?: 'unconfirmed' | 'paid';
  payment_date?: string | null;
  payment_method?: string | null;
  payment_amount?: number | string | null;
  payment_suggestion?: { date: string | null; amount: string | null } | null;
  latest_extraction?: {
    id: string;
    status: string;
    provider: string;
    error_metadata?: { message?: string } | null;
    output?: {
      normalized?: {
        fields?: Record<string, { value?: unknown }>;
      };
    };
  } | null;
}

@Injectable({ providedIn: 'root' })
export class DocumentApiService {
  private readonly http = inject(HttpClient);

  readonly selectedFinancialYearId = signal<string | null>(null);
  readonly financialYears = signal<FinancialYearDto[]>([]);

  selectFinancialYear(financialYearId: string | null): void {
    this.selectedFinancialYearId.set(financialYearId);
  }

  defaultFinancialYearId(years: FinancialYearDto[]): string | null {
    const today = new Date();
    const todayCode = [
      today.getFullYear(),
      String(today.getMonth() + 1).padStart(2, '0'),
      String(today.getDate()).padStart(2, '0'),
    ].join('-');
    const currentYear = years.find(
      (year) => year.start_date <= todayCode && year.end_date >= todayCode,
    );

    return currentYear?.id ?? years[0]?.id ?? null;
  }

  listFinancialYears(): Observable<FinancialYearDto[]> {
    return this.listAllFinancialYears().pipe(
      map((years) => years.filter((year) => year.status === 'active')),
    );
  }

  listAllFinancialYears(): Observable<FinancialYearDto[]> {
    return this.http
      .get<ApiList<FinancialYearDto>>('/api/financial-years')
      .pipe(
        map((response) => response.data),
        tap((years) => this.financialYears.set(years)),
      );
  }

  createFinancialYear(yearCode: string): Observable<FinancialYearDto> {
    const startYear = Number(yearCode.slice(0, 4));

    return this.http
      .post<ApiItem<FinancialYearDto>>('/api/financial-years', {
        year_code: yearCode,
        start_date: `${startYear}-07-01`,
        end_date: `${startYear + 1}-06-30`,
      })
      .pipe(
        map((response) => response.data),
        tap((year) => {
          this.financialYears.update((years) => [
            year,
            ...years.filter((existing) => existing.id !== year.id),
          ].sort((a, b) => b.start_date.localeCompare(a.start_date)));
        }),
      );
  }

  updateFinancialYear(
    id: string,
    changes: { year_code?: string; status?: string },
  ): Observable<FinancialYearDto> {
    return this.http
      .patch<ApiItem<FinancialYearDto>>(`/api/financial-years/${id}`, changes)
      .pipe(
        map((response) => response.data),
        tap((year) => {
          this.financialYears.update((years) => years
            .map((existing) => (existing.id === year.id ? year : existing))
            .sort((a, b) => b.start_date.localeCompare(a.start_date)));
        }),
      );
  }

  deleteFinancialYear(id: string): Observable<unknown> {
    return this.http.delete(`/api/financial-years/${id}`).pipe(
      tap(() => {
        this.financialYears.update((years) => years.filter((year) => year.id !== id));
        if (this.selectedFinancialYearId() === id) {
          this.selectedFinancialYearId.set(null);
        }
      }),
    );
  }

  assignDocumentFinancialYear(
    documentId: string,
    financialYearCode: string,
  ): Observable<DocumentDto> {
    return this.http
      .post<ApiItem<DocumentDto>>(`/api/documents/${documentId}/financial-year`, {
        financial_year_code: financialYearCode,
      })
      .pipe(map((response) => response.data));
  }

  assignDocumentToFinancialYear(
    documentId: string,
    financialYearId: string,
  ): Observable<DocumentDto> {
    return this.http
      .post<ApiItem<DocumentDto>>(`/api/documents/${documentId}/financial-year`, {
        financial_year_id: financialYearId,
      })
      .pipe(map((response) => response.data));
  }

  getInboundEmailAddress(): Observable<string> {
    return this.http
      .get<{ data: { address: string } }>('/api/inbound-email')
      .pipe(map((response) => response.data.address));
  }

  listDocumentTypes(): Observable<DocumentTypeDto[]> {
    return this.http
      .get<ApiList<DocumentTypeDto>>('/api/document-types')
      .pipe(map((response) => response.data));
  }

  listDocuments(): Observable<DocumentDto[]> {
    return this.http
      .get<ApiList<DocumentDto>>('/api/documents')
      .pipe(map((response) => response.data));
  }

  uploadDocument(
    financialYearId: string | null,
    documentTypeId: string,
    file: File,
  ): Observable<DocumentDto> {
    const formData = new FormData();
    if (financialYearId) {
      formData.append('financial_year_id', financialYearId);
    }
    formData.append('document_type_id', documentTypeId);
    formData.append('file', file, file.name);

    return this.http
      .post<ApiItem<DocumentDto>>('/api/documents', formData)
      .pipe(map((response) => response.data));
  }

  retryDocumentProcessing(documentId: string): Observable<DocumentDto> {
    return this.http
      .post<ApiItem<DocumentDto>>(`/api/documents/${documentId}/retry`, {})
      .pipe(map((response) => response.data));
  }

  confirmDocumentPayment(
    documentId: string,
    paymentDate: string,
    paymentMethod: string,
    amount: string,
  ): Observable<DocumentDto> {
    return this.http
      .post<ApiItem<DocumentDto>>(`/api/documents/${documentId}/payment`, {
        payment_date: paymentDate,
        payment_method: paymentMethod,
        amount,
      })
      .pipe(map((response) => response.data));
  }

  deleteDocument(documentId: string): Observable<void> {
    return this.http.delete<void>(`/api/documents/${documentId}`);
  }
}