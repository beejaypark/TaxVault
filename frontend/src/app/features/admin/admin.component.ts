import { DatePipe } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { ChangeDetectionStrategy, ChangeDetectorRef, Component, inject } from '@angular/core';

interface AdminOverview {
  total_users: number;
  new_users_7_days: number;
  new_users_30_days: number;
  recent_users: Array<{ id: string; display_name: string | null; email: string | null; identity_provider: string; billing_exempt: boolean; created_at: string }>;
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
        <section class="search-panel">
          <label for="customer-search">Find a customer</label>
          <div class="search-controls">
            <input id="customer-search" #searchInput type="search" placeholder="Name or email" (keyup.enter)="loadOverview(searchInput.value)" />
            <button type="button" (click)="loadOverview(searchInput.value)">Search</button>
            @if (searchTerm) { <button type="button" class="clear-button" (click)="searchInput.value = ''; loadOverview('')">Clear</button> }
          </div>
        </section>
        <section class="panel"><h2>Recent customers</h2><table><thead><tr><th>Name</th><th>Email</th><th>Provider</th><th>Registered</th><th>Billing</th></tr></thead><tbody>@for (user of overview.recent_users; track user.id) {<tr><td>{{ user.display_name || '—' }}</td><td>{{ user.email || '—' }}</td><td>{{ user.identity_provider }}</td><td>{{ user.created_at | date:'medium' }}</td><td><button type="button" class="exemption-button" (click)="toggleBillingExemption(user)">{{ user.billing_exempt ? 'Remove exemption' : 'Exempt from billing' }}</button></td></tr>}</tbody></table></section>
        <section class="panel"><h2>Registration notifications</h2><table><thead><tr><th>Recipient</th><th>Status</th><th>Sent</th><th>Error</th></tr></thead><tbody>@for (log of overview.notification_logs; track log.created_at) {<tr><td>{{ log.recipient }}</td><td>{{ log.status }}</td><td>{{ log.created_at | date:'medium' }}</td><td>{{ log.error || '—' }}</td></tr>}</tbody></table></section>
      }
    </main>
  `,
  styles: [`
    .admin-page { display:grid; gap:20px; width:min(100%,1180px); margin:auto; padding:40px 32px 64px; color:#172b4d; }
    .eyebrow { margin:0 0 8px; color:#1f7ac4; font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
    h1 { margin:0; font-size:34px; font-weight:500; } header > p:last-child { color:#6d8197; }
    .metrics { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; } .metrics article,.panel,.search-panel { padding:22px; border:1px solid #dce5ee; border-radius:12px; background:#fff; }
    .search-panel { display:grid; gap:10px; } .search-panel label { color:#526983; font-size:13px; font-weight:600; } .search-controls { display:flex; gap:8px; } .search-controls input { flex:1; min-width:0; padding:10px 12px; border:1px solid #cbd8e5; border-radius:7px; font:inherit; } .search-controls button { padding:10px 14px; border:0; border-radius:7px; background:#123f78; color:#fff; cursor:pointer; } .search-controls .clear-button { background:#e9eef4; color:#18385f; }
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
  searchTerm = '';

  constructor() {
    this.loadOverview();
  }

  loadOverview(search = ''): void {
    this.searchTerm = search.trim();
    const query = this.searchTerm ? `?search=${encodeURIComponent(this.searchTerm)}` : '';
    this.http.get<{ data: AdminOverview }>(`/api/admin/overview${query}`).subscribe({
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

  toggleBillingExemption(user: AdminOverview['recent_users'][number]): void {
    const exempt = !user.billing_exempt;
    this.http.patch<{ data: { billing_exempt: boolean } }>(`/api/admin/users/${user.id}/billing-exemption`, { billing_exempt: exempt }).subscribe({
      next: response => {
        user.billing_exempt = response.data.billing_exempt;
        this.changeDetector.markForCheck();
      },
      error: () => {
        this.error = 'Unable to update billing exemption.';
        this.changeDetector.markForCheck();
      },
    });
  }
}
