import { HttpClient } from '@angular/common/http';
import { Injectable, effect, inject, signal } from '@angular/core';
import { forkJoin } from 'rxjs';

import { activeLocale, LocaleCode } from '../../i18n/locale';

type TranslationMap = Record<string, string>;

@Injectable({ providedIn: 'root' })
export class TranslationService {
  private readonly http = inject(HttpClient);
  private readonly messages = signal<TranslationMap>({});
  private readonly fallbackMessages = signal<TranslationMap>({});

  constructor() {
    effect(() => this.load(activeLocale()));
  }

  translate(key: string): string {
    const read = (messages: TranslationMap): unknown => key.split('.').reduce<unknown>((current, segment) => {
      if (!current || typeof current !== 'object') {
        return undefined;
      }

      return (current as Record<string, unknown>)[segment];
    }, messages);

    const value = read(this.messages()) ?? read(this.fallbackMessages());

    return typeof value === 'string' ? value : key;
  }

  private load(locale: LocaleCode): void {
    forkJoin({
      messages: this.http.get<TranslationMap>(`/assets/i18n/${locale}.json`),
      fallback: this.http.get<TranslationMap>('/assets/i18n/en.json'),
    }).subscribe({
      next: ({ messages, fallback }) => {
        this.messages.set(messages);
        this.fallbackMessages.set(fallback);
      },
      error: () => this.messages.set({}),
    });
  }
}
