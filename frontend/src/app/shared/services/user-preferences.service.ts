import { HttpClient } from '@angular/common/http';
import { Injectable, effect, inject, signal } from '@angular/core';

import { AuthService } from './auth/auth.service';

export interface UserPreferences {
  notifyDocuments: boolean;
  notifyUncategorised: boolean;
  defaultFinancialYear: 'current' | 'all';
  emailReviewDigest: boolean;
  emailDeadlineReminders: boolean;
  lodgementDeadline: string | null;
}

const STORAGE_KEY = 'taxvault.preferences';
const DEFAULTS: UserPreferences = {
  notifyDocuments: true,
  notifyUncategorised: true,
  defaultFinancialYear: 'current',
  emailReviewDigest: false,
  emailDeadlineReminders: false,
  lodgementDeadline: null,
};

function normalise(stored: Partial<UserPreferences> | null | undefined): UserPreferences {
  return {
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
    this.save(next);
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

  private save(preferences: UserPreferences): void {
    if (!this.auth.user()) {
      return;
    }

    this.http.put('/api/auth/preferences', preferences, { withCredentials: true }).subscribe({
      error: () => undefined,
    });
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
