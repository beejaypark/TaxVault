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

      <section class="card" aria-labelledby="ai-heading">
        <h2 id="ai-heading">AI features</h2>
        <p class="hint">Use AI to summarise personalised Tax News. Your key is stored encrypted and is never displayed again.</p>
        <label class="toggle">
          <input type="checkbox" name="aiEnabled" [ngModel]="prefs().ai.enabled" (ngModelChange)="aiEnabled = $event" />
          <span><strong>Enable AI summaries</strong><small>Disable this to use rule-based recommendations only.</small></span>
        </label>
        <label class="field">
          <span>Provider</span>
          <select name="aiProvider" [(ngModel)]="aiProvider" (ngModelChange)="selectAiProvider($event)">
            <option value="openai">ChatGPT / OpenAI</option>
            <option value="anthropic">Claude</option>
            <option value="gemini">Gemini</option>
          </select>
        </label>
        <label class="field">
          <span>Model</span>
          <select name="aiModel" [(ngModel)]="aiModel">
            @for (model of availableAiModels; track model.value) {
              <option [value]="model.value">{{ model.label }}</option>
            }
          </select>
        </label>
        <label class="field">
          <span>OpenAI API key</span>
          <input type="password" name="aiKey" [(ngModel)]="aiKeyDraft" autocomplete="new-password" placeholder="Leave blank to keep the saved key" />
        </label>
        <p class="hint">{{ prefs().ai.api_key_configured ? 'An API key is configured.' : 'No user API key is configured; the server key will be used if available.' }}</p>
        <div class="actions">
          <button type="button" class="link-button" (click)="saveAiSettings()" [disabled]="aiSaving">{{ aiSaving ? 'Saving…' : 'Save AI settings' }}</button>
          @if (prefs().ai.api_key_configured) {
            <button type="button" class="link-button danger-link" (click)="clearAiKey()" [disabled]="aiSaving">Remove saved key</button>
          }
        </div>
        @if (aiMessage) { <p class="hint" role="status">{{ aiMessage }}</p> }
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
        <label class="toggle">
          <input type="checkbox" name="notifyTaxNews" [ngModel]="prefs().notifyTaxNews" (ngModelChange)="update({ notifyTaxNews: $event })" />
          <span><strong>Important tax news</strong><small>Show important ATO and Treasury updates in the notification bell.</small></span>
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
    .danger-link { color: var(--tv-color-danger); }
  `],
})
export class SettingsComponent {
  readonly auth = inject(AuthService);
  private readonly preferences = inject(UserPreferencesService);

  private readonly http = inject(HttpClient);

  readonly prefs = this.preferences.preferences;
  readonly sending = signal(false);
  readonly testMessage = signal('');
  aiEnabled = true;
  aiProvider: 'openai' | 'anthropic' | 'gemini' = 'openai';
  aiModel = 'gpt-4o-mini';
  aiKeyDraft = '';
  aiSaving = false;
  aiMessage = '';
  readonly aiModels = [
    { value: 'gpt-4o-mini', label: 'GPT-4o mini' },
    { value: 'gpt-4.1-mini', label: 'GPT-4.1 mini' },
  ];

  constructor() {
    this.aiEnabled = this.prefs().ai.enabled;
    this.aiProvider = this.prefs().ai.provider;
    this.aiModel = this.prefs().ai.model;
  }

  get availableAiModels(): { value: string; label: string }[] {
    if (this.aiProvider === 'anthropic') {
      return [
        { value: 'claude-3-5-haiku-latest', label: 'Claude 3.5 Haiku' },
        { value: 'claude-3-5-sonnet-latest', label: 'Claude 3.5 Sonnet' },
      ];
    }
    if (this.aiProvider === 'gemini') {
      return [
        { value: 'gemini-2.0-flash', label: 'Gemini 2.0 Flash' },
        { value: 'gemini-2.5-flash', label: 'Gemini 2.5 Flash' },
      ];
    }
    return this.aiModels;
  }

  selectAiProvider(provider: 'openai' | 'anthropic' | 'gemini'): void {
    this.aiProvider = provider;
    this.aiModel = this.availableAiModels[0]?.value ?? '';
  }

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

  saveAiSettings(): void {
    this.aiSaving = true;
    this.aiMessage = '';
    const settings: { enabled: boolean; provider: 'openai' | 'anthropic' | 'gemini'; model: string; api_key?: string } = {
      enabled: this.aiEnabled,
      provider: this.aiProvider,
      model: this.aiModel,
    };
    if (this.aiKeyDraft.trim()) settings.api_key = this.aiKeyDraft.trim();
    this.preferences.updateAi(settings).subscribe({
      next: (ai) => {
        this.aiEnabled = ai.enabled;
        this.aiProvider = ai.provider;
        this.aiModel = ai.model;
        this.aiKeyDraft = '';
        this.aiSaving = false;
        this.aiMessage = 'AI settings saved.';
      },
      error: () => {
        this.aiSaving = false;
        this.aiMessage = 'Unable to save AI settings.';
      },
    });
  }

  clearAiKey(): void {
    this.aiSaving = true;
    this.aiMessage = '';
    this.preferences.updateAi({ enabled: this.prefs().ai.enabled, provider: this.prefs().ai.provider, model: this.prefs().ai.model, clear_api_key: true }).subscribe({
      next: (ai) => {
        this.aiEnabled = ai.enabled;
        this.aiProvider = ai.provider;
        this.aiModel = ai.model;
        this.aiSaving = false;
        this.aiMessage = 'Saved API key removed.';
      },
      error: () => {
        this.aiSaving = false;
        this.aiMessage = 'Unable to remove the saved key.';
      },
    });
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
