import { HttpClient } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';

import { AuthService } from '../../shared/services/auth/auth.service';
import { buildReminderIcs } from '../../shared/utils/calendar';
import { UserPreferencesService } from '../../shared/services/user-preferences.service';

@Component({
  selector: 'app-settings',
  imports: [FormsModule, RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="settings-page">
      <header>
        <div class="eyebrow">Account</div>
        <h1>Settings</h1>
        <p>Your profile and how TaxVault works for you. Preferences are saved to your account.</p>
      </header>

      <section class="card" aria-labelledby="profile-heading">
        <h2 id="profile-heading">Profile</h2>
        <dl>
          <div><dt>Name</dt><dd>{{ auth.currentUser?.display_name || '—' }}</dd></div>
          <div><dt>Email</dt><dd>{{ auth.currentUser?.email || '—' }}</dd></div>
        </dl>
        <p class="hint">Your name and email come from your sign-in account and are managed there.</p>
      </section>

      <section class="card" aria-labelledby="general-heading">
        <h2 id="general-heading">General</h2>
        <label class="field">
          <span>Financial year when you sign in</span>
          <select name="defaultYear" [ngModel]="prefs().defaultFinancialYear" (ngModelChange)="update({ defaultFinancialYear: $event })">
            <option value="current">Current financial year</option>
            <option value="all">All financial years</option>
          </select>
        </label>
        <p class="hint">You can still change the financial year at any time from the header.</p>
      </section>

      <section class="card" aria-labelledby="notify-heading">
        <h2 id="notify-heading">Notifications</h2>
        <p class="hint">Choose what appears under the bell in the header.</p>
        <label class="toggle">
          <input type="checkbox" name="notifyDocuments" [ngModel]="prefs().notifyDocuments" (ngModelChange)="update({ notifyDocuments: $event })" />
          <span><strong>Documents</strong><small>Documents that need review, failed processing or need a financial year.</small></span>
        </label>
        <label class="toggle">
          <input type="checkbox" name="notifyUncategorised" [ngModel]="prefs().notifyUncategorised" (ngModelChange)="update({ notifyUncategorised: $event })" />
          <span><strong>Uncategorised transactions</strong><small>Transactions without a tax return item.</small></span>
        </label>
      </section>

      @if (!auth.isAccountant) {
        <section class="card" aria-labelledby="email-heading">
          <h2 id="email-heading">Email reminders</h2>
          <p class="hint">Optional emails to your sign-in address. Each email has an unsubscribe link.</p>
          <label class="toggle">
            <input type="checkbox" name="emailReviewDigest" [ngModel]="prefs().emailReviewDigest" (ngModelChange)="update({ emailReviewDigest: $event })" />
            <span><strong>Weekly review digest</strong><small>At most one email a week when documents need your review.</small></span>
          </label>
          <label class="toggle">
            <input type="checkbox" name="emailDeadlineReminders" [ngModel]="prefs().emailDeadlineReminders" (ngModelChange)="update({ emailDeadlineReminders: $event })" />
            <span><strong>Lodgement deadline reminders</strong><small>30 days, 7 days and 1 day before the deadline.</small></span>
          </label>
          <label class="field">
            <span>Your lodgement date (optional)</span>
            <input type="date" name="lodgementDeadline" [ngModel]="prefs().lodgementDeadline" (ngModelChange)="update({ lodgementDeadline: $event || null })" />
          </label>
          <p class="hint">Without a date we use 31 October after each financial year ends. Please confirm your actual date with the ATO or a tax agent.</p>
          <div class="actions">
            <button type="button" class="link-button" (click)="sendTest()" [disabled]="sending()">{{ sending() ? 'Sending…' : 'Send test email' }}</button>
            <button type="button" class="link-button" (click)="downloadIcs()">Add to calendar (.ics)</button>
          </div>
          @if (testMessage()) {
            <p class="hint" role="status">{{ testMessage() }}</p>
          }
        </section>

        <section class="card" aria-labelledby="access-heading">
          <h2 id="access-heading">Access</h2>
          <p class="hint">Invite your accountant to view your records, or remove their access.</p>
          <a routerLink="/accountants" class="link-button">Manage accountants</a>
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
}
