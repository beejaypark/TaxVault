import { signal } from '@angular/core';

export type LocaleCode = 'en' | 'kr' | 'cn' | 'es';

export const DEFAULT_LOCALE: LocaleCode = 'en';
export const SUPPORTED_LOCALES: readonly LocaleCode[] = ['en', 'kr', 'cn', 'es'];

const LOCALE_DATA: Record<LocaleCode, string> = {
  en: 'en-AU',
  kr: 'ko-KR',
  cn: 'zh-CN',
  es: 'es-ES',
};

export const activeLocale = signal<LocaleCode>(DEFAULT_LOCALE);

export function isLocaleCode(value: string | undefined): value is LocaleCode {
  return !!value && SUPPORTED_LOCALES.includes(value as LocaleCode);
}

export function localeFromPath(pathname: string): LocaleCode | null {
  const firstSegment = pathname.split('/').filter(Boolean)[0];
  return isLocaleCode(firstSegment) ? firstSegment : null;
}

export function angularLocale(locale: LocaleCode): string {
  return LOCALE_DATA[locale];
}

export function pathWithLocale(pathname: string, locale: LocaleCode): string {
  const withoutLocale = pathname.replace(/^\/(en|kr|cn|es)(?=\/|$)/, '') || '/';
  return `/${locale}${withoutLocale.startsWith('/') ? withoutLocale : `/${withoutLocale}`}`;
}
