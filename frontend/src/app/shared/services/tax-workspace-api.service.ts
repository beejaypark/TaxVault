import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';

interface ApiList<T> {
  data: T[];
}

interface ApiItem<T> {
  data: T;
}

export interface PropertyDto {
  id: string;
  reference_code: string | null;
  address_line_1: string | null;
  address_line_2: string | null;
  suburb: string | null;
  state: string | null;
  postcode: string | null;
  country_code: string;
}

export interface CreatePropertyRequest {
  reference_code?: string;
  address_line_1: string;
  address_line_2?: string;
  suburb?: string;
  state?: string;
  postcode?: string;
  country_code: string;
}

export interface InvestmentDto {
  id: string;
  property_id: string | null;
  investment_type: string;
  acquisition_date: string;
  disposal_date: string | null;
  quantity: string | number | null;
  ownership_percentage: string | number | null;
  cost_base: string | number | null;
  incidental_costs: string | number | null;
  proceeds: string | number | null;
}

export interface CreateInvestmentRequest {
  property_id?: string | null;
  investment_type: string;
  acquisition_date: string;
  cost_base?: number | null;
  incidental_costs?: number | null;
  proceeds?: number | null;
  quantity?: number | null;
  ownership_percentage?: number | null;
}

export interface ExportDto {
  id: string;
  financial_year_id: string;
  export_version: string;
  generated_at: string;
  sha256: string;
  payload: Record<string, unknown>;
}

export interface TransactionDto {
  id: string;
  financial_year_id: string;
  document_id: string | null;
  transaction_date: string;
  description: string;
  direction: 'income' | 'expense';
  amount: string | number;
  currency: string;
  source_system: string | null;
}

export interface CreateTransactionRequest {
  transaction_date: string;
  direction: 'income' | 'expense';
  amount: number;
  description: string;
  currency: string;
}

@Injectable({ providedIn: 'root' })
export class TaxWorkspaceApiService {
  private readonly http = inject(HttpClient);

  listProperties(): Observable<PropertyDto[]> {
    return this.http
      .get<ApiList<PropertyDto>>('/api/properties')
      .pipe(map((response) => response.data));
  }

  createProperty(property: CreatePropertyRequest): Observable<PropertyDto> {
    return this.http
      .post<ApiItem<PropertyDto>>('/api/properties', property)
      .pipe(map((response) => response.data));
  }

  listInvestments(): Observable<InvestmentDto[]> {
    return this.http
      .get<ApiList<InvestmentDto>>('/api/investments')
      .pipe(map((response) => response.data));
  }

  createInvestment(investment: CreateInvestmentRequest): Observable<InvestmentDto> {
    return this.http
      .post<ApiItem<InvestmentDto>>('/api/investments', investment)
      .pipe(map((response) => response.data));
  }

  createExport(financialYearId: string): Observable<ExportDto> {
    return this.http
      .post<ApiItem<ExportDto>>('/api/exports', {
        financial_year_id: financialYearId,
      })
      .pipe(map((response) => response.data));
  }

  listTransactions(): Observable<TransactionDto[]> {
    return this.http
      .get<ApiList<TransactionDto>>('/api/transactions')
      .pipe(map((response) => response.data));
  }

  createTransaction(transaction: CreateTransactionRequest): Observable<TransactionDto> {
    return this.http
      .post<ApiItem<TransactionDto>>('/api/transactions', transaction)
      .pipe(map((response) => response.data));
  }
}
