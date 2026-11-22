import { ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit, inject } from '@angular/core';
import { TaxIconComponent } from '../../shared/components/tax-icon/tax-icon.component';
import { BackNavigationComponent } from '../../shared/components/back-navigation/back-navigation.component';
import { TaxNewsDto, TaxWorkspaceApiService } from '../../shared/services/tax-workspace-api.service';
import { CollapsibleCardDirective } from '../../shared/directives/collapsible-card.directive';
import { TranslatePipe } from '../../shared/i18n/translate.pipe';
import { TranslationService } from '../../shared/i18n/translation.service';

@Component({
  selector: 'app-tax-news',
  imports: [TaxIconComponent, BackNavigationComponent, CollapsibleCardDirective, TranslatePipe],
  template: `
    <div class="news-page tv-workspace-page">
      <tv-back-navigation />
      <header class="page-header">
        <div>
          <div class="eyebrow">{{ 'TaxNews.Eyebrow' | translate }}</div>
          <h1>{{ 'TaxNews.Title' | translate }}</h1>
          <p>{{ 'TaxNews.Description' | translate }}</p>
        </div>
        <button type="button" class="sync-button" [disabled]="isSyncing" (click)="sync()">
          {{ isSyncing ? ('TaxNews.Syncing' | translate) : ('TaxNews.SyncNow' | translate) }}
        </button>
      </header>

      <nav class="filters" aria-label="Tax news filters">
        @for (filter of filters; track filter.value) {
          <button type="button" [class.active]="category === filter.value" (click)="selectCategory(filter.value)">{{ filter.key | translate }}</button>
        }
      </nav>

      @if (errorMessage) { <p class="notice error" role="alert">{{ errorMessage }}</p> }
      @if (isLoading) { <section class="news-card loading" role="status">Loading tax news…</section> }
      @else if (news.length === 0) {
        <section class="news-card empty" tvCollapsibleCard>
          <h2>{{ 'TaxNews.NoNews' | translate }}</h2>
          <p>{{ 'TaxNews.ScheduledSync' | translate }}</p>
        </section>
      } @else {
        <section class="news-card" tvCollapsibleCard aria-label="Tax news list">
          <div class="news-table-header"><span>{{ 'TaxNews.Update' | translate }}</span><span>{{ 'TaxNews.Source' | translate }}</span><span>{{ 'TaxNews.Status' | translate }}</span><span>{{ 'TaxNews.Published' | translate }}</span></div>
          @for (item of news; track item.id) {
            <article class="news-row" [class.important]="item.importance === 'important'">
              <div class="news-main">
                @if (item.importance === 'important') { <span class="important-mark"><tv-icon name="warning" /></span> }
                <div>
                  <a [href]="item.url" target="_blank" rel="noopener noreferrer">{{ item.title }}</a>
                  @if (item.ai_summary) { <span class="ai-badge">{{ 'TaxNews.AISummary' | translate }}</span> }
                  @if (item.summary) { <p>{{ item.summary }}</p> }
                  @if (item.ai_summary) { <p class="ai-summary"><strong>{{ 'TaxNews.AISummary' | translate }}:</strong> {{ item.ai_summary }}</p> }
                  @if (item.ai_reason) { <small class="ai-reason"><strong>{{ 'TaxNews.AIRelevance' | translate }}:</strong> {{ item.ai_reason }}</small> }
                  <small>{{ categoryLabel(item.category) }}</small>
                </div>
              </div>
              <span>{{ item.source }}</span>
              <span class="status" [class]="'status status-' + item.status">{{ ('TaxNews.StatusLabel.' + item.status) | translate }}</span>
              <time [attr.datetime]="item.published_at">{{ dateLabel(item.published_at) }}</time>
            </article>
          }
        </section>
      }
      <p class="disclaimer">{{ 'TaxNews.Disclaimer' | translate }}</p>
    </div>
   `,
  styles: [`
    :host { display: block; }
    .news-page { width: min(100%, var(--tv-content-max-width)); margin: 0 auto; padding: 40px 40px 64px; }
    .page-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; margin-bottom: 22px; }
    .eyebrow { margin-bottom: 8px; color: var(--tv-color-primary); font-size: 12px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
    h1, h2, p { margin: 0; }
    h1 { font-size: 30px; }
    .page-header p { margin-top: 8px; color: var(--tv-color-text-secondary); font-size: 14px; }
    .sync-button { flex: 0 0 auto; padding: 10px 16px; border: 1px solid var(--tv-color-primary); border-radius: 8px; background: var(--tv-color-primary); color: #fff; font-weight: 700; cursor: pointer; }
    .sync-button:hover:not(:disabled) { background: var(--tv-color-primary-dark); }
    .sync-button:disabled { cursor: wait; opacity: .65; }
    .filters { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
    .filters button { padding: 7px 12px; border: 1px solid var(--tv-color-border); border-radius: var(--tv-radius-sm); background: var(--tv-color-surface); color: var(--tv-color-text); cursor: pointer; }
    .filters button:hover, .filters button.active { border-color: var(--tv-color-primary); background: var(--tv-color-primary-soft); color: var(--tv-color-primary-dark); }
    .news-card { overflow: hidden; border: 1px solid var(--tv-color-border); border-radius: var(--tv-radius-lg); background: var(--tv-color-surface); box-shadow: var(--tv-shadow-card); }
    .loading, .empty { padding: 24px; }
    .news-table-header, .news-row { display: grid; grid-template-columns: minmax(0, 2.6fr) minmax(130px, 1fr) minmax(100px, .7fr) minmax(110px, .75fr); gap: 18px; align-items: center; padding: 14px 20px; }
    .news-table-header { background: var(--tv-color-table-stripe); color: var(--tv-color-text-secondary); font-size: 12px; font-weight: 650; }
    .news-row { border-top: 1px solid var(--tv-color-border); font-size: 12px; }
    .news-row.important { background: #fffaf0; }
    .news-main { display: flex; gap: 10px; min-width: 0; }
    .news-main a { color: var(--tv-color-primary); font-size: 14px; font-weight: 700; }
    .news-main a:hover { color: var(--tv-color-primary-dark); text-decoration: underline; }
    .news-main p { margin-top: 5px; color: var(--tv-color-text-secondary); line-height: 1.4; }
    .news-main small { display: inline-block; margin-top: 6px; color: var(--tv-color-text-muted); }
    .ai-badge { display: inline-block; margin-left: 7px; padding: 3px 6px; border-radius: 999px; background: #e8f0fb; color: var(--tv-color-primary); font-size: 10px; font-weight: 700; }
    .news-main .ai-summary { color: var(--tv-color-primary); }
    .news-main .ai-reason { color: var(--tv-color-text-secondary); line-height: 1.4; }
    .important-mark { color: var(--tv-color-warning); }
    .status { justify-self: start; padding: 4px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; text-transform: capitalize; }
    .status-important, .status-effective { background: var(--tv-color-warning-soft); color: var(--tv-color-warning); }
    .status-passed { background: var(--tv-color-success-soft); color: var(--tv-color-success); }
    .status-proposed { background: var(--tv-color-primary-soft); color: var(--tv-color-primary); }
    .status-guidance { background: #f3f4f6; color: var(--tv-color-text-secondary); }
    time { color: var(--tv-color-text-secondary); }
    .disclaimer { margin-top: 14px; color: var(--tv-color-text-muted); font-size: 11px; }
    .notice.error { margin-bottom: 16px; color: var(--tv-color-danger); }
    @media (max-width: 760px) { .news-page { padding: 24px 16px 48px; } .page-header { flex-direction: column; } .sync-button { width: 100%; } .news-table-header { display: none; } .news-row { grid-template-columns: 1fr auto; gap: 10px; } .news-row > span, .news-row > time { justify-self: end; } .news-main { grid-row: span 2; } }
   `],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TaxNewsComponent implements OnInit {
  private readonly api = inject(TaxWorkspaceApiService);
  private readonly cdr = inject(ChangeDetectorRef);
  private readonly translations = inject(TranslationService);

  readonly filters = [
    { value: 'all', key: 'TaxNews.All' },
    { value: 'rental', key: 'TaxNews.Rental' },
    { value: 'individuals', key: 'TaxNews.Individuals' },
    { value: 'small_business', key: 'TaxNews.SmallBusiness' },
    { value: 'super', key: 'TaxNews.Super' },
    { value: 'general', key: 'TaxNews.General' },
  ];
  news: TaxNewsDto[] = [];
  category = 'all';
  isLoading = true;
  isSyncing = false;
  errorMessage = '';

  ngOnInit(): void { this.load(); }
  selectCategory(category: string): void { this.category = category; this.isLoading = true; this.load(); }
  sync(): void {
    this.isSyncing = true;
    this.errorMessage = '';
    this.api.syncTaxNews().subscribe({
      next: () => { this.isSyncing = false; this.isLoading = true; this.load(); },
      error: () => { this.isSyncing = false; this.errorMessage = this.translations.translate('TaxNews.SyncFailed'); this.cdr.markForCheck(); },
    });
  }
  dateLabel(value: string | null): string { return value ? new Intl.DateTimeFormat('en-AU', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(value)) : '—'; }
  categoryLabel(value: string): string { return this.translations.translate(`TaxNews.Category.${value}`); }

  private load(): void {
    this.api.listTaxNews(this.category, false, true).subscribe({
      next: (news) => { this.news = news; this.isLoading = false; this.cdr.markForCheck(); },
      error: () => { this.errorMessage = this.translations.translate('TaxNews.LoadFailed'); this.isLoading = false; this.cdr.markForCheck(); },
    });
  }
}
