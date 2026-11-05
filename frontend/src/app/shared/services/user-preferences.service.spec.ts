import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';

import { AuthService, AuthUser } from './auth/auth.service';
import { UserPreferencesService } from './user-preferences.service';

describe('UserPreferencesService', () => {
  const user = signal<AuthUser | null>(null);

  function setup(): { service: UserPreferencesService; http: HttpTestingController } {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: AuthService, useValue: { user } },
      ],
    });

    return { service: TestBed.inject(UserPreferencesService), http: TestBed.inject(HttpTestingController) };
  }

  beforeEach(() => {
    localStorage.removeItem('taxvault.preferences');
    user.set(null);
    TestBed.resetTestingModule();
  });

  it('uses defaults when nothing is stored', () => {
    expect(setup().service.preferences()).toEqual({
      notifyDocuments: true,
      notifyUncategorised: true,
      defaultFinancialYear: 'current',
      emailReviewDigest: false,
      emailDeadlineReminders: false,
      lodgementDeadline: null,
    });
  });

  it('falls back to defaults on corrupt storage', () => {
    localStorage.setItem('taxvault.preferences', '{not json');

    expect(setup().service.preferences().notifyDocuments).toBe(true);
  });

  it('prefers the saved account preferences', () => {
    user.set({
      id: 'u1',
      email: 'a@b.c',
      display_name: 'A',
      identity_provider: 'x',
      preferences: { notifyDocuments: false, defaultFinancialYear: 'all' },
    });

    expect(setup().service.preferences()).toEqual({
      notifyDocuments: false,
      notifyUncategorised: true,
      defaultFinancialYear: 'all',
      emailReviewDigest: false,
      emailDeadlineReminders: false,
      lodgementDeadline: null,
    });
  });

  it('saves updates to the account and the browser when signed in', () => {
    user.set({ id: 'u1', email: 'a@b.c', display_name: 'A', identity_provider: 'x', preferences: {} });
    const { service, http } = setup();

    service.update({ notifyUncategorised: false });

    const request = http.expectOne('/api/auth/preferences');
    expect(request.request.method).toBe('PUT');
    expect(request.request.body.notifyUncategorised).toBe(false);
    expect(JSON.parse(localStorage.getItem('taxvault.preferences') ?? '{}').notifyUncategorised).toBe(false);
  });

  it('does not call the server when signed out', () => {
    const { service, http } = setup();

    service.update({ notifyDocuments: false });

    http.expectNone('/api/auth/preferences');
    expect(service.preferences().notifyDocuments).toBe(false);
  });
});
