import { ChangeDetectionStrategy, Component, effect, inject } from '@angular/core';
import { RouterOutlet } from '@angular/router';

import { activeLocale, angularLocale, isLocaleCode } from './i18n/locale';
import { AuthService } from './shared/services/auth/auth.service';
import { AnnouncementStripComponent } from './shared/components/announcement-strip/announcement-strip.component';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, AnnouncementStripComponent],
  template: '<app-announcement-strip /><router-outlet />',
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

      activeLocale.set(language);
      document.documentElement.lang = angularLocale(language);
    });
  }
}
