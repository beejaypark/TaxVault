import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';

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
  financial_year_id: string;
  document_type_id: string;
  original_filename: string;
  mime_type: string | null;
  size_bytes: number;
  uploaded_at: string;
  status: string;
}

@Injectable({ providedIn: 'root' })
export class DocumentApiService {
  private readonly http = inject(HttpClient);

  listFinancialYears(): Observable<FinancialYearDto[]> {
    return this.http
      .get<ApiList<FinancialYearDto>>('/api/financial-years')
      .pipe(
        map((response) =>
          response.data.filter((year) => year.status === 'active'),
        ),
      );
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
    financialYearId: string,
    documentTypeId: string,
    file: File,
  ): Observable<DocumentDto> {
    const formData = new FormData();
    formData.append('financial_year_id', financialYearId);
    formData.append('document_type_id', documentTypeId);
    formData.append('file', file, file.name);

    return this.http
      .post<ApiItem<DocumentDto>>('/api/documents', formData)
      .pipe(map((response) => response.data));
  }
}