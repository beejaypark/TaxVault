import { ChangeDetectionStrategy, Component, EventEmitter, Input, Output } from '@angular/core';

import { Announcement } from '../../services/announcement.service';

@Component({
  selector: 'app-announcement-banner-item',
  template: `
    <section
      class="announcement-banner"
      [class]="'announcement-banner theme-' + announcement.theme + ' font-' + announcement.font_family"
      role="status"
      aria-live="polite">
      @if (announcement.icon !== 'none') {
        <span class="announcement-icon" aria-hidden="true">
          @switch (announcement.icon) {
            @case ('megaphone') {
              <svg viewBox="0 0 24 24"><path d="M3 10v4h3l10 5V5L6 10H3zm3 5H4v-6h2v6zm3 1.2V7.8l8-4v16l-8-3.6zM19 8.2v7.6a4 4 0 0 0 0-7.6z"/></svg>
            }
            @case ('info') {
              <svg viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16zm-1-9h2v6h-2zm0-4h2v2h-2z"/></svg>
            }
            @case ('warning') {
              <svg viewBox="0 0 24 24"><path d="M12 3 1.6 21h20.8L12 3zm0 4 6.9 12H5.1L12 7zm-1 3v5h2v-5h-2zm0 6v2h2v-2h-2z"/></svg>
            }
            @case ('celebration') {
              <svg viewBox="0 0 24 24"><path d="m2 22 14-4L6 8 2 22zm4.8-4.2 1.3-4.5 3.2 3.2-4.5 1.3zM16.5 3l1.2-2 1.2 2 2 .7-2 1.2-.6 2.1-1.3-2-2-.7 1.5-1.3zM20 10l.7-1.5.8 1.5 1.5.5-1.5.8-.5 1.5-.8-1.5-1.5-.5L20 10zM11 4l.8-1.5.7 1.5 1.5.5-1.5.8-.5 1.5-.8-1.5-1.5-.5L11 4z"/></svg>
            }
          }
        </span>
      }
      <div class="announcement-copy">
        <strong>{{ announcement.title }}</strong>
        <span [class.message-bold]="announcement.is_bold">{{ announcement.message }}</span>
      </div>
      @if (announcement.action_label && announcement.action_url) {
        <a class="announcement-action" [href]="announcement.action_url"
           [attr.target]="isExternal ? '_blank' : null"
           [attr.rel]="isExternal ? 'noopener noreferrer' : null">
          {{ announcement.action_label }}
        </a>
      }
      @if (!preview && announcement.is_dismissible) {
        <button type="button" class="announcement-close" aria-label="Dismiss announcement" (click)="dismissed.emit()">×</button>
      }
    </section>
  `,
  styles: [`
    :host { display:block; }
    .announcement-banner { display:flex; align-items:center; justify-content:center; gap:14px; min-height:52px; padding:10px clamp(16px,4vw,44px); color:var(--announcement-ink); background:var(--announcement-bg); border-bottom:1px solid color-mix(in srgb,var(--announcement-ink) 14%,transparent); font-family:var(--announcement-font); }
    .theme-blue { --announcement-bg:#eaf3ff; --announcement-ink:#153e68; --announcement-button:#174f83; }
    .theme-teal { --announcement-bg:#e4f5f1; --announcement-ink:#15554c; --announcement-button:#17675b; }
    .theme-amber { --announcement-bg:#fff2d6; --announcement-ink:#624000; --announcement-button:#785000; }
    .theme-red { --announcement-bg:#fde9e7; --announcement-ink:#702b26; --announcement-button:#8f3028; }
    .theme-violet { --announcement-bg:#f1ebfb; --announcement-ink:#49316c; --announcement-button:#5a3d83; }
    .font-system { --announcement-font:system-ui,-apple-system,"Segoe UI",sans-serif; }
    .font-serif { --announcement-font:Georgia,"Times New Roman",serif; }
    .font-mono { --announcement-font:ui-monospace,SFMono-Regular,Consolas,monospace; }
    .announcement-icon { display:grid; width:24px; height:24px; flex:none; place-items:center; }
    .announcement-icon svg { width:22px; height:22px; fill:currentColor; }
    .announcement-copy { display:flex; align-items:baseline; justify-content:center; flex-wrap:wrap; gap:4px 9px; min-width:0; text-align:center; }
    .announcement-copy strong { font-size:14px; }
    .announcement-copy > span { font-size:14px; line-height:1.45; }
    .message-bold { font-weight:700; }
    .announcement-action { flex:none; padding:6px 11px; border-radius:6px; background:var(--announcement-button); color:#fff; font-size:12px; font-weight:700; text-decoration:none; }
    .announcement-action:hover { filter:brightness(.92); }
    .announcement-close { flex:none; border:0; padding:2px 6px; background:transparent; color:inherit; font:inherit; font-size:23px; line-height:1; cursor:pointer; }
    @media(max-width:620px) { .announcement-banner { gap:9px; padding:10px 12px; } .announcement-copy { gap:2px 6px; } .announcement-copy strong,.announcement-copy > span { font-size:12px; } .announcement-action { font-size:11px; } }
  `],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AnnouncementBannerItemComponent {
  @Input({ required: true }) announcement!: Announcement;
  @Input() preview = false;
  @Output() dismissed = new EventEmitter<void>();

  get isExternal(): boolean {
    return this.announcement.action_url?.startsWith('https://') ?? false;
  }
}
