import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import {
  HttpTestingController,
  provideHttpClientTesting,
} from '@angular/common/http/testing';

import { AuthService, AuthUser } from './auth.service';

describe('AuthService', () => {
  let service: AuthService;
  let http: HttpTestingController;

  const user: AuthUser = {
    id: 'user-123',
    email: 'admin@example.test',
    display_name: 'Development Admin',
    identity_provider: 'local',
  };

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        AuthService,
      ],
    });

    service = TestBed.inject(AuthService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('restores an authenticated session from the current-user endpoint', () => {
    let result: AuthUser | null | undefined;

    service.initialize().subscribe((value) => (result = value));

    const request = http.expectOne('/api/auth/user');
    expect(request.request.method).toBe('GET');
    expect(request.request.withCredentials).toBe(true);
    request.flush({ data: user });

    expect(result).toEqual(user);
    expect(service.currentUser).toEqual(user);
  });

  it('treats an unauthenticated session as signed out', () => {
    let result: AuthUser | null | undefined;

    service.initialize().subscribe((value) => (result = value));
    http.expectOne('/api/auth/user').flush(
      { message: 'Unauthenticated.' },
      { status: 401, statusText: 'Unauthorized' },
    );

    expect(result).toBeNull();
    expect(service.currentUser).toBeNull();
  });

  it('requests a CSRF cookie before submitting login credentials', () => {
    let result: AuthUser | undefined;

    service.login('admin', 'test-password').subscribe((value) => (result = value));

    const csrf = http.expectOne('/sanctum/csrf-cookie');
    expect(csrf.request.method).toBe('GET');
    expect(csrf.request.withCredentials).toBe(true);
    csrf.flush(null);

    const login = http.expectOne('/api/auth/login');
    expect(login.request.method).toBe('POST');
    expect(login.request.body).toEqual({
      login: 'admin',
      password: 'test-password',
    });
    expect(login.request.withCredentials).toBe(true);
    login.flush({ data: user });

    expect(result).toEqual(user);
    expect(service.currentUser).toEqual(user);
  });

  it('clears local authentication state on logout', () => {
    service.user.set(user);

    service.logout().subscribe();

    const request = http.expectOne('/api/auth/logout');
    expect(request.request.method).toBe('POST');
    expect(request.request.withCredentials).toBe(true);
    request.flush({ message: 'Logged out.' });

    expect(service.currentUser).toBeNull();
  });
});
