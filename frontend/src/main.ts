import { provideZoneChangeDetection } from '@angular/core';
import { bootstrapApplication } from '@angular/platform-browser';
import { appConfig } from './app/app.config';
import { AppComponent } from './app/app.component';
import {
  DEFAULT_LOCALE,
  LocaleCode,
  activeLocale,
  angularLocale,
  localeFromPath,
} from './app/i18n/locale';

const currentLocale = localeFromPath(window.location.pathname);
const legacyLocaleSegment = window.location.pathname.match(/^\/([a-z]{2})(?=\/|$)/i)?.[1]?.toLowerCase() ?? null;

if (legacyLocaleSegment) {
  const withoutLocale = window.location.pathname.replace(/^\/[a-z]{2}(?=\/|$)/i, '') || '/';
  window.history.replaceState({}, '', `${withoutLocale}${window.location.search}${window.location.hash}`);
}

let storedLocale: string | null = null;
try {
  const stored = JSON.parse(window.localStorage.getItem('taxvault.preferences') ?? 'null') as { language?: string } | null;
  storedLocale = stored?.language ?? null;
} catch {
  storedLocale = null;
}

const browserLocale = (navigator.language || '').toLowerCase().split('-')[0];
const initialLocale = currentLocale
  ?? (storedLocale === 'kr' || storedLocale === 'cn' || storedLocale === 'es' ? storedLocale : null)
  ?? (browserLocale === 'ko' ? 'kr' : browserLocale === 'zh' ? 'cn' : browserLocale === 'es' ? 'es' : DEFAULT_LOCALE);

activeLocale.set(initialLocale as LocaleCode);
document.documentElement.lang = angularLocale(initialLocale as LocaleCode);

bootstrapApplication(AppComponent, {
  ...appConfig,
  providers: [
    provideZoneChangeDetection(),
    ...(appConfig.providers ?? []),
  ],
}).catch((err) => console.error(err));
