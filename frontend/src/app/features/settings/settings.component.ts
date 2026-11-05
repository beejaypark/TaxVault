import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';

import { AuthService } from '../../shared/services/auth/auth.service';
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
        <p>Your profile and how TaxVault works for you. Preferences are saved in this browser.</p>
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
    .link-button { justify-self: start; padding: 9px 16px; border: 1px solid var(--tv-color-border); border-radius: var(--tv-radius-sm); color: var(--tv-color-primary); font-size: 13px; font-weight: 600; }
  `],
})
export class SettingsComponent {
  readonly auth = inject(AuthService);
  private readonly preferences = inject(UserPreferencesService);

  readonly prefs = this.preferences.preferences;

  update(changes: Parameters<UserPreferencesService['update']>[0]): void {
    this.preferences.update(changes);
  }
}
