import { ChangeDetectionStrategy, ChangeDetectorRef, Component, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';

import { Announcement, AnnouncementInput, AnnouncementService } from '../../shared/services/announcement.service';
import { AnnouncementBannerItemComponent } from '../../shared/components/announcement-banner-item/announcement-banner-item.component';

interface AnnouncementForm extends Omit<AnnouncementInput, 'starts_at' | 'ends_at'> {
  action_label: string;
  action_url: string;
  starts_at_local: string;
  ends_at_local: string;
}

interface SydneyDateParts {
  year: string;
  month: string;
  day: string;
  hour: string;
  minute: string;
}

@Component({
  selector: 'app-announcement-manager',
  imports: [FormsModule, AnnouncementBannerItemComponent],
  template: `
    <section class="announcement-manager">
      <header class="manager-heading">
        <div><p class="eyebrow">Customer communications</p><h2>Announcements</h2><p>Publish notices across the landing page, sign-in page, and signed-in workspace.</p></div>
        <button type="button" class="new-button" (click)="newAnnouncement()">New announcement</button>
      </header>

      @if (error) { <p class="feedback error" role="alert">{{ error }}</p> }
      @if (success) { <p class="feedback success" role="status">{{ success }}</p> }

      @if (editing) {
        <form class="editor" (ngSubmit)="save()">
          <div class="editor-heading"><h3>{{ editingId ? 'Edit announcement' : 'Create announcement' }}</h3><button type="button" class="text-button" (click)="cancelEdit()">Close</button></div>
          <div class="form-grid">
            <label class="wide">Title
              <input name="title" [(ngModel)]="form.title" maxlength="120" required placeholder="Scheduled maintenance" />
            </label>
            <label class="wide">Message
              <textarea name="message" [(ngModel)]="form.message" maxlength="2000" rows="3" required placeholder="Tell customers what they need to know."></textarea>
            </label>
            <label>Color
              <select name="theme" [(ngModel)]="form.theme">
                <option value="blue">Blue · information</option><option value="teal">Teal · positive</option><option value="amber">Amber · caution</option><option value="red">Red · urgent</option><option value="violet">Violet · special</option>
              </select>
            </label>
            <label>Icon
              <select name="icon" [(ngModel)]="form.icon">
                <option value="megaphone">Megaphone</option><option value="info">Information</option><option value="warning">Warning</option><option value="celebration">Celebration</option><option value="none">No icon</option>
              </select>
            </label>
            <label>Font family
              <select name="font_family" [(ngModel)]="form.font_family">
                <option value="system">System</option><option value="serif">Serif</option><option value="mono">Monospace</option>
              </select>
            </label>
            <div class="checks">
              <label class="check"><input type="checkbox" name="is_bold" [(ngModel)]="form.is_bold" /> Bold message</label>
              <label class="check"><input type="checkbox" name="is_dismissible" [(ngModel)]="form.is_dismissible" /> Let visitors dismiss it</label>
              <label class="check publish-check"><input type="checkbox" name="is_published" [(ngModel)]="form.is_published" /> Published</label>
            </div>
            <label>Button label <span class="optional">Optional</span>
              <input name="action_label" [(ngModel)]="form.action_label" maxlength="60" placeholder="Read the update" />
            </label>
            <label>Button link <span class="optional">Site path or HTTPS link</span>
              <input name="action_url" [(ngModel)]="form.action_url" maxlength="2048" placeholder="/help or https://example.com" />
            </label>
            <label>Start time <span class="optional">Australia/Sydney · blank means now</span>
              <input type="datetime-local" name="starts_at" [(ngModel)]="form.starts_at_local" />
            </label>
            <label>End time <span class="optional">Australia/Sydney · blank means no end</span>
              <input type="datetime-local" name="ends_at" [(ngModel)]="form.ends_at_local" />
            </label>
          </div>

          <div class="preview-block">
            <div class="preview-heading"><h4>Live preview</h4><span>Shown at the top of the site</span></div>
            <app-announcement-banner-item [announcement]="previewAnnouncement" [preview]="true" />
          </div>

          <div class="form-actions">
            <button type="submit" class="save-button" [disabled]="saving">{{ saving ? 'Saving…' : (editingId ? 'Save changes' : 'Save announcement') }}</button>
            <button type="button" class="cancel-button" (click)="cancelEdit()">Cancel</button>
          </div>
        </form>
      }

      <div class="announcement-list">
        <div class="list-heading"><h3>All announcements</h3><span>{{ announcements.length }} shown</span></div>
        @if (loading) { <p class="empty">Loading announcements…</p> }
        @else if (!announcements.length) { <p class="empty">No announcements yet. Create one to post a notice.</p> }
        @else {
          @for (announcement of announcements; track announcement.id) {
            <article class="announcement-row">
              <span class="theme-dot" [class]="'theme-dot theme-' + announcement.theme" aria-hidden="true"></span>
              <div class="row-copy"><div class="row-title"><strong>{{ announcement.title }}</strong><span class="status" [class]="'status ' + statusClass(announcement)">{{ statusLabel(announcement) }}</span></div><p>{{ announcement.message }}</p><small>{{ scheduleLabel(announcement) }}</small></div>
              <div class="row-actions"><button type="button" (click)="editAnnouncement(announcement)">Edit</button><button type="button" class="delete-button" (click)="deleteAnnouncement(announcement)">Delete</button></div>
            </article>
          }
        }
      </div>
    </section>
  `,
  styles: [`
    :host { display:block; }
    .announcement-manager { display:grid; gap:18px; padding:22px; border:1px solid #dce5ee; border-radius:12px; background:#fff; }
    .manager-heading,.editor-heading,.preview-heading,.list-heading,.row-title { display:flex; align-items:center; justify-content:space-between; gap:14px; }
    .manager-heading { align-items:flex-start; }
    .manager-heading h2 { margin:0; color:#18385f; font-size:22px; font-weight:600; }
    .manager-heading p:last-child { margin:7px 0 0; color:#6d8197; font-size:13px; }
    .eyebrow { margin:0 0 5px; color:#1f7ac4; font-size:11px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
    button { border:0; border-radius:7px; padding:9px 13px; font:inherit; font-size:13px; font-weight:650; cursor:pointer; }
    button:disabled { opacity:.55; cursor:wait; }
    .new-button,.save-button { background:#123f78; color:#fff; }
    .editor { display:grid; gap:18px; padding:20px; border:1px solid #dce5ee; border-radius:10px; background:#f8fafc; }
    .editor-heading h3,.list-heading h3 { margin:0; color:#18385f; font-size:17px; font-weight:600; }
    .text-button,.cancel-button { background:#e9eef4; color:#18385f; }
    .form-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
    label { display:grid; gap:6px; color:#3e5772; font-size:12px; font-weight:650; }
    .wide { grid-column:1/-1; }
    input:not([type=checkbox]),textarea,select { width:100%; min-width:0; padding:9px 11px; border:1px solid #cbd8e5; border-radius:7px; background:#fff; color:#18385f; font:inherit; font-size:13px; }
    textarea { resize:vertical; line-height:1.5; }
    input:focus,textarea:focus,select:focus { outline:2px solid #74a7da; outline-offset:1px; }
    .optional { color:#75869a; font-size:11px; font-weight:400; }
    .checks { display:flex; flex-wrap:wrap; align-content:center; gap:8px 16px; }
    .check { display:flex; align-items:center; gap:7px; font-size:12px; font-weight:550; }
    .check input { accent-color:#174f83; }
    .publish-check { color:#1d684b; }
    .preview-block { display:grid; gap:8px; }
    .preview-heading h4 { margin:0; color:#526983; font-size:12px; }
    .preview-heading span,.list-heading > span { color:#75869a; font-size:11px; }
    .preview-block app-announcement-banner-item { border:1px solid #dce5ee; border-radius:8px; overflow:hidden; }
    .form-actions { display:flex; gap:8px; }
    .announcement-list { display:grid; gap:10px; }
    .list-heading { padding-bottom:5px; }
    .announcement-row { display:flex; align-items:flex-start; gap:12px; padding:14px 0; border-top:1px solid #e7edf3; }
    .theme-dot { width:10px; height:10px; margin-top:5px; flex:none; border-radius:50%; }
    .theme-blue { background:#4388c7; } .theme-teal { background:#258c7a; } .theme-amber { background:#bc8400; } .theme-red { background:#bc4942; } .theme-violet { background:#7650a8; }
    .row-copy { flex:1; min-width:0; }
    .row-title { justify-content:flex-start; flex-wrap:wrap; }
    .row-title strong { color:#18385f; font-size:13px; }
    .row-copy p { margin:5px 0; color:#526983; font-size:12px; line-height:1.4; overflow-wrap:anywhere; }
    .row-copy small { color:#75869a; font-size:11px; }
    .status { padding:3px 7px; border-radius:99px; font-size:10px; font-weight:700; }
    .status-active { background:#e5f5ed; color:#17633f; } .status-scheduled { background:#eaf3ff; color:#214d79; } .status-ended { background:#edf0f3; color:#586879; } .status-draft { background:#f1edf8; color:#604786; }
    .row-actions { display:flex; gap:6px; }
    .row-actions button { padding:6px 9px; background:#e9eef4; color:#18385f; font-size:11px; }
    .row-actions .delete-button { background:#f9e8e6; color:#8c332d; }
    .empty { margin:0; padding:18px 0; color:#75869a; font-size:13px; }
    .feedback { margin:0; padding:10px 12px; border-radius:7px; font-size:12px; }
    .error { background:#fde9e7; color:#702b26; } .success { background:#e4f5f1; color:#15554c; }
    @media(max-width:700px) { .announcement-manager { padding:16px; } .manager-heading { flex-direction:column; } .form-grid { grid-template-columns:1fr; } .wide { grid-column:auto; } .announcement-row { flex-wrap:wrap; } .row-actions { margin-left:22px; } }
  `],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AnnouncementManagerComponent {
  private readonly api = inject(AnnouncementService);
  private readonly cdr = inject(ChangeDetectorRef);

  announcements: Announcement[] = [];
  loading = true;
  saving = false;
  editing = false;
  editingId: string | null = null;
  error = '';
  success = '';
  form = this.newForm();

  constructor() {
    this.load();
  }

  get previewAnnouncement(): Announcement {
    return {
      id: this.editingId ?? 'preview',
      updated_at: 'preview',
      ...this.payload(),
      starts_at: null,
      ends_at: null,
    };
  }

  newAnnouncement(): void {
    this.editingId = null;
    this.form = this.newForm();
    this.editing = true;
    this.error = '';
    this.success = '';
  }

  editAnnouncement(announcement: Announcement): void {
    this.editingId = announcement.id;
    this.form = {
      title: announcement.title,
      message: announcement.message,
      theme: announcement.theme,
      icon: announcement.icon,
      font_family: announcement.font_family,
      is_bold: announcement.is_bold,
      is_dismissible: announcement.is_dismissible,
      action_label: announcement.action_label ?? '',
      action_url: announcement.action_url ?? '',
      is_published: announcement.is_published,
      starts_at_local: announcement.starts_at ? this.toSydneyInput(announcement.starts_at) : '',
      ends_at_local: announcement.ends_at ? this.toSydneyInput(announcement.ends_at) : '',
    };
    this.editing = true;
    this.error = '';
    this.success = '';
  }

  cancelEdit(): void {
    this.editing = false;
    this.editingId = null;
    this.error = '';
  }

  save(): void {
    this.error = '';
    this.success = '';
    this.saving = true;
    const request = this.editingId
      ? this.api.update(this.editingId, this.payload())
      : this.api.create(this.payload());
    request.subscribe({
      next: response => {
        this.saving = false;
        this.editing = false;
        this.editingId = null;
        this.success = response.data.is_published ? 'Announcement saved.' : 'Draft saved.';
        this.load();
        this.cdr.markForCheck();
      },
      error: response => {
        this.saving = false;
        this.error = response.error?.message || 'Unable to save this announcement. Check the times and link, then try again.';
        this.cdr.markForCheck();
      },
    });
  }

  deleteAnnouncement(announcement: Announcement): void {
    if (!window.confirm(`Delete “${announcement.title}”?`)) return;
    this.api.delete(announcement.id).subscribe({
      next: () => {
        this.success = 'Announcement deleted.';
        this.error = '';
        this.load();
      },
      error: () => {
        this.error = 'Unable to delete this announcement.';
        this.cdr.markForCheck();
      },
    });
  }

  statusLabel(announcement: Announcement): string {
    if (!announcement.is_published) return 'Draft';
    const now = Date.now();
    if (announcement.starts_at && new Date(announcement.starts_at).getTime() > now) return 'Scheduled';
    if (announcement.ends_at && new Date(announcement.ends_at).getTime() <= now) return 'Ended';
    return 'Active';
  }

  statusClass(announcement: Announcement): string {
    return `status-${this.statusLabel(announcement).toLowerCase()}`;
  }

  scheduleLabel(announcement: Announcement): string {
    if (announcement.starts_at && announcement.ends_at) return `${this.formatSydney(announcement.starts_at)} → ${this.formatSydney(announcement.ends_at)} Sydney time`;
    if (announcement.starts_at) return `Starts ${this.formatSydney(announcement.starts_at)} Sydney time`;
    if (announcement.ends_at) return `No start time · ends ${this.formatSydney(announcement.ends_at)} Sydney time`;
    return 'No schedule · publish immediately';
  }

  private load(): void {
    this.loading = true;
    this.api.list().subscribe({
      next: response => {
        this.announcements = response.data;
        this.loading = false;
        this.cdr.markForCheck();
      },
      error: () => {
        this.loading = false;
        this.error = 'Unable to load announcements.';
        this.cdr.markForCheck();
      },
    });
  }

  private payload(): AnnouncementInput {
    return {
      title: this.form.title.trim(),
      message: this.form.message.trim(),
      theme: this.form.theme,
      icon: this.form.icon,
      font_family: this.form.font_family,
      is_bold: this.form.is_bold,
      is_dismissible: this.form.is_dismissible,
      action_label: this.form.action_label.trim() || null,
      action_url: this.form.action_url.trim() || null,
      is_published: this.form.is_published,
      starts_at: this.fromSydneyInput(this.form.starts_at_local),
      ends_at: this.fromSydneyInput(this.form.ends_at_local),
    };
  }

  private newForm(): AnnouncementForm {
    return {
      title: '', message: '', theme: 'blue', icon: 'megaphone', font_family: 'system',
      is_bold: false, is_dismissible: true, action_label: '', action_url: '',
      is_published: false, starts_at_local: '', ends_at_local: '',
    };
  }

  private formatSydney(value: string): string {
    return new Intl.DateTimeFormat('en-AU', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Australia/Sydney' }).format(new Date(value));
  }

  private toSydneyInput(value: string): string {
    const parts = this.sydneyParts(new Date(value));
    return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
  }

  private fromSydneyInput(value: string): string | null {
    if (!value) return null;
    const [datePart, timePart] = value.split('T');
    const [year, month, day] = datePart.split('-').map(Number);
    const [hour, minute] = timePart.split(':').map(Number);
    const wanted = Date.UTC(year, month - 1, day, hour, minute);
    let candidate = wanted;
    for (let attempt = 0; attempt < 3; attempt++) {
      const parts = this.sydneyParts(new Date(candidate));
      const actual = Date.UTC(Number(parts.year), Number(parts.month) - 1, Number(parts.day), Number(parts.hour), Number(parts.minute));
      candidate += wanted - actual;
    }
    return new Date(candidate).toISOString();
  }

  private sydneyParts(value: Date): SydneyDateParts {
    const parts = new Intl.DateTimeFormat('en-AU', {
      timeZone: 'Australia/Sydney', year: 'numeric', month: '2-digit', day: '2-digit',
      hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
    }).formatToParts(value);
    const part = (type: string): string => parts.find(value => value.type === type)?.value ?? '';
    return { year: part('year'), month: part('month'), day: part('day'), hour: part('hour'), minute: part('minute') };
  }
}
