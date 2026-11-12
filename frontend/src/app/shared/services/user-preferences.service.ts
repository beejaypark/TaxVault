import { HttpClient } from '@angular/common/http';
import { Injectable, effect, inject, signal } from '@angular/core';
import { catchError, map, of } from 'rxjs';

import { AuthService } from './auth/auth.service';
import { DashboardWidgetLayout, UserAiPreferences } from './auth/auth.service';
import { LocaleCode, DEFAULT_LOCALE } from '../../i18n/locale';

export interface UserPreferences {
  language: LocaleCode;
  notifyDocuments: boolean;
  notifyUncategorised: boolean;
  notifyTaxNews: boolean;
  defaultFinancialYear: 'current' | 'all';
  emailReviewDigest: boolean;
  emailDeadlineReminders: boolean;
  lodgementDeadline: string | null;
  dashboardLayout: DashboardWidgetLayout[];
  ai: UserAiPreferences;
}

const STORAGE_KEY = 'taxvault.preferences';
const DEFAULTS: UserPreferences = {
  language: DEFAULT_LOCALE,
  notifyDocuments: true,
  notifyUncategorised: true,
  notifyTaxNews: true,
  defaultFinancialYear: 'current',
  emailReviewDigest: false,
  emailDeadlineReminders: false,
  lodgementDeadline: null,
  dashboardLayout: [],
  ai: { enabled: true, provider: 'openai', model: 'gpt-4o-mini', api_key_configured: false },
};

function normalise(stored: Partial<UserPreferences> | null | undefined): UserPreferences {
  return {
    language: stored?.language === 'kr' || stored?.language === 'cn' || stored?.language === 'es' ? stored.language : DEFAULTS.language,
    notifyDocuments: stored?.notifyDocuments ?? DEFAULTS.notifyDocuments,
    notifyUncategorised: stored?.notifyUncategorised ?? DEFAULTS.notifyUncategorised,
    notifyTaxNews: stored?.notifyTaxNews ?? DEFAULTS.notifyTaxNews,
    defaultFinancialYear: stored?.defaultFinancialYear === 'all' ? 'all' : 'current',
    emailReviewDigest: stored?.emailReviewDigest ?? DEFAULTS.emailReviewDigest,
    emailDeadlineReminders: stored?.emailDeadlineReminders ?? DEFAULTS.emailDeadlineReminders,
    lodgementDeadline: stored?.lodgementDeadline || null,
    dashboardLayout: Array.isArray(stored?.dashboardLayout)
      ? stored!.dashboardLayout!.filter((item): item is DashboardWidgetLayout =>
          !!item
          && typeof item.id === 'string'
          && typeof item.visible === 'boolean'
          && Number.isInteger(item.order),
        )
      : DEFAULTS.dashboardLayout,
    ai: stored?.ai && typeof stored.ai === 'object'
      ? {
          enabled: stored.ai.enabled ?? DEFAULTS.ai.enabled,
          provider: stored.ai.provider ?? DEFAULTS.ai.provider,
          model: stored.ai.model || DEFAULTS.ai.model,
          api_key_configured: stored.ai.api_key_configured ?? false,
        }
      : DEFAULTS.ai,
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

  updateAi(settings: { enabled: boolean; provider: 'openai' | 'anthropic' | 'gemini'; model: string; api_key?: string; clear_api_key?: boolean }) {
    const payload = { ...this.preferences(), ai: settings };

    return this.http.put<{ data: UserPreferences }>('/api/auth/preferences', payload, { withCredentials: true }).pipe(
      map((response) => {
        const next = normalise(response.data);
        this.preferences.set(next);
        this.store(next);
        return next.ai;
      }),
      catchError(() => of(this.preferences().ai)),
    );
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
