import { Injectable, signal } from '@angular/core';

export interface UserPreferences {
  notifyDocuments: boolean;
  notifyUncategorised: boolean;
  defaultFinancialYear: 'current' | 'all';
}

const STORAGE_KEY = 'taxvault.preferences';
const DEFAULTS: UserPreferences = {
  notifyDocuments: true,
  notifyUncategorised: true,
  defaultFinancialYear: 'current',
};

@Injectable({ providedIn: 'root' })
export class UserPreferencesService {
  readonly preferences = signal<UserPreferences>(this.load());

  update(changes: Partial<UserPreferences>): void {
    const next = { ...this.preferences(), ...changes };

    this.preferences.set(next);

    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
    } catch {
      // Preferences stay in memory when storage is unavailable.
    }
  }

  private load(): UserPreferences {
    try {
      const stored = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}') as Partial<UserPreferences>;

      return {
        notifyDocuments: stored.notifyDocuments ?? DEFAULTS.notifyDocuments,
        notifyUncategorised: stored.notifyUncategorised ?? DEFAULTS.notifyUncategorised,
        defaultFinancialYear: stored.defaultFinancialYear === 'all' ? 'all' : 'current',
      };
    } catch {
      return { ...DEFAULTS };
    }
  }
}
