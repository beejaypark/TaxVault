import { HttpClient } from '@angular/common/http';
import { Injectable, effect, inject, signal } from '@angular/core';
import { catchError, map, of } from 'rxjs';

import { AuthService } from './auth/auth.service';
import { LocaleCode, DEFAULT_LOCALE } from '../../i18n/locale';

export interface UserPreferences {
  language: LocaleCode;
  notifyDocuments: boolean;
  notifyUncategorised: boolean;
  defaultFinancialYear: 'current' | 'all';
  emailReviewDigest: boolean;
  emailDeadlineReminders: boolean;
  lodgementDeadline: string | null;
}

const STORAGE_KEY = 'taxvault.preferences';
const DEFAULTS: UserPreferences = {
  language: DEFAULT_LOCALE,
  notifyDocuments: true,
  notifyUncategorised: true,
  defaultFinancialYear: 'current',
  emailReviewDigest: false,
  emailDeadlineReminders: false,
  lodgementDeadline: null,
};

function normalise(stored: Partial<UserPreferences> | null | undefined): UserPreferences {
  return {
    language: stored?.language === 'kr' || stored?.language === 'cn' || stored?.language === 'es' ? stored.language : DEFAULTS.language,
    notifyDocuments: stored?.notifyDocuments ?? DEFAULTS.notifyDocuments,
    notifyUncategorised: stored?.notifyUncategorised ?? DEFAULTS.notifyUncategorised,
    defaultFinancialYear: stored?.defaultFinancialYear === 'all' ? 'all' : 'current',
    emailReviewDigest: stored?.emailReviewDigest ?? DEFAULTS.emailReviewDigest,
    emailDeadlineReminders: stored?.emailDeadlineReminders ?? DEFAULTS.emailDeadlineReminders,
    lodgementDeadline: stored?.lodgementDeadline || null,
  };
}

/** Preferences are saved to the account; the browser copy is a fallback for offline or signed-out use. */
@Injectable({ providedIn: 'root' })
export class UserPreferencesService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AuthService);
  private syncedUserId: string | null = null;

  readonly preferences = signal<UserPreferences>(this.initial());

  constructor() {
    effect(() => this.syncWithAccount());
  }

  update(changes: Partial<UserPreferences>): void {
    const next = { ...this.preferences(), ...changes };

    this.preferences.set(next);
    this.store(next);
    this.save(next).subscribe();
  }

  updateAndWait(changes: Partial<UserPreferences>) {
    const next = { ...this.preferences(), ...changes };

    this.preferences.set(next);
    this.store(next);

    return this.save(next);
  }

  private initial(): UserPreferences {
    const user = this.auth.user();

    if (user?.preferences) {
      this.syncedUserId = user.id;
      const preferences = normalise(user.preferences);
      this.store(preferences);

      return preferences;
    }

    return normalise(this.readLocal());
  }

  private syncWithAccount(): void {
    const user = this.auth.user();

    if (!user || user.id === this.syncedUserId) {
      return;
    }

    this.syncedUserId = user.id;

    if (user.preferences) {
      const preferences = normalise(user.preferences);
      this.preferences.set(preferences);
      this.store(preferences);
    } else {
      // First sign-in since preferences moved to the account: keep this browser's choices.
      this.save(this.preferences());
    }
  }

  private save(preferences: UserPreferences) {
    if (!this.auth.user()) {
      return of(undefined);
    }

    return this.http.put('/api/auth/preferences', preferences, { withCredentials: true }).pipe(
      map(() => undefined),
      catchError(() => of(undefined)),
    );
  }

  private readLocal(): Partial<UserPreferences> | null {
    try {
      return JSON.parse(localStorage.getItem(STORAGE_KEY) ?? 'null') as Partial<UserPreferences> | null;
    } catch {
      return null;
    }
  }

  private store(preferences: UserPreferences): void {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(preferences));
    } catch {
      // Preferences stay in memory when storage is unavailable.
    }
  }
}
