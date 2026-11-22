import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';

import { WealthSummary } from '../utils/wealth';

interface ApiList<T> {
  data: T[];
}

interface ApiItem<T> {
  data: T;
}

export interface MortgageInterestEntryDto {
  id: string;
  month: string;
  interest_amount: string | number;
}

export interface PropertyValuationDto {
  id: string;
  value: string | number;
  as_of: string;
}

export interface PropertyDeductionDto {
  id: string;
  property_id: string;
  financial_year_id: string;
  deduction_type: 'capital_works' | 'plant_equipment';
  amount: string | number;
  source_document_id: string | null;
  notes: string | null;
  financial_year?: { id: string; year_code: string };
}

export interface PropertyDeductionRequest {
  financial_year_id: string;
  deduction_type: 'capital_works' | 'plant_equipment';
  amount: number;
  source_document_id?: string | null;
  notes?: string | null;
}

export interface TaxNewsDto {
  id: string;
  source: string;
  title: string;
  summary: string | null;
  url: string;
  category: string;
  importance: 'info' | 'important';
  status: 'proposed' | 'passed' | 'effective' | 'guidance';
  published_at: string | null;
  effective_at: string | null;
  ai_summary?: string | null;
  ai_reason?: string | null;
}

export interface MortgageRateChangeDto {
  id: string;
  effective_date: string;
  rate: string | number;
  bank_repayment_amount: string | number | null;
}

export interface MortgageDto {
  id: string;
  property_id: string;
  bank_name: string;
  loan_type: string;
  repayment_frequency: 'weekly' | 'fortnightly' | 'monthly';
  loan_amount: string | number;
  repayment_amount?: string | number | null;
  interest_rate: string | number | null;
  start_date: string | null;
  maturity_date: string | null;
  estimated_balance?: number;
  verified_balance?: number | null;
  verified_balance_date?: string | null;
  balance_variance?: number | null;
  balance_needs_review?: boolean;
  interest_entries: MortgageInterestEntryDto[];
  rate_changes: MortgageRateChangeDto[];
}

export interface MortgageRequest {
  bank_name: string;
  loan_type: string;
  repayment_frequency: 'weekly' | 'fortnightly' | 'monthly';
  loan_amount: number;
  repayment_amount?: number | null;
  interest_rate: number | null;
  start_date: string | null;
  maturity_date: string | null;
  verified_balance?: number | null;
  verified_balance_date?: string | null;
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
  current_value?: string | number | null;
  current_value_date?: string | null;
  is_main_residence?: boolean;
  mortgages?: MortgageDto[];
  valuations?: PropertyValuationDto[];
  deductions?: PropertyDeductionDto[];
}

export interface CreatePropertyRequest {
  reference_code?: string;
  address_line_1: string;
  address_line_2?: string;
  suburb?: string;
  state?: string;
  postcode?: string;
  country_code: string;
  current_value?: number | null;
  current_value_date?: string | null;
  is_main_residence?: boolean;
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
  current_value?: string | number | null;
  current_value_date?: string | null;
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
  current_value?: number | null;
  current_value_date?: string | null;
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
  ato_category?: string | null;
  property_id?: string | null;
}

export interface CreateTransactionRequest {
  transaction_date: string;
  direction: 'income' | 'expense';
  amount: number;
  description: string;
  currency: string;
  ato_category?: string | null;
  property_id?: string | null;
}

export interface RecurringIncomeRuleDto {
  id: string;
  name: string;
  description: string;
  amount: string | number;
  day_of_month: number;
  start_date: string;
  end_date: string | null;
  ato_category: string | null;
  active: boolean;
}

export interface RecurringIncomeRuleRequest {
  name: string;
  description: string;
  amount: number;
  day_of_month: number;
  start_date: string;
  end_date?: string | null;
  ato_category?: string | null;
}

export interface RecurringIncomePreview {
  year: string | null;
  amount: string;
  missing: string[];
  existing: string[];
}

export interface RentalSummaryLine {
  category: string;
  label: string;
  amount: number;
  records: number;
  source?: 'mortgage_records';
}

export interface ReturnSummaryLine {
  category: string;
  label: string;
  group: 'Income' | 'Rental property' | 'Deductions';
  ato_ref: string;
  direction: 'income' | 'expense';
  amount: number;
  records: number;
  property_id: string | null;
  property_name: string;
  source: 'wfh_log' | null;
}

