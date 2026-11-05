import { ChangeDetectionStrategy, ChangeDetectorRef, Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';

import { AuthService } from '../../services/auth/auth.service';
import { DeadlineDto, TaxWorkspaceApiService } from '../../services/tax-workspace-api.service';

@Component({
  selector: 'app-deadline-card',
  imports: [RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @for (deadline of deadlines; track deadline.date) {
      <section class="deadline" [class.done]="deadline.completed" [class.soon]="!deadline.completed && deadline.days_left <= 30" aria-label="Lodgement deadline">
        <div class="when">
          <strong>{{ deadline.completed ? '✓' : deadline.days_left }}</strong>
          <span>{{ deadline.completed ? 'done' : deadline.days_left === 1 ? 'day left' : 'days left' }}</span>
        </div>
        <div class="body">
          <h2>{{ deadline.label }} lodgement date: {{ formatDate(deadline.date) }}</h2>
          @if (deadline.completed) {
            <p>Marked as done. No more reminders for this date.</p>
          } @else {
            <ul>
              <li [class.ok]="deadline.documents_to_review === 0">
                @if (deadline.documents_to_review === 0) { ✓ No documents waiting for review }
                @else { <a routerLink="/documents" [queryParams]="{ status: 'review_required' }">{{ deadline.documents_to_review }} documents waiting for review</a> }
              </li>
              <li [class.ok]="deadline.uncategorised_transactions === 0">
                @if (deadline.uncategorised_transactions === 0) { ✓ Every transaction has a tax return item }
                @else { <a routerLink="/transactions">{{ deadline.uncategorised_transactions }} transactions without a tax return item</a> }
              </li>
            </ul>
          }
          <p class="note">This date is a reminder only. Confirm your real due date with the ATO or a tax agent.</p>
        </div>
        @if (!auth.isAccountant) {
          <button type="button" class="btn" [disabled]="busy" (click)="toggle(deadline)">{{ deadline.completed ? 'Reopen' : 'Mark as done' }}</button>
        }
      </section>
    }
  `,
  styles: [`
    :host { display: contents; }
    .deadline { display: flex; align-items: center; gap: 20px; padding: 18px 24px; border: 1px solid var(--tv-color-border); border-radius: var(--tv-radius-lg); background: var(--tv-color-surface); box-shadow: var(--tv-shadow-card); }
    .deadline.soon { border-color: var(--tv-color-primary); }
    .deadline.done { opacity: .75; }
    .when { display: grid; justify-items: center; min-width: 72px; color: var(--tv-color-primary); }
    .when strong { font-size: 34px; line-height: 1; }
    .when span { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    .body { flex: 1; min-width: 0; }
    h2 { margin: 0 0 6px; font-size: 16px; }
    ul { margin: 0 0 6px; padding: 0; list-style: none; display: grid; gap: 2px; font-size: 13px; }
    li.ok { color: var(--tv-color-success); }
    a { color: var(--tv-color-primary); font-weight: 600; }
    .note { margin: 0; color: var(--tv-color-text-secondary); font-size: 12px; }
    .btn { min-height: 36px; padding: 0 14px; border: 1px solid var(--tv-color-border); border-radius: 6px; background: var(--tv-color-surface); font-size: 13px; cursor: pointer; white-space: nowrap; }
    .btn:disabled { opacity: .55; cursor: not-allowed; }
    @media (max-width: 700px) { .deadline { flex-wrap: wrap; padding: 16px; } }
  `],
})
export class DeadlineCardComponent {
  private readonly api = inject(TaxWorkspaceApiService);
  private readonly cdr = inject(ChangeDetectorRef);
  readonly auth = inject(AuthService);

  deadlines: DeadlineDto[] = [];
  busy = false;

  constructor() {
    this.api.getDeadlines().subscribe({
      next: (items) => {
        this.deadlines = items.slice(0, 1);
        this.cdr.markForCheck();
      },
      error: () => undefined,
    });
  }

  formatDate(date: string): string {
    return new Date(`${date}T00:00:00`).toLocaleDateString('en-AU', { day: 'numeric', month: 'long', year: 'numeric' });
  }

  toggle(deadline: DeadlineDto): void {
    this.busy = true;
    this.api.setDeadlineCompleted(deadline.date, !deadline.completed).subscribe({
      next: () => {
        this.deadlines = this.deadlines.map((item) => (item === deadline ? { ...item, completed: !item.completed } : item));
        this.busy = false;
        this.cdr.markForCheck();
      },
      error: () => {
        this.busy = false;
        this.cdr.markForCheck();
      },
    });
  }
}
