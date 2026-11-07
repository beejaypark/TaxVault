import { HttpClient } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';

import { AuthService } from '../../shared/services/auth/auth.service';
import { buildReminderIcs } from '../../shared/utils/calendar';
import { UserPreferencesService } from '../../shared/services/user-preferences.service';
import { LocaleCode, angularLocale, pathWithLocale } from '../../i18n/locale';
import { TranslatePipe } from '../../shared/i18n/translate.pipe';

@Component({
  selector: 'app-settings',
  imports: [FormsModule, RouterLink, TranslatePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="settings-page">
      <header>
        <div class="eyebrow">{{ 'Settings.Account' | translate }}</div>
        <h1>{{ 'Settings.Title' | translate }}</h1>
        <p>{{ 'Settings.Description' | translate }}</p>
      </header>

      <section class="card" aria-labelledby="profile-heading">
        <h2 id="profile-heading">{{ 'Settings.Profile' | translate }}</h2>
        <dl>
          <div><dt>{{ 'Settings.Name' | translate }}</dt><dd>{{ auth.currentUser?.display_name || '—' }}</dd></div>
          <div><dt>{{ 'Settings.Email' | translate }}</dt><dd>{{ auth.currentUser?.email || '—' }}</dd></div>
        </dl>
        <p class="hint">{{ 'Settings.ProfileHint' | translate }}</p>
      </section>

      <section class="card" aria-labelledby="general-heading">
        <h2 id="general-heading">{{ 'Settings.General' | translate }}</h2>
        <label class="field">
          <span>{{ 'Settings.Language' | translate }}</span>
          <select name="language" [ngModel]="prefs().language" (ngModelChange)="changeLanguage($event)">
            <option value="en">English</option>
            <option value="kr">한국어</option>
            <option value="cn">简体中文</option>
            <option value="es">Español</option>
          </select>
        </label>
        <label class="field">
          <span>{{ 'Settings.FinancialYearOnSignIn' | translate }}</span>
          <select name="defaultYear" [ngModel]="prefs().defaultFinancialYear" (ngModelChange)="update({ defaultFinancialYear: $event })">
            <option value="current">{{ 'Settings.CurrentFinancialYear' | translate }}</option>
            <option value="all">All financial years</option>
          </select>
        </label>
        <p class="hint">{{ 'Settings.GeneralHint' | translate }}</p>
      </section>

      <section class="card" aria-labelledby="notify-heading">
        <h2 id="notify-heading">{{ 'Settings.Notifications' | translate }}</h2>
        <p class="hint">{{ 'Settings.NotificationsHint' | translate }}</p>
        <label class="toggle">
          <input type="checkbox" name="notifyDocuments" [ngModel]="prefs().notifyDocuments" (ngModelChange)="update({ notifyDocuments: $event })" />
          <span><strong>{{ 'Settings.DocumentsNotification' | translate }}</strong><small>{{ 'Settings.DocumentsNotificationHint' | translate }}</small></span>
        </label>
        <label class="toggle">
          <input type="checkbox" name="notifyUncategorised" [ngModel]="prefs().notifyUncategorised" (ngModelChange)="update({ notifyUncategorised: $event })" />
          <span><strong>{{ 'Settings.UncategorisedTransactions' | translate }}</strong><small>{{ 'Settings.UncategorisedTransactionsHint' | translate }}</small></span>
        </label>
      </section>

      @if (!auth.isAccountant) {
        <section class="card" aria-labelledby="email-heading">
          <h2 id="email-heading">{{ 'Settings.EmailReminders' | translate }}</h2>
          <p class="hint">{{ 'Settings.EmailRemindersHint' | translate }}</p>
          <label class="toggle">
            <input type="checkbox" name="emailReviewDigest" [ngModel]="prefs().emailReviewDigest" (ngModelChange)="update({ emailReviewDigest: $event })" />
            <span><strong>{{ 'Settings.WeeklyReviewDigest' | translate }}</strong><small>{{ 'Settings.WeeklyReviewDigestHint' | translate }}</small></span>
          </label>
          <label class="toggle">
            <input type="checkbox" name="emailDeadlineReminders" [ngModel]="prefs().emailDeadlineReminders" (ngModelChange)="update({ emailDeadlineReminders: $event })" />
            <span><strong>{{ 'Settings.DeadlineReminders' | translate }}</strong><small>{{ 'Settings.DeadlineRemindersHint' | translate }}</small></span>
          </label>
          <label class="field">
            <span>{{ 'Settings.LodgementDate' | translate }}</span>
            <input type="date" name="lodgementDeadline" [ngModel]="prefs().lodgementDeadline" (ngModelChange)="update({ lodgementDeadline: $event || null })" />
          </label>
          <p class="hint">{{ 'Settings.DeadlineHint' | translate }}</p>
          <div class="actions">
            <button type="button" class="link-button" (click)="sendTest()" [disabled]="sending()">{{ sending() ? ('Settings.Sending' | translate) : ('Settings.SendTestEmail' | translate) }}</button>
            <button type="button" class="link-button" (click)="downloadIcs()">{{ 'Settings.AddToCalendar' | translate }}</button>
          </div>
          @if (testMessage()) {
            <p class="hint" role="status">{{ testMessage() }}</p>
          }
        </section>

        <section class="card" aria-labelledby="access-heading">
          <h2 id="access-heading">{{ 'Settings.Access' | translate }}</h2>
          <p class="hint">{{ 'Settings.AccessHint' | translate }}</p>
          <a routerLink="/accountants" class="link-button">{{ 'Settings.ManageAccountants' | translate }}</a>
        </section>
      }
    </div>
  `,
  styles: [`
    .settings-page { display: grid; gap: 20px; width: min(100%, 800px); margin: 0 auto; padding: 40px; box-sizing: border-box; }
    @media (max-width: 700px) { .settings-page { padding: 20px 16px; } }
    .eyebrow { color: var(--tv-color-primary); font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
    h1 { margin: 4px 0 6px; font-size: 32px; font-weight: 500; }
    header p, .hint { margin: 0; color: var(--tv-color-text-secondary); font-size: 13px; }
    .card { display: grid; gap: 14px; padding: 24px; border: 1px solid var(--tv-color-border); border-radius: var(--tv-radius-lg); background: var(--tv-color-surface); box-shadow: var(--tv-shadow-card); }
    h2 { margin: 0; font-size: 17px; }
    dl { display: grid; gap: 10px; margin: 0; }
    dl div { display: grid; grid-template-columns: 100px 1fr; gap: 12px; }
    dt { color: var(--tv-color-text-muted); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    dd { margin: 0; font-size: 14px; font-weight: 600; overflow-wrap: anywhere; }
    .field { display: grid; gap: 8px; max-width: 320px; }
    .field span { color: var(--tv-color-text-secondary); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    select { min-height: 42px; padding: 0 12px; border: 1px solid var(--tv-color-border); border-radius: var(--tv-radius-sm); background: var(--tv-color-surface); font-size: 14px; }
    .toggle { display: flex; align-items: flex-start; gap: 12px; padding: 12px; border: 1px solid var(--tv-color-border); border-radius: var(--tv-radius-sm); cursor: pointer; }
    .toggle input { width: 18px; height: 18px; margin-top: 2px; accent-color: var(--tv-color-primary); }
    .toggle span { display: grid; gap: 2px; }
    .toggle small { color: var(--tv-color-text-secondary); font-size: 12px; }
    input[type=date] { min-height: 42px; padding: 0 12px; border: 1px solid var(--tv-color-border); border-radius: var(--tv-radius-sm); background: var(--tv-color-surface); font-size: 14px; }
    .actions { display: flex; flex-wrap: wrap; gap: 10px; }
    button.link-button { background: var(--tv-color-surface); cursor: pointer; }
    .link-button { justify-self: start; padding: 9px 16px; border: 1px solid var(--tv-color-border); border-radius: var(--tv-radius-sm); color: var(--tv-color-primary); font-size: 13px; font-weight: 600; }
  `],
})
export class SettingsComponent {
  readonly auth = inject(AuthService);
  private readonly preferences = inject(UserPreferencesService);

  private readonly http = inject(HttpClient);

  readonly prefs = this.preferences.preferences;
  readonly sending = signal(false);
  readonly testMessage = signal('');

  sendTest(): void {
    this.sending.set(true);
    this.testMessage.set('');
    this.http.post('/api/auth/preferences/test-email', {}).subscribe({
      next: () => {
        this.sending.set(false);
        this.testMessage.set('Test email sent. Check your inbox.');
      },
      error: () => {
        this.sending.set(false);
        this.testMessage.set('Could not send the test email. Check the mail settings on the server.');
      },
    });
  }

  downloadIcs(): void {
    const chosen = this.prefs().lodgementDeadline;
    const today = new Date();
    let date = chosen;
    if (!date) {
      const year = today.getMonth() > 9 || (today.getMonth() === 9 && today.getDate() > 31) ? today.getFullYear() + 1 : today.getFullYear();
      date = `${year}-10-31`;
    }
    const ics = buildReminderIcs(date, 'Tax return due (reminder)', 'Gather your records in TaxVault. Confirm the real due date with the ATO or a tax agent.');
    const url = URL.createObjectURL(new Blob([ics], { type: 'text/calendar' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = 'taxvault-lodgement-reminder.ics';
    link.click();
    URL.revokeObjectURL(url);
  }

  update(changes: Parameters<UserPreferencesService['update']>[0]): void {
    this.preferences.update(changes);
  }

  changeLanguage(value: string): void {
    if (!['en', 'kr', 'cn', 'es'].includes(value)) {
      return;
    }

    const locale = value as LocaleCode;
    this.preferences.updateAndWait({ language: locale }).subscribe(() => {
      document.documentElement.lang = angularLocale(locale);
      window.location.assign(pathWithLocale(window.location.pathname, locale));
    });
  }
}
