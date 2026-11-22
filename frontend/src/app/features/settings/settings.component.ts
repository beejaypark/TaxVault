import { HttpClient } from '@angular/common/http';
import { ChangeDetectionStrategy, ChangeDetectorRef, Component, inject, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';

import { AuthService } from '../../shared/services/auth/auth.service';
import { buildReminderIcs } from '../../shared/utils/calendar';
import { UserPreferencesService } from '../../shared/services/user-preferences.service';
import { LocaleCode, activeLocale, angularLocale } from '../../i18n/locale';
import { TranslatePipe } from '../../shared/i18n/translate.pipe';

interface StorageProviderDto {
  key: string;
  label: string;
  mode: 'managed' | 'external';
  supports_import: boolean;
  supports_sync: boolean;
  configured?: boolean;
}

interface StorageConnectionDto {
  id: string;
  provider: string;
  external_account_id: string;
  display_name?: string | null;
  status: string;
  connected_at: string;
  last_synced_at?: string | null;
  last_error?: string | null;
}

interface BillingStatusDto {
  status: string | null;
  trial_ends_at: string | null;
  subscription_ends_at: string | null;
  active: boolean;
  price: { amount: number | null; currency: string; interval: string | null; interval_count: number } | null;
}

@Component({
  selector: 'app-settings',
  imports: [DatePipe, FormsModule, RouterLink, TranslatePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="settings-page tv-workspace-page">
      <header class="page-header">
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
            <option value="all">{{ 'Settings.AllFinancialYears' | translate }}</option>
          </select>
        </label>
        <p class="hint">{{ 'Settings.GeneralHint' | translate }}</p>
      </section>

      @if (!auth.isAccountant && !auth.isPlatformAdmin) {
      <section class="card" aria-labelledby="storage-heading">
        <h2 id="storage-heading">{{ 'Settings.DocumentStorage' | translate }}</h2>
        <p class="hint">{{ 'Settings.DocumentStorageHint' | translate }}</p>
        @if (storageProviders.length === 0) {
          <p class="hint">{{ 'Settings.StorageUnavailable' | translate }}</p>
        } @else {
          <label class="field storage-provider-select">
            <span>{{ 'Settings.StorageProvider' | translate }}</span>
            <select name="storageProvider" [(ngModel)]="selectedStorageProviderKey" (ngModelChange)="selectStorageProvider($event)">
              @for (provider of storageProviders; track provider.key) {
                <option [value]="provider.key">{{ provider.label }}</option>
              }
            </select>
          </label>
          @if (selectedStorageProvider; as provider) {
            <div class="storage-provider-row">
              <div>
                <strong>{{ provider.label }}</strong>
                <small>{{ provider.mode === 'managed' ? ('Settings.ManagedByTaxVault' | translate) : ('Settings.ExternalProvider' | translate) }}</small>
              </div>
              @if (provider.key === 'onedrive') {
                <button type="button" class="link-button" (click)="connectOneDrive()" [disabled]="provider.configured === false">
                  {{ 'Settings.ConnectOneDrive' | translate }}
                </button>
                @if (provider.configured === false) {
                  <small class="status-pill">{{ 'Settings.SaveOneDriveFirst' | translate }}</small>
                }
              } @else {
                <span class="status-pill">{{ provider.key === 'taxvault' ? ('Settings.DefaultStorage' | translate) : ('Settings.ComingSoon' | translate) }}</span>
              }
            </div>
          }
        }
        @if (selectedStorageProviderKey === 'onedrive') {
        <div class="storage-settings">
          <strong>{{ 'Settings.OneDriveAppSettings' | translate }}</strong>
          <p class="hint">{{ 'Settings.OneDriveAppSettingsHint' | translate }}</p>
          <label class="field">
            <span>{{ 'Settings.ApplicationClientId' | translate }}</span>
            <input type="text" name="onedriveClientId" [(ngModel)]="onedriveClientId" autocomplete="off" />
          </label>
          <label class="field">
            <span>{{ 'Settings.ClientSecret' | translate }}</span>
            <input type="password" name="onedriveClientSecret" [(ngModel)]="onedriveClientSecret" autocomplete="new-password" [placeholder]="'Settings.KeepSavedSecret' | translate" />
          </label>
          <label class="field">
            <span>{{ 'Settings.DirectoryTenantId' | translate }}</span>
            <input type="text" name="onedriveTenant" [(ngModel)]="onedriveTenant" autocomplete="off" />
          </label>
          <p class="hint">{{ 'Settings.RedirectUriHint' | translate }} {{ onedriveRedirectUri || ('Settings.Loading' | translate) }}</p>
          <div class="actions">
            <button type="button" class="link-button" (click)="saveOneDriveSettings()" [disabled]="storageSaving || !onedriveClientId.trim()">{{ storageSaving ? ('Settings.Saving' | translate) : ('Settings.SaveOneDriveSettings' | translate) }}</button>
          </div>
        </div>
        } @else if (selectedStorageProviderKey === 'taxvault') {
          <p class="hint storage-provider-description">{{ 'Settings.TaxVaultStorageHint' | translate }}</p>
        } @else if (selectedStorageProviderKey) {
          <p class="hint storage-provider-description">{{ 'Settings.ProviderComingSoonHint' | translate }}</p>
        }
        @if (storageConnections.length === 0) {
          <p class="hint">{{ 'Settings.NoStorageConnections' | translate }}</p>
        } @else {
          @for (connection of storageConnections; track connection.id) {
            <div class="storage-provider-row">
              <div>
                <strong>{{ connection.display_name || connection.provider }}</strong>
                <small>{{ connection.external_account_id }} · {{ connection.status }}</small>
              </div>
              <button type="button" class="link-button danger-link" (click)="disconnectStorage(connection.id)">{{ 'Settings.Disconnect' | translate }}</button>
            </div>
          }
        }
        @if (storageMessage) { <p class="hint" role="status">{{ storageMessage | translate }}</p> }
      </section>
      } @else {
        <section class="card" aria-labelledby="storage-heading">
          <h2 id="storage-heading">{{ 'Settings.DocumentStorage' | translate }}</h2>
          <p class="hint">{{ 'Settings.AccountantStorageHint' | translate }}</p>
        </section>
      }

      <section class="card" aria-labelledby="ai-heading">
        <h2 id="ai-heading">{{ 'Settings.AiFeatures' | translate }}</h2>
        <p class="hint">{{ 'Settings.AiFeaturesHint' | translate }}</p>
        <label class="toggle">
          <input type="checkbox" name="aiEnabled" [ngModel]="prefs().ai.enabled" (ngModelChange)="aiEnabled = $event" />
          <span><strong>{{ 'Settings.EnableAiSummaries' | translate }}</strong><small>{{ 'Settings.DisableAiSummariesHint' | translate }}</small></span>
        </label>
        <label class="field">
          <span>{{ 'Settings.Provider' | translate }}</span>
          <select name="aiProvider" [(ngModel)]="aiProvider" (ngModelChange)="selectAiProvider($event)">
            <option value="openai">ChatGPT / OpenAI</option>
            <option value="anthropic">Claude</option>
            <option value="gemini">Gemini</option>
          </select>
        </label>
        <label class="field">
          <span>{{ 'Settings.Model' | translate }}</span>
          <select name="aiModel" [(ngModel)]="aiModel">
            @for (model of availableAiModels; track model.value) {
              <option [value]="model.value">{{ model.label }}</option>
            }
          </select>
        </label>
        <label class="field">
          <span>{{ 'Settings.OpenAiApiKey' | translate }}</span>
          <input type="password" name="aiKey" [(ngModel)]="aiKeyDraft" autocomplete="new-password" [placeholder]="'Settings.KeepSavedKey' | translate" />
        </label>
        <p class="hint">{{ prefs().ai.api_key_configured ? ('Settings.ApiKeyConfigured' | translate) : ('Settings.NoUserApiKey' | translate) }}</p>
        <div class="actions">
          <button type="button" class="link-button" (click)="saveAiSettings()" [disabled]="aiSaving">{{ aiSaving ? ('Settings.Saving' | translate) : ('Settings.SaveAiSettings' | translate) }}</button>
          @if (prefs().ai.api_key_configured) {
            <button type="button" class="link-button danger-link" (click)="clearAiKey()" [disabled]="aiSaving">{{ 'Settings.RemoveSavedKey' | translate }}</button>
          }
        </div>
        @if (aiMessage) { <p class="hint" role="status">{{ aiMessage | translate }}</p> }
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
          <span><strong>{{ 'Settings.ImportantTaxNews' | translate }}</strong><small>{{ 'Settings.ImportantTaxNewsHint' | translate }}</small></span>
        </label>
      </section>

      @if (!auth.isAccountant) {
        <section class="card" aria-labelledby="billing-heading">
          <h2 id="billing-heading">TaxVault subscription</h2>
          <p class="hint">Choose a plan that works for you. Cancel anytime from the Stripe customer portal.</p>
          <div class="subscription-summary">
            <div>
              <span class="subscription-label">Current plan</span>
              <strong>{{ billingStatus?.active ? (billingStatus?.status || 'Active') : 'Not started' }}</strong>
              @if (billingStatus?.trial_ends_at) {
                <small>Free trial ends {{ billingStatus?.trial_ends_at | date:'mediumDate' }}</small>
              }
            </div>
            @if (billingStatus?.active) {
              <div class="billing-actions">
                @if (!billingCancellationRequested) {
                  <button type="button" class="link-button danger-link" (click)="cancelSubscription()" [disabled]="billingLoading">{{ billingLoading ? 'Updating…' : 'Cancel subscription' }}</button>
                }
                <button type="button" class="link-button" (click)="openBillingPortal()" [disabled]="billingLoading">Manage payment details</button>
              </div>
            } @else {
              <div class="billing-actions">
                <button type="button" class="link-button" (click)="startCheckout(true)" [disabled]="billingLoading">Start 30-day free trial</button>
                <button type="button" class="link-button secondary-button" (click)="startCheckout(false)" [disabled]="billingLoading">Subscribe now</button>
              </div>
            }
          </div>
          @if (billingMessage) { <p class="hint" role="status">{{ billingMessage }}</p> }
        </section>
      }

      @if (!auth.isAccountant) {
        <section class="card" aria-labelledby="email-heading">
          <h2 id="email-heading">{{ 'Settings.EmailReminders' | translate }}</h2>
          <p class="hint">{{ 'Settings.EmailRemindersHint' | translate }}</p>
          <div class="subscription-summary">
            <div>
              <span class="subscription-label">{{ 'Settings.EmailSubscription' | translate }}</span>
              <strong>{{ emailSubscriptionActive ? ('Settings.Subscribed' | translate) : ('Settings.Unsubscribed' | translate) }}</strong>
              <small>{{ emailSubscriptionActive ? ('Settings.SubscribedHint' | translate) : ('Settings.UnsubscribedHint' | translate) }}</small>
            </div>
            @if (emailSubscriptionActive) {
              <button type="button" class="link-button danger-link" (click)="setEmailSubscription(false)">{{ 'Settings.Unsubscribe' | translate }}</button>
            } @else {
              <button type="button" class="link-button" (click)="setEmailSubscription(true)">{{ 'Settings.Subscribe' | translate }}</button>
            }
          </div>
          @if (subscriptionMessage) { <p class="hint" role="status">{{ subscriptionMessage | translate }}</p> }
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
            <p class="hint" role="status">{{ testMessage() | translate }}</p>
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
    .settings-page { display: grid; gap: 12px; width: min(100%, 1100px); margin: 0 auto; padding: 40px 32px 56px; box-sizing: border-box; }
    @media (max-width: 700px) { .settings-page { gap: 16px; padding: 24px 16px 40px; } }
    .eyebrow { color: var(--tv-color-primary); font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
    h1 { margin: 8px 0 8px; font-size: 34px; font-weight: 500; letter-spacing: -0.02em; }
    header p, .hint { margin: 0; color: var(--tv-color-text-secondary); font-size: 14px; line-height: 1.55; }
    .card { display: grid; align-items: start; gap: 16px; padding: 24px; border: 1px solid var(--tv-color-border); border-radius: var(--tv-radius-lg); background: var(--tv-color-surface); }
    @media (min-width: 1024px) {
      .card { grid-template-columns: minmax(150px, 1fr) minmax(0, 3fr); column-gap: 24px; }
      .card > h2 { grid-column: 1; grid-row: 1; }
      .card > :not(h2) { grid-column: 2; }
    }
    h2 { margin: 0; font-size: 18px; font-weight: 600; letter-spacing: -0.01em; }
    dl { display: grid; gap: 10px; margin: 0; }
    dl div { display: grid; grid-template-columns: 100px 1fr; gap: 12px; }
    dt { color: var(--tv-color-text-muted); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    dd { margin: 0; font-size: 14px; font-weight: 600; overflow-wrap: anywhere; }
    .field { display: grid; gap: 8px; max-width: 420px; }
    .field span { color: var(--tv-color-text-secondary); font-size: 12px; font-weight: 600; }
    select { min-height: 44px; padding: 0 12px; border: 1px solid var(--tv-color-border); border-radius: 8px; background: var(--tv-color-surface); color: var(--tv-color-text); font-size: 14px; }
    input[type=text], input[type=password] { min-height: 44px; padding: 0 12px; border: 1px solid var(--tv-color-border); border-radius: 8px; background: var(--tv-color-surface); color: var(--tv-color-text); font-size: 14px; }
    select:focus-visible, input:focus-visible, button:focus-visible, a:focus-visible { outline: 3px solid color-mix(in srgb, var(--tv-color-primary) 24%, transparent); outline-offset: 2px; }
    .toggle { display: flex; align-items: flex-start; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--tv-color-border); cursor: pointer; }
    .toggle:hover { color: var(--tv-color-primary); }
    .toggle input { width: 18px; height: 18px; margin-top: 2px; accent-color: var(--tv-color-primary); }
    .toggle span { display: grid; gap: 2px; }
    .toggle small { color: var(--tv-color-text-secondary); font-size: 12px; }
    input[type=date] { min-height: 42px; padding: 0 12px; border: 1px solid var(--tv-color-border); border-radius: var(--tv-radius-sm); background: var(--tv-color-surface); font-size: 14px; }
    .actions { display: flex; flex-wrap: wrap; gap: 12px; }
    button.link-button { background: var(--tv-color-surface); cursor: pointer; }
    .link-button { justify-self: start; min-height: 40px; padding: 0 16px; border: 1px solid var(--tv-color-border); border-radius: 8px; color: var(--tv-color-primary); font-size: 13px; font-weight: 600; transition: background-color 120ms ease, border-color 120ms ease, color 120ms ease; }
    .link-button:hover:not(:disabled) { border-color: var(--tv-color-primary); background: var(--tv-color-primary-soft); }
    .link-button:disabled { cursor: not-allowed; opacity: .55; }
    .danger-link { color: var(--tv-color-danger); }
    .storage-provider-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 16px; border: 1px solid color-mix(in srgb, var(--tv-color-primary) 20%, var(--tv-color-border)); border-radius: 10px; background: var(--tv-color-primary-soft); }
    .storage-provider-row > div { display: grid; gap: 3px; }
    .storage-provider-row small { color: var(--tv-color-text-secondary); font-size: 12px; }
    .status-pill { color: var(--tv-color-text-secondary); font-size: 12px; }
    .storage-settings { display: grid; gap: 16px; padding: 20px; border: 1px solid var(--tv-color-border); border-radius: 10px; background: color-mix(in srgb, var(--tv-color-primary-soft) 35%, var(--tv-color-surface)); }
    .storage-provider-select { max-width: 420px; }
    .storage-provider-description { margin: 0; padding: 12px; border: 1px solid var(--tv-color-border); border-radius: var(--tv-radius-sm); }
    .subscription-summary { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 16px; border: 1px solid var(--tv-color-border); border-radius: 10px; background: var(--tv-color-background); }
    .subscription-summary > div { display: grid; gap: 3px; }
    .billing-actions { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; }
    .secondary-button { background: transparent; }
    .pricing-card { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin: 12px 0; padding: 16px; border: 1px solid var(--tv-color-border); border-radius: 10px; background: var(--tv-color-background); }
    .pricing-card > div { display: grid; gap: 3px; }
    .pricing-card strong { font-size: 22px; }
    .price-note { color: var(--tv-color-primary); font-size: 13px; font-weight: 600; }
    .subscription-summary strong { font-size: 15px; }
    .subscription-summary small { color: var(--tv-color-text-secondary); font-size: 12px; }
    .subscription-label { color: var(--tv-color-text-secondary); font-size: 12px; font-weight: 600; }
    @media (max-width: 560px) { .subscription-summary { align-items: flex-start; flex-direction: column; } }
  `],
})
export class SettingsComponent {
  readonly auth = inject(AuthService);
  private readonly preferences = inject(UserPreferencesService);

  private readonly http = inject(HttpClient);
  private readonly changeDetector = inject(ChangeDetectorRef);
  private readonly route = inject(ActivatedRoute);

  readonly prefs = this.preferences.preferences;
  readonly sending = signal(false);
  readonly testMessage = signal('');
  aiEnabled = true;
  aiProvider: 'openai' | 'anthropic' | 'gemini' = 'openai';
  aiModel = 'gpt-4o-mini';
  aiKeyDraft = '';
  aiSaving = false;
  aiMessage = '';
  subscriptionMessage = '';
  storageProviders: StorageProviderDto[] = [];
  selectedStorageProviderKey = '';
  storageConnections: StorageConnectionDto[] = [];
  storageMessage = '';
  onedriveClientId = '';
  onedriveClientSecret = '';
  onedriveTenant = 'common';
  onedriveRedirectUri = '';
  storageSaving = false;
  billingStatus: BillingStatusDto | null = null;
  billingLoading = false;
  billingMessage = '';
  billingCheckoutStarted = false;
  billingCancellationRequested = false;
  readonly aiModels = [
    { value: 'gpt-4o-mini', label: 'GPT-4o mini' },
    { value: 'gpt-4.1-mini', label: 'GPT-4.1 mini' },
  ];

  constructor() {
    this.aiEnabled = this.prefs().ai.enabled;
    this.aiProvider = this.prefs().ai.provider;
    this.aiModel = this.prefs().ai.model;
    // Auth is initialized asynchronously. Do not assume an owner before the
    // user role has been loaded, otherwise accountant viewers make a forbidden
    // storage request and see a misleading "unavailable" message.
    this.auth.initialize().subscribe((user) => {
      if (user?.role !== 'accountant' && user?.role !== 'platform_admin') {
        this.loadBillingStatus();
      }
      this.loadStorageProviders();
      if (user?.role !== 'accountant') {
        this.loadOneDriveSettings();
        this.loadStorageConnections();
      }
    });
  }

  loadBillingStatus(): void {
    this.http.get<{ data: BillingStatusDto }>('/api/billing/status').subscribe({
      next: (response) => {
        this.billingStatus = response.data;
        const plan = this.route.snapshot.queryParamMap.get('plan');
        const checkoutRequested = this.route.snapshot.queryParamMap.get('billing') === 'checkout'
          && (plan === 'monthly' || plan === 'trial' || plan === 'yearly');
        if (checkoutRequested && !this.billingCheckoutStarted && !response.data.active) {
          this.billingCheckoutStarted = true;
          this.startCheckout(true, plan === 'yearly' ? 'yearly' : 'monthly');
        }
        this.changeDetector.markForCheck();
      },
      error: () => { this.billingMessage = 'Unable to load subscription status.'; this.changeDetector.markForCheck(); },
    });
  }

  startCheckout(trial: boolean, plan: 'monthly' | 'yearly' = 'monthly'): void {
    this.billingLoading = true;
    this.billingMessage = '';
    this.http.post<{ data: { url: string } }>('/api/billing/checkout', { trial, plan }).subscribe({
      next: (response) => window.location.assign(response.data.url),
      error: (error) => {
        this.billingLoading = false;
        this.billingMessage = error?.error?.message || 'Unable to start checkout.';
        this.changeDetector.markForCheck();
      },
    });
  }

  openBillingPortal(): void {
    this.billingLoading = true;
    this.billingMessage = '';
    this.http.post<{ data: { url: string } }>('/api/billing/portal', {}).subscribe({
      next: (response) => window.location.assign(response.data.url),
      error: (error) => {
        this.billingLoading = false;
        this.billingMessage = error?.error?.message || 'Unable to open subscription management.';
        this.changeDetector.markForCheck();
      },
    });
  }

  cancelSubscription(): void {
    if (! window.confirm('Cancel your TaxVault subscription at the end of the current billing period?')) return;

    this.billingLoading = true;
    this.billingMessage = '';
    this.http.post<{ data: { subscription_ends_at: string | null } }>('/api/billing/cancel', {}).subscribe({
      next: (response) => {
        if (this.billingStatus) {
          this.billingStatus = {
            ...this.billingStatus,
            subscription_ends_at: response.data.subscription_ends_at,
          };
        }
        this.billingLoading = false;
        this.billingCancellationRequested = true;
        this.billingMessage = response.data.subscription_ends_at
          ? `Subscription cancelled. Access remains available until ${new Date(response.data.subscription_ends_at).toLocaleDateString('en-AU')}.`
          : 'Subscription cancelled.';
        this.changeDetector.markForCheck();
      },
      error: (error) => {
        this.billingLoading = false;
        this.billingMessage = error?.error?.message || 'Unable to cancel subscription.';
        this.changeDetector.markForCheck();
      },
    });
  }

  loadOneDriveSettings(): void {
    this.http.get<{ data: { client_id: string; tenant: string; redirect_uri: string } }>('/api/document-storage/settings/onedrive').subscribe({
      next: (response) => {
        this.onedriveClientId = response.data.client_id;
        this.onedriveTenant = response.data.tenant;
        this.onedriveRedirectUri = response.data.redirect_uri;
        this.changeDetector.markForCheck();
      },
      error: () => { this.storageMessage = 'Settings.LoadOneDriveFailed'; this.changeDetector.markForCheck(); },
    });
  }

  saveOneDriveSettings(): void {
    this.storageSaving = true;
    this.storageMessage = '';
    this.http.put<{ data: { redirect_uri: string } }>('/api/document-storage/settings/onedrive', {
      client_id: this.onedriveClientId.trim(),
      client_secret: this.onedriveClientSecret.trim() || null,
      tenant: this.onedriveTenant.trim() || 'common',
    }).subscribe({
      next: (response) => {
        this.onedriveClientSecret = '';
        this.onedriveRedirectUri = response.data.redirect_uri;
        this.storageSaving = false;
        this.storageMessage = 'Settings.OneDriveSaved';
        this.loadStorageProviders();
        this.changeDetector.markForCheck();
      },
      error: () => {
        this.storageSaving = false;
        this.storageMessage = 'Settings.SaveOneDriveFailed';
        this.changeDetector.markForCheck();
      },
    });
  }

  loadStorageProviders(): void {
    this.http.get<{ data: StorageProviderDto[]; default?: string }>('/api/document-storage/providers').subscribe({
      next: (response) => {
        this.storageProviders = response.data;
        this.selectedStorageProviderKey = this.storageProviders.some((provider) => provider.key === this.selectedStorageProviderKey)
          ? this.selectedStorageProviderKey
          : (response.default && this.storageProviders.some((provider) => provider.key === response.default)
            ? response.default
            : this.storageProviders[0]?.key ?? '');
        this.changeDetector.markForCheck();
      },
      error: () => {
        this.storageMessage = 'Settings.StorageOptionsFailed';
        this.changeDetector.markForCheck();
      },
    });
  }

  get selectedStorageProvider(): StorageProviderDto | null {
    return this.storageProviders.find((provider) => provider.key === this.selectedStorageProviderKey) ?? null;
  }

  selectStorageProvider(providerKey: string): void {
    this.selectedStorageProviderKey = providerKey;
    this.storageMessage = '';
    this.changeDetector.markForCheck();
  }

  loadStorageConnections(): void {
    this.http.get<{ data: StorageConnectionDto[] }>('/api/document-storage/connections').subscribe({
      next: (response) => {
        this.storageConnections = response.data;
        this.changeDetector.markForCheck();
      },
      error: () => {
        this.storageMessage = 'Settings.LoadConnectionsFailed';
        this.changeDetector.markForCheck();
      },
    });
  }

  disconnectStorage(connectionId: string): void {
    this.http.delete(`/api/document-storage/connections/${connectionId}`).subscribe({
      next: () => {
        this.storageConnections = this.storageConnections.map((connection) => connection.id === connectionId
          ? { ...connection, status: 'disconnected' }
          : connection);
        this.storageMessage = 'Settings.StorageDisconnected';
      },
      error: () => { this.storageMessage = 'Settings.DisconnectStorageFailed'; },
    });
  }

  connectOneDrive(): void {
    window.location.assign('/api/document-storage/onedrive/redirect?returnUrl=/settings');
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

  get emailSubscriptionActive(): boolean {
    return this.prefs().emailReviewDigest || this.prefs().emailDeadlineReminders;
  }

  setEmailSubscription(subscribed: boolean): void {
    this.subscriptionMessage = '';
    this.preferences.updateAndWait({
      emailReviewDigest: subscribed,
      emailDeadlineReminders: subscribed,
    }).subscribe({
      next: () => {
        this.subscriptionMessage = subscribed
          ? 'Settings.SubscriptionEnabled'
          : 'Settings.SubscriptionDisabled';
        this.changeDetector.markForCheck();
      },
      error: () => {
        this.subscriptionMessage = 'Settings.SubscriptionUpdateFailed';
        this.changeDetector.markForCheck();
      },
    });
  }

  sendTest(): void {
    this.sending.set(true);
    this.testMessage.set('');
    this.http.post('/api/auth/preferences/test-email', {}).subscribe({
      next: () => {
        this.sending.set(false);
        this.testMessage.set('Settings.TestEmailSent');
      },
      error: () => {
        this.sending.set(false);
        this.testMessage.set('Settings.TestEmailFailed');
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
        this.aiMessage = 'Settings.AiSettingsSaved';
      },
      error: () => {
        this.aiSaving = false;
        this.aiMessage = 'Settings.AiSettingsFailed';
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
        this.aiMessage = 'Settings.ApiKeyRemoved';
      },
      error: () => {
        this.aiSaving = false;
        this.aiMessage = 'Settings.ApiKeyRemoveFailed';
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
      activeLocale.set(locale);
    });
  }
}
