import { ChangeDetectionStrategy, Component, OnInit } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';

import { AuthService } from '../../../../shared/services/auth/auth.service';

@Component({
  selector: 'app-login',
  imports: [FormsModule],
  template: `
    <main class="login-page">
      <section class="login-card" aria-labelledby="login-title">
        <div class="brand">
          <div class="brand-mark">T</div>
          <span class="brand-name">TAXVAULT</span>
        </div>

        <h1 id="login-title">Welcome back</h1>
        <p class="intro">Sign in to access your tax documents and reviews.</p>

        <div class="notice" role="status">
          Development login is controlled by the backend environment.
          If sign-in is unavailable, contact your administrator.
        </div>

        @if (errorMessage) {
          <p class="error-message" role="alert">{{ errorMessage }}</p>
        }

        <form (ngSubmit)="submit()" #loginForm="ngForm">
          <label for="login">Login</label>
          <input
            id="login"
            name="login"
            type="text"
            autocomplete="username"
            [(ngModel)]="login"
            required
            maxlength="100"
          />

          <label for="password">Password</label>
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
            {{ loading ? 'Signing in…' : 'Sign in' }}
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
    .brand { display: flex; align-items: center; gap: 10px; margin-bottom: 30px; }
    .brand-mark {
      width: 34px; height: 34px; display: grid; place-items: center;
      border-radius: 9px; background: var(--tv-color-primary, #4f46e5);
      color: white; font-weight: 750;
    }
    .brand-name { font-size: 15px; font-weight: 750; letter-spacing: .05em; }
    h1 { margin: 0 0 8px; font-size: 27px; }
    .intro { margin: 0 0 24px; color: #64748b; line-height: 1.5; }
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
    input:focus { outline: 2px solid #818cf8; outline-offset: 1px; }
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

  constructor(
    private readonly auth: AuthService,
    private readonly router: Router,
    private readonly route: ActivatedRoute,
  ) {}

  ngOnInit(): void {
    this.auth.initialize().subscribe((user) => {
      if (user) {
        void this.router.navigateByUrl(this.getReturnUrl());
      }
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
            ? 'Development login is disabled on this backend.'
            : error.status === 422
              ? 'The supplied credentials are invalid.'
              : 'Unable to sign in. Check the server connection and try again.';
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
