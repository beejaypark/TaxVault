import { ChangeDetectionStrategy, Component, Input, OnInit, inject } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';

import { BrandLogoComponent } from '../../../../shared/components/brand-logo/brand-logo.component';
import { AuthService } from '../../../../shared/services/auth/auth.service';
import { TranslatePipe } from '../../../../shared/i18n/translate.pipe';
import { TranslationService } from '../../../../shared/i18n/translation.service';

@Component({
  selector: 'app-login',
  imports: [FormsModule, BrandLogoComponent, TranslatePipe],
  template: `
    <main class="login-page" [class.embedded]="embedded">
      @if (!embedded) {
      <section class="welcome-panel" aria-labelledby="welcome-title">
        <tv-brand-logo [size]="40" />
        <p class="eyebrow">Tax records, without the scramble</p>
        <h1 id="welcome-title">Your tax documents, ready when you are.</h1>
        <p class="welcome-copy">TaxVault brings receipts, statements and tax evidence into one calm workspace, so you can find what matters and hand over a clearer record at tax time.</p>
        <div class="welcome-points">
          <div><span>01</span><p><strong>Bring documents in</strong><small>Upload in bulk or import folders from connected storage.</small></p></div>
          <div><span>02</span><p><strong>Find the details</strong><small>OCR-assisted capture surfaces dates and amounts for review.</small></p></div>
          <div><span>03</span><p><strong>Keep the evidence together</strong><small>Source files, corrections and review history stay connected.</small></p></div>
        </div>
        <p class="welcome-note">Private by default. Clear by design.</p>
      </section>
      }
      <section class="login-card" aria-labelledby="login-title">
        @if (trialMode) {
          <div class="trial-banner"><strong>Start with a 30-day free trial</strong><span>No payment details are required to get started. Decide whether TaxVault is right for you after your free month.</span></div>
        }
        <h1 id="login-title">{{ 'Auth.WelcomeBack' | translate }}</h1>
        <p class="intro">{{ 'Auth.SignInIntro' | translate }}</p>

        <a class="oidc-button" [href]="keycloakLoginUrl" (click)="startAuth($event, 'keycloak')">{{ 'Auth.ContinueWithKeycloak' | translate }}</a>
        <a class="google-button" [href]="googleLoginUrl" (click)="startAuth($event, 'google')">Continue with Google</a>
        <a class="register-link" [class.trial-register]="trialMode" [href]="registrationUrl" (click)="startAuth($event, 'register')">{{ trialMode ? 'Start my 30-day free trial' : 'Create a new account' }}</a>
        @if (developmentLoginEnabled) {
          <div class="separator" aria-hidden="true"><span>{{ 'Auth.DevelopmentLogin' | translate }}</span></div>

          <div class="notice" role="status">
            {{ 'Auth.DevelopmentNotice' | translate }}
          </div>
        }

        @if (errorMessage) {
          <p class="error-message" role="alert">{{ errorMessage }}</p>
        }

        @if (developmentLoginEnabled) {
        <form (ngSubmit)="submit()" #loginForm="ngForm">
          <label for="login">{{ 'Auth.Login' | translate }}</label>
          <input
            id="login"
            name="login"
            type="text"
            autocomplete="username"
            [(ngModel)]="login"
            required
            maxlength="100"
          />

          <label for="password">{{ 'Auth.Password' | translate }}</label>
          <input
            id="password"
            name="password"
            type="password"
            autocomplete="current-password"
            [(ngModel)]="password"
            required
            maxlength="1024"
          />

          <button type="submit" [disabled]="loginForm.invalid || loading">
            {{ loading ? ('Auth.SigningIn' | translate) : ('Auth.SignIn' | translate) }}
          </button>
        </form>
        }
      </section>
    </main>
  `,
  styles: [`
    :host { display: block; width: 100%; }
    .login-page { min-height: 100vh; display: grid; grid-template-columns: minmax(0, 1fr) minmax(360px, 420px); align-items: center; gap: 72px; width: min(100%, 1180px); margin: 0 auto; padding: 48px 32px; box-sizing: border-box; background: var(--tv-color-background, #f5f7fb); }
    .welcome-panel { max-width: 600px; padding: 24px 0; }
    .eyebrow { margin: 52px 0 16px; color: #1f7ac4; font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
    .welcome-panel h1 { max-width: 580px; margin: 0; color: #12345d; font-size: clamp(42px, 5vw, 64px); font-weight: 500; letter-spacing: -.055em; line-height: 1.02; }
    .welcome-copy { max-width: 520px; margin: 24px 0 0; color: #58708f; font-size: 16px; line-height: 1.65; }
    .welcome-points { display: grid; gap: 16px; margin-top: 36px; }
    .welcome-points > div { display: flex; gap: 14px; align-items: flex-start; }
    .welcome-points > div > span { color: #1f7ac4; font-size: 12px; font-weight: 700; }
    .welcome-points p { display: grid; gap: 4px; margin: 0; }
    .welcome-points strong { color: #294563; font-size: 14px; font-weight: 600; }
    .welcome-points small { color: #8091a7; font-size: 12px; line-height: 1.45; }
    .welcome-note { margin: 42px 0 0; color: #8b9aac; font-size: 12px; }
    .login-card {
      width: min(100%, 420px);
      padding: 36px;
      border: 1px solid var(--tv-color-border, #e2e8f0);
      border-radius: 18px;
      background: var(--tv-color-surface, #fff);
      box-shadow: 0 16px 48px rgb(15 23 42 / 8%);
    }
    .login-card h1 { margin: 0 0 8px; font-size: 27px; }
    .trial-banner { display: grid; gap: 6px; margin: -4px 0 22px; padding: 12px 14px; border: 1px solid #b9dcca; border-radius: 9px; color: #286345; background: #effaf3; font-size: 12px; line-height: 1.45; }
    .trial-banner strong { font-size: 13px; }
    .intro { margin: 0 0 24px; color: #64748b; line-height: 1.5; }
    .oidc-button {
      min-height: 46px; display: grid; place-items: center; border-radius: 8px;
      background: var(--tv-color-primary, #4f46e5); color: white;
      font-weight: 650; text-decoration: none;
    }
    .oidc-button:hover { filter: brightness(.95); }
    .google-button { min-height: 44px; display: grid; place-items: center; margin-top: 10px; border: 1px solid #cbd5e1; border-radius: 8px; color: #1e293b; background: white; font-weight: 600; text-decoration: none; }
    .google-button:hover { background: #f8fafc; }
    .register-link { display: block; margin-top: 14px; color: var(--tv-color-primary, #1d4ed8); font-size: 13px; text-align: center; text-decoration: none; }
    .register-link:hover { text-decoration: underline; }
    .trial-register { display: flex; align-items: center; justify-content: center; min-height: 44px; margin-top: 14px; border-radius: 8px; color: #123f78; background: #eaf3fb; font-weight: 700; text-decoration: none; }
    .trial-register:hover { background: #dcebf8; text-decoration: none; }
    .separator {
      display: flex; align-items: center; gap: 12px; margin: 20px 0;
      color: #64748b; font-size: 12px;
    }
    .separator::before, .separator::after {
      content: ''; height: 1px; flex: 1; background: #e2e8f0;
    }
    .notice {
      margin-bottom: 20px; padding: 12px; border-radius: 8px;
      background: #f1f5f9; color: #475569; font-size: 12px; line-height: 1.5;
    }
    .error-message { color: #b91c1c; font-size: 13px; }
    form { display: grid; gap: 10px; }
    label { margin-top: 8px; font-size: 13px; font-weight: 650; }
    input {
      box-sizing: border-box; width: 100%; min-height: 44px; padding: 10px 12px;
      border: 1px solid #cbd5e1; border-radius: 8px; font: inherit;
    }
    input:focus { outline: 2px solid var(--tv-color-accent, #1f7ac4); outline-offset: 1px; }
    button {
      min-height: 46px; margin-top: 16px; border: 0; border-radius: 8px;
      background: var(--tv-color-primary, #4f46e5); color: white;
      font: inherit; font-weight: 650; cursor: pointer;
    }
    button:disabled { cursor: not-allowed; opacity: .55; }
    @media (max-width: 820px) { .login-page { grid-template-columns: 1fr; gap: 28px; max-width: 520px; padding: 32px 20px; } .welcome-panel { padding: 0; } .eyebrow { margin-top: 32px; } .welcome-panel h1 { font-size: 44px; } }
    @media (max-width: 480px) { .login-page { padding: 24px 16px; } .login-card { padding: 28px 24px; } .welcome-panel h1 { font-size: 38px; } }
    .login-page.embedded { min-height: 0; width: 100%; max-width: none; display: block; box-sizing: border-box; padding: 48px 28px 28px; background: transparent; }
    .login-page.embedded .login-card { display: block; box-sizing: border-box; width: 100%; max-width: none; padding: 28px 24px 24px; box-shadow: none; }
  `],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LoginComponent implements OnInit {
  @Input() embedded = false;
  @Input() trialMode = false;
  @Input() returnUrl = '/dashboard';
  login = '';
  password = '';
  loading = false;
  errorMessage = '';
  developmentLoginEnabled = false;

  private readonly translations = inject(TranslationService);

  get keycloakLoginUrl(): string {
    return this.auth.oidcLoginUrl(this.getReturnUrl());
  }

  get googleLoginUrl(): string {
    return this.auth.oidcLoginUrl(this.getReturnUrl(), 'google');
  }

  get registrationUrl(): string {
    return this.auth.oidcLoginUrl(this.getReturnUrl(), undefined, true);
  }

  constructor(
    private readonly auth: AuthService,
    private readonly router: Router,
    private readonly route: ActivatedRoute,
  ) {}

  ngOnInit(): void {
    const authStart = this.route.snapshot.queryParamMap.get('authStart');
    if (authStart === 'keycloak' || authStart === 'google' || authStart === 'register') {
      const target = authStart === 'keycloak'
        ? this.keycloakLoginUrl
        : authStart === 'google'
          ? this.googleLoginUrl
          : this.registrationUrl;
      window.location.assign(target);
      return;
    }

    if (this.route.snapshot.queryParamMap.get('loggedOut') === '1') {
      window.location.assign('http://localhost:8025');
      return;
    }

    const authError = this.route.snapshot.queryParamMap.get('authError');
    this.errorMessage = authError
      ? authError === 'inactive'
        ? this.translations.translate('Auth.InactiveAccount')
        : this.translations.translate('Auth.OidcIncomplete')
      : this.route.snapshot.queryParamMap.get('sessionExpired') === '1'
        ? this.translations.translate('Auth.SessionExpired')
        : '';

    this.auth.initialize().subscribe({
      next: (user) => {
        if (user) {
          void this.router.navigateByUrl(this.getReturnUrl());
        }
      },
      error: () => {
        this.errorMessage = this.translations.translate('Auth.StatusCheckFailed');
      },
    });

    this.auth.authConfiguration().subscribe({
      next: (configuration) => {
        this.developmentLoginEnabled = configuration.development_login_enabled;
      },
    });
  }

  submit(): void {
    if (this.loading || !this.login.trim() || !this.password) {
      return;
    }

    this.loading = true;
    this.errorMessage = '';

    this.auth.login(this.login.trim(), this.password).subscribe({
      next: () => {
        this.loading = false;
        void this.router.navigateByUrl(this.getReturnUrl());
      },
      error: (error: HttpErrorResponse) => {
        this.loading = false;
        this.errorMessage =
          error.status === 404
            ? this.translations.translate('Auth.DevelopmentLoginDisabled')
            : error.status === 422
              ? this.translations.translate('Auth.InvalidCredentials')
              : this.translations.translate('Auth.SignInFailed');
      },
    });
  }

  startAuth(event: MouseEvent, start: 'keycloak' | 'google' | 'register'): void {
    event.preventDefault();
    window.location.assign(this.auth.oidcLogoutThenStartUrl(start));
  }

  private getReturnUrl(): string {
    const value = this.route.snapshot.queryParamMap.get('returnUrl');

    return value && value.startsWith('/') && !value.startsWith('//')
      ? value
      : this.returnUrl;
  }
}