export interface WorkFromHomeSummary {
  hours: number;
  days: number;
  rate: number;
  amount: number;
  rate_is_default: boolean;
}

export interface ReturnSummaryDto {
  financial_year_id: string;
  year_code: string;
  start_date: string;
  lines: ReturnSummaryLine[];
  rental: {
    property_id: string;
    property_name: string;
    income: number;
    expenses: number;
    interest_from_mortgage_records: number;
    net_result: number;
  }[];
  work_from_home: WorkFromHomeSummary;
  uncategorised: Record<'income' | 'expense', { amount: number; records: number }>;
  totals: { income: number; deductions: number; net_rental_result: number };
}

export interface TaxReadinessComponentDto {
  label: string;
  completed: number;
  total: number;
  percentage: number | null;
  applicable: boolean;
}

export interface TaxReadinessIssueDto {
  severity: 'blocked' | 'needs_attention';
  code: string;
  count: number;
  message: string;
}

export interface TaxReadinessDto {
  financial_year_id: string;
  financial_year_code: string;
  score: number | null;
  status: 'ready' | 'needs_attention' | 'not_started';
  calculation_version: string;
  components: {
    documents: TaxReadinessComponentDto;
    transactions: TaxReadinessComponentDto;
    evidence: TaxReadinessComponentDto;
    required_data: TaxReadinessComponentDto;
  };
  issues: TaxReadinessIssueDto[];
}

export interface WorkFromHomeLogDto {
  id: string;
  work_date: string;
  hours: string | number;
  note: string | null;
}

export interface ImportRow {
  date: string;
  description: string;
  amount: number;
  ato_category?: string | null;
  property_id?: string | null;
}

export interface DeadlineDto {
  label: string;
  date: string;
  days_left: number;
  financial_year_end: string | null;
  days_to_year_end: number | null;
  completed: boolean;
  financial_year_id: string | null;
  uncategorised_transactions: number;
  documents_to_review: number;
}

export interface BankStatus {
  enabled: boolean;
  connected: boolean;
  last_synced_at: string | null;
}

export interface ImportResult {
  imported: number;
  duplicates: number;
  outside_financial_years: number;
}

export interface RentalSummaryDto {
  property_id: string;
  financial_year_id: string;
  income: RentalSummaryLine[];
  expenses: RentalSummaryLine[];
  total_income: number;
  total_expenses: number;
  net_rental_result: number;
  interest_from_mortgage_records: number;
  interest_from_transactions: number;
  interest_mismatch: boolean;
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

  updateProperty(id: string, property: CreatePropertyRequest): Observable<PropertyDto> {
    return this.http
      .put<ApiItem<PropertyDto>>(`/api/properties/${id}`, property)
      .pipe(map((response) => response.data));
  }

  deleteProperty(id: string): Observable<unknown> {
    return this.http.delete(`/api/properties/${id}`);
  }

  savePropertyDeduction(propertyId: string, deduction: PropertyDeductionRequest): Observable<PropertyDeductionDto> {
    return this.http
      .post<ApiItem<PropertyDeductionDto>>(`/api/properties/${propertyId}/deductions`, deduction)
      .pipe(map((response) => response.data));
  }

  updatePropertyDeduction(propertyId: string, deductionId: string, deduction: PropertyDeductionRequest): Observable<PropertyDeductionDto> {
    return this.http
      .put<ApiItem<PropertyDeductionDto>>(`/api/properties/${propertyId}/deductions/${deductionId}`, deduction)
      .pipe(map((response) => response.data));
  }

  deletePropertyDeduction(propertyId: string, deductionId: string): Observable<unknown> {
    return this.http.delete(`/api/properties/${propertyId}/deductions/${deductionId}`);
  }

  getRentalSummary(propertyId: string, financialYearId: string): Observable<RentalSummaryDto> {
    return this.http
      .get<ApiItem<RentalSummaryDto>>(`/api/properties/${propertyId}/rental-summary`, {
        params: { financial_year_id: financialYearId },
      })
      .pipe(map((response) => response.data));
  }

  createMortgage(propertyId: string, mortgage: MortgageRequest): Observable<MortgageDto> {
    return this.http
      .post<ApiItem<MortgageDto>>(`/api/properties/${propertyId}/mortgages`, mortgage)
      .pipe(map((response) => response.data));
  }

  updateMortgage(id: string, mortgage: MortgageRequest): Observable<MortgageDto> {
    return this.http
      .put<ApiItem<MortgageDto>>(`/api/mortgages/${id}`, mortgage)
      .pipe(map((response) => response.data));
  }

