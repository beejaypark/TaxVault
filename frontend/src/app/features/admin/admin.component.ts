import { DatePipe } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { ChangeDetectionStrategy, ChangeDetectorRef, Component, inject } from '@angular/core';

interface AdminOverview {
  total_users: number;
  new_users_7_days: number;
  new_users_30_days: number;
  recent_users: Array<{ display_name: string | null; email: string | null; identity_provider: string; created_at: string }>;
  notification_logs: Array<{ recipient: string; status: string; created_at: string; error: string | null }>;
}

@Component({
  selector: 'app-admin',
  imports: [DatePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <main class="admin-page">
      <header><p class="eyebrow">Platform administration</p><h1>TaxVault overview</h1><p>Monitor customer registrations and administrator notifications.</p></header>
      @if (error) { <p class="error" role="alert">{{ error }}</p> }
      @if (data; as overview) {
        <section class="metrics">
          <article><span>Total customers</span><strong>{{ overview.total_users }}</strong></article>
          <article><span>New in 7 days</span><strong>{{ overview.new_users_7_days }}</strong></article>
          <article><span>New in 30 days</span><strong>{{ overview.new_users_30_days }}</strong></article>
        </section>
        <section class="panel"><h2>Recent customers</h2><table><thead><tr><th>Name</th><th>Email</th><th>Provider</th><th>Registered</th></tr></thead><tbody>@for (user of overview.recent_users; track user.email) {<tr><td>{{ user.display_name || '—' }}</td><td>{{ user.email || '—' }}</td><td>{{ user.identity_provider }}</td><td>{{ user.created_at | date:'medium' }}</td></tr>}</tbody></table></section>
        <section class="panel"><h2>Registration notifications</h2><table><thead><tr><th>Recipient</th><th>Status</th><th>Sent</th><th>Error</th></tr></thead><tbody>@for (log of overview.notification_logs; track log.created_at) {<tr><td>{{ log.recipient }}</td><td>{{ log.status }}</td><td>{{ log.created_at | date:'medium' }}</td><td>{{ log.error || '—' }}</td></tr>}</tbody></table></section>
      }
    </main>
  `,
  styles: [`
    .admin-page { display:grid; gap:20px; width:min(100%,1180px); margin:auto; padding:40px 32px 64px; color:#172b4d; }
    .eyebrow { margin:0 0 8px; color:#1f7ac4; font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
    h1 { margin:0; font-size:34px; font-weight:500; } header > p:last-child { color:#6d8197; }
    .metrics { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; } .metrics article,.panel { padding:22px; border:1px solid #dce5ee; border-radius:12px; background:#fff; }
    .metrics span { color:#6d8197; font-size:13px; } .metrics strong { display:block; margin-top:10px; color:#123f78; font-size:32px; }
    .panel h2 { margin:0 0 18px; color:#18385f; font-size:20px; font-weight:600; }
    table { width:100%; border-collapse:collapse; font-size:13px; } th,td { padding:11px 8px; border-bottom:1px solid #e3eaf1; text-align:left; } th { color:#526983; font-size:12px; } .error { color:#a32d2d; }
    @media (max-width:700px) { .admin-page { padding:24px 16px 40px; } .metrics { grid-template-columns:1fr; } .panel { overflow-x:auto; } table { min-width:620px; } }
  `],
})
export class AdminComponent {
  private readonly http = inject(HttpClient);
  private readonly changeDetector = inject(ChangeDetectorRef);
  data: AdminOverview | null = null;
  error = '';

  constructor() {
    this.http.get<{ data: AdminOverview }>('/api/admin/overview').subscribe({
      next: response => {
        this.data = response.data;
        this.changeDetector.markForCheck();
      },
      error: () => {
        this.error = 'Unable to load the administrator overview.';
        this.changeDetector.markForCheck();
      },
    });
  }
}
