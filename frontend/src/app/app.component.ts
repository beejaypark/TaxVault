import { ChangeDetectionStrategy, Component, effect, inject } from '@angular/core';
import { RouterOutlet } from '@angular/router';

import { activeLocale, isLocaleCode, pathWithLocale } from './i18n/locale';
import { AuthService } from './shared/services/auth/auth.service';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet],
  template: '<router-outlet />',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AppComponent {
  title = 'TaxVault';

  private readonly auth = inject(AuthService);

  constructor() {
    effect(() => {
      const language = this.auth.user()?.preferences?.language;

      if (!language || !isLocaleCode(language) || language === activeLocale()) {
        return;
      }

      const targetPath = pathWithLocale(window.location.pathname, language);
      window.location.assign(`${targetPath}${window.location.search}${window.location.hash}`);
    });
  }
}
