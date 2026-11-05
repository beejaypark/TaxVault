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
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Router, RouterLink } from '@angular/router';
import { catchError, forkJoin, interval, of } from 'rxjs';

import { TaxIconComponent } from '../tax-icon/tax-icon.component';
import { DocumentApiService } from '../../services/documents/document-api.service';
import { DeadlineDto, TaxWorkspaceApiService } from '../../services/tax-workspace-api.service';
import { UserPreferencesService } from '../../services/user-preferences.service';

export interface AppNotification {
  id: string;
  severity: 'warning' | 'info';
  title: string;
  detail: string;
  link: string;
  queryParams?: Record<string, string>;
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
      documents: prefs.notifyDocuments
        ? this.documentsApi.listDocuments().pipe(catchError(() => of([])))
        : of([]),
      transactions: prefs.notifyUncategorised
        ? this.api.listTransactions().pipe(catchError(() => of([])))
        : of([]),
      deadlines: this.api.getDeadlines().pipe(catchError(() => of([] as DeadlineDto[]))),
    }).subscribe(({ documents, transactions, deadlines }) => {
      const items: AppNotification[] = [];
      const count = (status: string) => documents.filter((document) => document.status === status).length;
      const plural = (n: number, word: string) => `${n} ${word}${n === 1 ? '' : 's'}`;

      const due = deadlines.find((deadline) => !deadline.completed && deadline.days_left <= DEADLINE_WARNING_DAYS);
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

      this.notifications = items;
      this.cdr.markForCheck();
    });
  }
}
