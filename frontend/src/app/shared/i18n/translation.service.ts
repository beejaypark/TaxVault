import { HttpClient } from '@angular/common/http';
import { Injectable, effect, inject, signal } from '@angular/core';
import { catchError, forkJoin, of } from 'rxjs';

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

    if (typeof value === 'string') {
      return value;
    }

    const leaf = key.split('.').pop() || key;
    return leaf.replace(/([a-z0-9])([A-Z])/g, '$1 $2').replace(/([A-Z])([A-Z][a-z])/g, '$1 $2');
  }

  private load(locale: LocaleCode): void {
    forkJoin({
      messages: this.http.get<TranslationMap>(`/assets/i18n/${locale}.json`).pipe(catchError(() => of(null))),
      fallback: this.http.get<TranslationMap>('/assets/i18n/en.json').pipe(catchError(() => of(null))),
    }).subscribe({
      next: ({ messages, fallback }) => {
        if (fallback) {
          this.fallbackMessages.set(fallback);
        }

        this.messages.set(messages ?? fallback ?? this.messages());
      },
    });
  }
}
