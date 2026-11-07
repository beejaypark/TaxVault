import { APP_BASE_HREF } from '@angular/common';
import { provideZoneChangeDetection } from '@angular/core';
import { bootstrapApplication } from '@angular/platform-browser';
import { appConfig } from './app/app.config';
import { AppComponent } from './app/app.component';
import {
  DEFAULT_LOCALE,
  activeLocale,
  angularLocale,
  localeFromPath,
  pathWithLocale,
} from './app/i18n/locale';

const currentLocale = localeFromPath(window.location.pathname);

if (!currentLocale) {
  window.location.replace(pathWithLocale(window.location.pathname, DEFAULT_LOCALE));
} else {
  activeLocale.set(currentLocale);
  document.documentElement.lang = angularLocale(currentLocale);

  const baseHref = `/${currentLocale}/`;
  document.querySelector('base')?.setAttribute('href', baseHref);

  bootstrapApplication(AppComponent, {
    ...appConfig,
    providers: [
      provideZoneChangeDetection(),
      ...(appConfig.providers ?? []),
      { provide: APP_BASE_HREF, useValue: baseHref },
    ],
  }).catch((err) => console.error(err));
}