  deleteMortgage(id: string): Observable<unknown> {
    return this.http.delete(`/api/mortgages/${id}`);
  }

  saveMortgageInterest(id: string, month: string, interestAmount: number): Observable<MortgageDto> {
    return this.http
      .put<ApiItem<MortgageDto>>(`/api/mortgages/${id}/interest`, {
        month,
        interest_amount: interestAmount,
      })
      .pipe(map((response) => response.data));
  }

  saveMortgageRate(id: string, effectiveDate: string, rate: number, bankRepaymentAmount: number): Observable<MortgageDto> {
    return this.http
      .put<ApiItem<MortgageDto>>(`/api/mortgages/${id}/rates`, { effective_date: effectiveDate, rate, bank_repayment_amount: bankRepaymentAmount })
      .pipe(map((response) => response.data));
  }

  deleteMortgageRate(id: string, entryId: string): Observable<MortgageDto> {
    return this.http
      .delete<ApiItem<MortgageDto>>(`/api/mortgages/${id}/rates/${entryId}`)
      .pipe(map((response) => response.data));
  }

  deleteMortgageInterest(id: string, entryId: string): Observable<MortgageDto> {
    return this.http
      .delete<ApiItem<MortgageDto>>(`/api/mortgages/${id}/interest/${entryId}`)
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

  updateInvestment(id: string, investment: CreateInvestmentRequest): Observable<InvestmentDto> {
    return this.http
      .put<ApiItem<InvestmentDto>>(`/api/investments/${id}`, investment)
      .pipe(map((response) => response.data));
  }

  deleteInvestment(id: string): Observable<unknown> {
    return this.http.delete(`/api/investments/${id}`);
  }

  createExport(financialYearId: string): Observable<ExportDto> {
    return this.http
      .post<ApiItem<ExportDto>>('/api/exports', {
        financial_year_id: financialYearId,
      })
      .pipe(map((response) => response.data));
  }

  downloadAccountantPack(financialYearId: string, format: 'csv' | 'excel' | 'pdf'): Observable<Blob> {
    return this.http.get('/api/exports/pack', {
      params: { financial_year_id: financialYearId, format },
      responseType: 'blob',
    });
  }

  downloadTransactionsCsv(financialYearId: string): Observable<Blob> {
    return this.http.get('/api/exports/transactions.csv', {
      params: { financial_year_id: financialYearId },
      responseType: 'blob',
    });
  }

  listTransactions(): Observable<TransactionDto[]> {
    return this.http
      .get<ApiList<TransactionDto>>('/api/transactions')
      .pipe(map((response) => response.data));
  }

  listTaxNews(category = 'all', recommended = false, ai = false): Observable<TaxNewsDto[]> {
    return this.http.get<ApiList<TaxNewsDto>>('/api/tax-news', { params: { category, recommended, ai } }).pipe(map((response) => response.data));
  }

  syncTaxNews(): Observable<void> {
    return this.http.post<void>('/api/tax-news/sync', {});
  }

  createTransaction(transaction: CreateTransactionRequest): Observable<TransactionDto> {
    return this.http
      .post<ApiItem<TransactionDto>>('/api/transactions', transaction)
      .pipe(map((response) => response.data));
  }

  updateTransaction(id: string, transaction: CreateTransactionRequest): Observable<TransactionDto> {
    return this.http
      .put<ApiItem<TransactionDto>>(`/api/transactions/${id}`, transaction)
      .pipe(map((response) => response.data));
  }

  deleteTransaction(id: string): Observable<unknown> {
    return this.http.delete(`/api/transactions/${id}`);
  }

  listRecurringIncome(): Observable<RecurringIncomeRuleDto[]> {
    return this.http.get<ApiList<RecurringIncomeRuleDto>>('/api/recurring-income').pipe(map((response) => response.data));
  }

  createRecurringIncome(rule: RecurringIncomeRuleRequest): Observable<RecurringIncomeRuleDto> {
    return this.http.post<ApiItem<RecurringIncomeRuleDto>>('/api/recurring-income', rule).pipe(map((response) => response.data));
  }

  updateRecurringIncome(id: string, rule: Partial<RecurringIncomeRuleRequest> & { active?: boolean }): Observable<RecurringIncomeRuleDto> {
    return this.http.put<ApiItem<RecurringIncomeRuleDto>>(`/api/recurring-income/${id}`, rule).pipe(map((response) => response.data));
  }

  deleteRecurringIncome(id: string): Observable<unknown> {
    return this.http.delete(`/api/recurring-income/${id}`);
  }

  previewMissingIncome(id: string): Observable<RecurringIncomePreview> {
    return this.http.get<ApiItem<RecurringIncomePreview>>(`/api/recurring-income/${id}/missing`).pipe(map(response => response.data));
  }

  generateMissingIncome(id: string, year: string, amount: string, dates: string[]): Observable<{ created: number; skipped: number }> {
    return this.http.post<ApiItem<{ created: number; skipped: number }>>(`/api/recurring-income/${id}/missing`, { year, amount, dates })
      .pipe(map(response => response.data));
  }

  bulkCategorise(
    ids: string[],
    atoCategory: string | null,
    propertyId: string | null,
  ): Observable<{ updated: number; skipped: number }> {
    return this.http
      .post<ApiItem<{ updated: number; skipped: number }>>('/api/transactions/bulk-categorise', {
        ids,
        ato_category: atoCategory,
        property_id: propertyId,
      })
      .pipe(map((response) => response.data));
  }

  importTransactions(rows: ImportRow[]): Observable<ImportResult> {
    return this.http
      .post<ApiItem<ImportResult>>('/api/transactions/import', { rows })
      .pipe(map((response) => response.data));
  }

  getWealth(financialYearId?: string | null): Observable<WealthSummary> {
    const params = financialYearId ? { financial_year_id: financialYearId } : undefined;

    return this.http.get<ApiItem<WealthSummary>>('/api/wealth', { params }).pipe(map((response) => response.data));
  }

  getDeadlines(): Observable<DeadlineDto[]> {
    return this.http.get<ApiItem<DeadlineDto[]>>('/api/deadlines').pipe(map((response) => response.data));
  }

  setDeadlineCompleted(date: string, completed: boolean): Observable<unknown> {
    const url = `/api/deadlines/${date}/complete`;

    return completed ? this.http.put(url, {}) : this.http.delete(url);
  }

  getBankStatus(): Observable<BankStatus> {
    return this.http.get<ApiItem<BankStatus>>('/api/bank/status').pipe(map((response) => response.data));
  }

  connectBank(): Observable<string> {
    return this.http
      .post<ApiItem<{ url: string }>>('/api/bank/connect', {})
      .pipe(map((response) => response.data.url));
  }

  syncBank(): Observable<ImportResult & { last_synced_at: string | null }> {
    return this.http
      .post<ApiItem<ImportResult & { last_synced_at: string | null }>>('/api/bank/sync', {})
      .pipe(map((response) => response.data));
  }

  getReturnSummary(financialYearId: string): Observable<ReturnSummaryDto> {
    return this.http
      .get<ApiItem<ReturnSummaryDto>>(`/api/financial-years/${financialYearId}/return-summary`)
      .pipe(map((response) => response.data));
  }

  getTaxReadiness(financialYearId: string): Observable<TaxReadinessDto> {
    return this.http
      .get<ApiItem<TaxReadinessDto>>(`/api/financial-years/${financialYearId}/tax-readiness`)
      .pipe(map((response) => response.data));
  }

  getWorkFromHome(
    financialYearId: string,
  ): Observable<{ summary: WorkFromHomeSummary; logs: WorkFromHomeLogDto[] }> {
    return this.http
      .get<ApiItem<{ summary: WorkFromHomeSummary; logs: WorkFromHomeLogDto[] }>>(
        '/api/work-from-home',
        { params: { financial_year_id: financialYearId } },
      )
      .pipe(map((response) => response.data));
  }

  saveWorkFromHome(workDate: string, hours: number, note: string): Observable<WorkFromHomeLogDto> {
    return this.http
      .post<ApiItem<WorkFromHomeLogDto>>('/api/work-from-home', {
        work_date: workDate,
        hours,
        note: note || null,
      })
      .pipe(map((response) => response.data));
  }

  deleteWorkFromHome(id: string): Observable<unknown> {
    return this.http.delete(`/api/work-from-home/${id}`);
  }

  saveWorkFromHomeRate(financialYearId: string, rate: number): Observable<WorkFromHomeSummary> {
    return this.http
      .put<ApiItem<WorkFromHomeSummary>>(`/api/financial-years/${financialYearId}/wfh-rate`, { rate })
      .pipe(map((response) => response.data));
  }
}
