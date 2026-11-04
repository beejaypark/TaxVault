import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';

import { AccessSection } from './auth/auth.service';

export interface AccountantDto {
  id: string;
  name: string;
  email: string;
  firm: string | null;
  status: string;
  financial_year_ids: string[] | null;
  sections: AccessSection[];
  last_invited_at: string | null;
  last_pack_sent_at: string | null;
  temporary_password?: string;
}

export interface AccountantRequest {
  name: string;
  email?: string;
  firm?: string | null;
  sections: AccessSection[];
  financial_year_ids: string[] | null;
}

@Injectable({ providedIn: 'root' })
export class AccountantsApiService {
  private readonly http = inject(HttpClient);

  list(): Observable<AccountantDto[]> {
    return this.http.get<{ data: AccountantDto[] }>('/api/accountants').pipe(map((r) => r.data));
  }

  create(payload: AccountantRequest): Observable<AccountantDto> {
    return this.http.post<{ data: AccountantDto }>('/api/accountants', payload).pipe(map((r) => r.data));
  }

  update(id: string, payload: AccountantRequest): Observable<AccountantDto> {
    return this.http
      .patch<{ data: AccountantDto }>(`/api/accountants/${id}`, payload)
      .pipe(map((r) => r.data));
  }

  revoke(id: string): Observable<unknown> {
    return this.http.delete(`/api/accountants/${id}`);
  }

  resetPassword(id: string): Observable<string> {
    return this.http
      .post<{ data: { temporary_password: string } }>(`/api/accountants/${id}/reset-password`, {})
      .pipe(map((r) => r.data.temporary_password));
  }

  sendPack(id: string, payload: { financial_year_id?: string | null; note?: string | null }): Observable<AccountantDto> {
    return this.http
      .post<{ data: AccountantDto }>(`/api/accountants/${id}/send-pack`, payload)
      .pipe(map((r) => r.data));
  }
}
