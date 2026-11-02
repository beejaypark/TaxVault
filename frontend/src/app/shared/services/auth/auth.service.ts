import { Injectable, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
import {
  Observable,
  catchError,
  finalize,
  map,
  of,
  shareReplay,
  switchMap,
  tap,
  throwError,
} from 'rxjs';

export interface AuthUser {
  id: string;
  email: string;
  display_name: string;
  identity_provider: string;
}

interface ApiResponse<T> {
  data: T;
}

interface LogoutResponse {
  logout_url: string | null;
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  readonly user = signal<AuthUser | null>(null);

  private initialized = false;
  private initialization?: Observable<AuthUser | null>;

  constructor(private readonly http: HttpClient) {}

  get currentUser(): AuthUser | null {
    return this.user();
  }

  initialize(): Observable<AuthUser | null> {
    if (this.initialized) {
      return of(this.user());
    }

    if (!this.initialization) {
      this.initialization = this.http
        .get<ApiResponse<AuthUser>>('/api/auth/user', {
          withCredentials: true,
        })
        .pipe(
          map((response) => response.data),
          catchError((error: HttpErrorResponse) =>
            error.status === 401 ? of(null) : throwError(() => error),
          ),
          tap((user) => this.user.set(user)),
          finalize(() => {
            this.initialized = true;
          }),
          shareReplay(1),
        );
    }

    return this.initialization;
  }

  login(login: string, password: string): Observable<AuthUser> {
    return this.http
      .get<void>('/sanctum/csrf-cookie', {
        withCredentials: true,
      })
      .pipe(
        switchMap(() =>
          this.http.post<ApiResponse<AuthUser>>(
            '/api/auth/login',
            { login, password },
            { withCredentials: true },
          ),
        ),
        map((response) => response.data),
        tap((user) => {
          this.user.set(user);
          this.initialized = true;
          this.initialization = of(user);
        }),
      );
  }

  oidcLoginUrl(returnUrl: string): string {
    const backendOrigin =
      window.location.port === '4200' ? 'http://localhost:8080' : '';

    return `${backendOrigin}/api/auth/oidc/redirect?returnUrl=${encodeURIComponent(returnUrl)}`;
  }

  logout(): Observable<string | null> {
    return this.http
      .post<ApiResponse<LogoutResponse>>(
        '/api/auth/logout',
        {},
        { withCredentials: true },
      )
      .pipe(
        map((response) => response.data.logout_url),
        tap(() => {
          this.user.set(null);
          this.initialized = true;
          this.initialization = of(null);
        }),
      );
  }
}
