import { ChangeDetectionStrategy, Component, OnInit, inject } from '@angular/core';
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
    <main class="login-page">
      <section class="login-card" aria-labelledby="login-title">
        <tv-brand-logo class="brand" [size]="38" />

        <h1 id="login-title">{{ 'Auth.WelcomeBack' | translate }}</h1>
        <p class="intro">{{ 'Auth.SignInIntro' | translate }}</p>

        <a class="oidc-button" [href]="keycloakLoginUrl">{{ 'Auth.ContinueWithKeycloak' | translate }}</a>
        <div class="separator" aria-hidden="true"><span>{{ 'Auth.DevelopmentLogin' | translate }}</span></div>

        <div class="notice" role="status">
          {{ 'Auth.DevelopmentNotice' | translate }}
        </div>

        @if (errorMessage) {
          <p class="error-message" role="alert">{{ errorMessage }}</p>
        }

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
      </section>
    </main>
  `,
  styles: [`
    :host { display: block; min-height: 100vh; }
    .login-page {
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 24px;
      background: var(--tv-color-background, #f5f7fb);
    }
    .login-card {
      width: min(100%, 420px);
      padding: 36px;
      border: 1px solid var(--tv-color-border, #e2e8f0);
      border-radius: 18px;
      background: var(--tv-color-surface, #fff);
      box-shadow: 0 16px 48px rgb(15 23 42 / 8%);
    }
    .brand { margin-bottom: 30px; }
    h1 { margin: 0 0 8px; font-size: 27px; }
    .intro { margin: 0 0 24px; color: #64748b; line-height: 1.5; }
    .oidc-button {
      min-height: 46px; display: grid; place-items: center; border-radius: 8px;
      background: var(--tv-color-primary, #4f46e5); color: white;
      font-weight: 650; text-decoration: none;
    }
    .oidc-button:hover { filter: brightness(.95); }
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
  `],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LoginComponent implements OnInit {
  login = '';
  password = '';
  loading = false;
  errorMessage = '';

  private readonly translations = inject(TranslationService);

  get keycloakLoginUrl(): string {
    return this.auth.oidcLoginUrl(this.getReturnUrl());
  }

  constructor(
    private readonly auth: AuthService,
    private readonly router: Router,
    private readonly route: ActivatedRoute,
  ) {}

  ngOnInit(): void {
    const authError = this.route.snapshot.queryParamMap.get('authError');
    this.errorMessage = authError
      ? authError === 'inactive'
        ? this.translations.translate('Auth.InactiveAccount')
        : this.translations.translate('Auth.OidcIncomplete')
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

  private getReturnUrl(): string {
    const value = this.route.snapshot.queryParamMap.get('returnUrl');

    return value && value.startsWith('/') && !value.startsWith('//')
      ? value
      : '/dashboard';
  }
}
