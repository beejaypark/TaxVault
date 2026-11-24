import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  DestroyRef,
  ElementRef,
  HostListener,
  effect,
  inject,
} from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Router, RouterLink } from '@angular/router';
import { catchError, forkJoin, interval, of } from 'rxjs';

import { TaxIconComponent } from '../tax-icon/tax-icon.component';
import { DocumentApiService } from '../../services/documents/document-api.service';
import { DeadlineDto, TaxNewsDto, TaxWorkspaceApiService } from '../../services/tax-workspace-api.service';
import { UserPreferencesService } from '../../services/user-preferences.service';

export interface AppNotification {
  id: string;
  severity: 'warning' | 'info';
  title: string;
  detail: string;
  link: string;
  queryParams?: Record<string, string>;
}

interface BillingNotificationStatus {
  status: string | null;
  trial_ends_at: string | null;
  upload_access: boolean;
  billing_exempt: boolean;
}

const REFRESH_MS = 60_000;
const DEADLINE_WARNING_DAYS = 30;

@Component({
  selector: 'app-notifications',
  imports: [RouterLink, TaxIconComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './notifications.component.html',
  styleUrl: './notifications.component.scss',
})
export class NotificationsComponent {
  private readonly documentsApi = inject(DocumentApiService);
  private readonly http = inject(HttpClient);
  private readonly api = inject(TaxWorkspaceApiService);
  private readonly preferences = inject(UserPreferencesService);
  private readonly router = inject(Router);
  private readonly cdr = inject(ChangeDetectorRef);
  private readonly host = inject<ElementRef<HTMLElement>>(ElementRef);

  isOpen = false;
  notifications: AppNotification[] = [];

  constructor() {
    effect(() => {
      this.documentsApi.selectedFinancialYearId();
      this.preferences.preferences();
      this.load();
    });

    interval(REFRESH_MS)
      .pipe(takeUntilDestroyed(inject(DestroyRef)))
      .subscribe(() => this.load());
  }

  @HostListener('document:click', ['$event'])
  onDocumentClick(event: Event): void {
    if (this.isOpen && !this.host.nativeElement.contains(event.target as Node)) {
      this.isOpen = false;
      this.cdr.markForCheck();
    }
  }

  @HostListener('document:keydown.escape')
  onEscape(): void {
    if (this.isOpen) {
      this.isOpen = false;
      this.cdr.markForCheck();
    }
  }

  toggle(): void {
    this.isOpen = !this.isOpen;

    if (this.isOpen) {
      this.load();
    }
  }

  open(item: AppNotification): void {
    this.isOpen = false;
    void this.router.navigate([item.link], { queryParams: item.queryParams });
  }

  private load(): void {
    const prefs = this.preferences.preferences();
    const yearId = this.documentsApi.selectedFinancialYearId();

    forkJoin({
      billing: this.http.get<{ data: BillingNotificationStatus }>('/api/billing/status').pipe(catchError(() => of({ data: { status: null, trial_ends_at: null, upload_access: true, billing_exempt: true } as BillingNotificationStatus }))),
      documents: prefs.notifyDocuments
        ? this.documentsApi.listDocuments().pipe(catchError(() => of([])))
        : of([]),
      transactions: prefs.notifyUncategorised
        ? this.api.listTransactions().pipe(catchError(() => of([])))
        : of([]),
      deadlines: this.api.getDeadlines().pipe(catchError(() => of([] as DeadlineDto[]))),
      taxNews: prefs.notifyTaxNews
        ? this.api.listTaxNews().pipe(catchError(() => of([] as TaxNewsDto[])))
        : of([] as TaxNewsDto[]),
    }).subscribe(({ documents, transactions, deadlines, taxNews, billing }) => {
      const items: AppNotification[] = [];
      const billingStatus = billing.data;
      const count = (status: string) => documents.filter((document) => document.status === status).length;
      const plural = (n: number, word: string) => `${n} ${word}${n === 1 ? '' : 's'}`;

      if (!billingStatus.billing_exempt && billingStatus.trial_ends_at) {
        const daysLeft = Math.ceil((new Date(billingStatus.trial_ends_at).setHours(0, 0, 0, 0) - new Date().setHours(0, 0, 0, 0)) / 86_400_000);
        if ([10, 5, 1, 0].includes(daysLeft)) {
          items.push({
            id: 'trial-ending',
            severity: daysLeft <= 1 ? 'warning' : 'info',
            title: daysLeft === 0 ? 'Your free trial ends today' : `Your free trial ends in ${plural(daysLeft, 'day')}`,
            detail: 'Choose a monthly or yearly plan in Settings to keep uploading documents.',
            link: '/settings',
          });
        } else if (!billingStatus.upload_access) {
          items.push({ id: 'trial-ended', severity: 'warning', title: 'Your free trial has ended', detail: 'Choose a monthly or yearly plan to resume document uploads.', link: '/settings' });
        }
      }

      const due = deadlines.find((deadline) => !deadline.completed && deadline.days_left >= 0 && deadline.days_left <= DEADLINE_WARNING_DAYS);
      if (due) {
        items.push({
          id: 'deadline',
          severity: due.days_left <= 7 ? 'warning' : 'info',
          title: `${due.label} lodgement date in ${plural(due.days_left, 'day')}`,
          detail: 'Check your documents and transactions, then mark it as done on the dashboard.',
          link: '/dashboard',
        });
      }

      const review = count('review_required');
      if (review > 0) {
        items.push({
          id: 'docs-review',
          severity: 'warning',
          title: `${plural(review, 'document')} need your review`,
          detail: 'Check the extracted details before they are used.',
          link: '/documents',
          queryParams: { status: 'review_required' },
        });
      }

      const failed = count('processing_failed');
      if (failed > 0) {
        items.push({
          id: 'docs-failed',
          severity: 'warning',
          title: `${plural(failed, 'document')} failed processing`,
          detail: 'Open the document to retry or upload it again.',
          link: '/documents',
          queryParams: { status: 'processing_failed' },
        });
      }

      const awaiting = count('awaiting_financial_year');
      if (awaiting > 0) {
        items.push({
          id: 'docs-awaiting-year',
          severity: 'info',
          title: `${plural(awaiting, 'document')} need a financial year`,
          detail: 'Assign a financial year so they appear in your records.',
          link: '/documents',
          queryParams: { status: 'awaiting_financial_year' },
        });
      }

      const uncategorised = transactions.filter(
        (transaction) => !transaction.ato_category && (!yearId || transaction.financial_year_id === yearId),
      ).length;
      if (uncategorised > 0) {
        items.push({
          id: 'txn-uncategorised',
          severity: 'info',
          title: `${plural(uncategorised, 'transaction')} without a tax return item`,
          detail: 'Categorise them so your accountant pack is complete.',
          link: '/transactions',
        });
      }

      const recentNewsCutoff = Date.now() - 30 * 24 * 60 * 60 * 1000;
      const importantNews = taxNews
        .filter((item) => item.importance === 'important')
        .filter((item) => !item.published_at || new Date(item.published_at).getTime() >= recentNewsCutoff)
        .slice(0, 3);
      importantNews.forEach((item) => items.push({
        id: `tax-news-${item.id}`,
        severity: 'warning',
        title: item.title,
        detail: `${item.source} · ${item.status}`,
        link: '/tax-news',
      }));

      this.notifications = items;
      this.cdr.markForCheck();
    });
  }
}
