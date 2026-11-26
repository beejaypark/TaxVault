import { ChangeDetectionStrategy, ChangeDetectorRef, Component, OnInit, inject } from '@angular/core';
import { DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';

import { AccessSection } from '../../shared/services/auth/auth.service';
import {
  AccountantDto,
  AccountantRequest,
  AccountantsApiService,
} from '../../shared/services/accountants-api.service';
import {
  DocumentApiService,
  FinancialYearDto,
} from '../../shared/services/documents/document-api.service';
import { TranslatePipe } from '../../shared/i18n/translate.pipe';
import { TranslationService } from '../../shared/i18n/translation.service';
import { TaxReadinessDto, TaxWorkspaceApiService } from '../../shared/services/tax-workspace-api.service';

const SECTION_LABEL_KEYS: Record<AccessSection, string> = {
  documents: 'Accountants.SectionDocuments',
  transactions: 'Accountants.SectionTransactions',
  reviews: 'Accountants.SectionReviews',
  assets: 'Accountants.SectionAssets',
};

interface AccountantForm {
  name: string;
  email: string;
  firm: string;
  sections: AccessSection[];
  allYears: boolean;
  yearIds: string[];
}

@Component({
  selector: 'app-accountants',
  imports: [FormsModule, DatePipe, TranslatePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <section class="page tv-workspace-page">
      <header class="head">
        <div>
          <h1>{{ 'Accountants.Title' | translate }}</h1>
          <p>{{ 'Accountants.Description' | translate }}</p>
        </div>
        <button type="button" class="primary-button" (click)="startCreate()">{{ 'Accountants.Add' | translate }}</button>
      </header>

      @if (message) {
        <p class="notice" role="status">{{ message }}</p>
      }
      @if (error) {
        <p class="notice error" role="alert">{{ error }}</p>
      }
      @if (temporaryPassword) {
        <div class="notice password" role="status">
          <strong>{{ 'Accountants.TemporaryPassword' | translate }} {{ temporaryFor }}</strong>
          <code>{{ temporaryPassword }}</code>
          <span>{{ 'Accountants.ShownOnce' | translate }}</span>
          <button type="button" class="secondary-button" (click)="copyPassword()">{{ 'Accountants.Copy' | translate }}</button>
          <button type="button" class="secondary-button" (click)="temporaryPassword = ''">{{ 'Accountants.Dismiss' | translate }}</button>
        </div>
      }

      @if (form) {
        <form class="panel form" (ngSubmit)="save()">
          <h2>{{ editingId ? ('Accountants.EditAccess' | translate) : ('Accountants.Add' | translate) }}</h2>
          <label>{{ 'Accountants.Name' | translate }} <input name="name" [(ngModel)]="form.name" required maxlength="120" /></label>
          @if (!editingId) {
            <label>{{ 'Accountants.Email' | translate }} <input name="email" type="email" [(ngModel)]="form.email" required /></label>
          }
          <label>{{ 'Accountants.Firm' | translate }} <input name="firm" [(ngModel)]="form.firm" maxlength="160" /></label>

          <fieldset>
            <legend>{{ 'Accountants.CanView' | translate }}</legend>
            @for (section of sectionKeys; track section) {
              <label class="check">
                <input
                  type="checkbox"
                  [name]="'s-' + section"
                  [checked]="form.sections.includes(section)"
                  (change)="toggleSection(section)"
                />
                {{ sectionLabel(section) | translate }}
              </label>
            }
          </fieldset>

          <fieldset>
            <legend>{{ 'Accountants.FinancialYears' | translate }}</legend>
            <label class="check">
              <input type="checkbox" name="allYears" [(ngModel)]="form.allYears" /> {{ 'Accountants.AllYears' | translate }}
            </label>
            @if (!form.allYears) {
              @for (year of financialYears; track year.id) {
                <label class="check">
                  <input
                    type="checkbox"
                    [name]="'y-' + year.id"
                    [checked]="form.yearIds.includes(year.id)"
                    (change)="toggleYear(year.id)"
                  />
                  {{ year.year_code }}
                </label>
              }
            }
          </fieldset>

          <div class="actions">
            <button type="submit" class="primary-button" [disabled]="busy">{{ 'Accountants.Save' | translate }}</button>
            <button type="button" class="secondary-button" (click)="form = null">{{ 'Accountants.Cancel' | translate }}</button>
          </div>
        </form>
      }

      @if (packFor) {
        <form class="panel form" (ngSubmit)="sendPack()">
          <h2>{{ 'Accountants.SendPackTo' | translate }} {{ packFor.name }}</h2>
          @if (packReadiness) {
            <div class="pack-preview" aria-label="Accountant pack readiness preview">
              <div><span>{{ 'Accountants.PackReadiness' | translate }}</span><strong>{{ packReadiness.score === null ? ('Dashboard.NotStarted' | translate) : packReadiness.score + '%' }}</strong></div>
              <p>{{ packReadiness.components.documents.completed }}/{{ packReadiness.components.documents.total }} {{ 'Accountants.DocumentsReviewed' | translate }} · {{ packReadiness.components.transactions.completed }}/{{ packReadiness.components.transactions.total }} {{ 'Accountants.TransactionsCategorised' | translate }}</p>
              @if (packReadiness.issues.length > 0) { <p class="pack-warning">{{ packReadiness.issues.length }} {{ 'Accountants.UnresolvedIssues' | translate }}</p> }
            </div>
          }
          <label>
            {{ 'Accountants.FinancialYears' | translate }}
            <select name="packYear" [(ngModel)]="packYear" (ngModelChange)="loadPackPreview()">
              <option value="">{{ 'Accountants.LatestAvailable' | translate }}</option>
              @for (year of packYears(packFor); track year.id) {
                <option [value]="year.id">{{ year.year_code }}</option>
              }
            </select>
          </label>
          <label>{{ 'Accountants.Note' | translate }} <textarea name="packNote" [(ngModel)]="packNote" maxlength="1000"></textarea></label>
          <label>{{ 'Accountants.FromName' | translate }} <input name="packFromName" [(ngModel)]="packFromName" maxlength="120" required /></label>
          <label>{{ 'Accountants.FromEmail' | translate }} <input name="packFrom" type="email" [(ngModel)]="packFrom" required /></label>
          <label>{{ 'Accountants.To' | translate }} <input name="packTo" type="email" [(ngModel)]="packTo" required /></label>
          <label>{{ 'Accountants.Subject' | translate }} <input name="packSubject" [(ngModel)]="packSubject" maxlength="255" required /></label>
          <label>{{ 'Accountants.Message' | translate }} <textarea name="packContent" [(ngModel)]="packContent" rows="7" maxlength="10000" required></textarea></label>
          <div class="actions">
            <button type="submit" class="primary-button" [disabled]="busy">{{ 'Accountants.SendPackNow' | translate }}</button>
            <button type="button" class="secondary-button" (click)="packFor = null">{{ 'Accountants.Cancel' | translate }}</button>
          </div>
        </form>
      }

      <div class="panel">
        <div class="panel-title">{{ 'Accountants.Accountants' | translate }}</div>
        <table>
          <thead>
            <tr>
              <th>{{ 'Accountants.Name' | translate }}</th><th>{{ 'Accountants.Firm' | translate }}</th><th>{{ 'Accountants.Email' | translate }}</th><th>{{ 'Accountants.Access' | translate }}</th><th>{{ 'Accountants.LastInvited' | translate }}</th><th>{{ 'Accountants.PackSent' | translate }}</th><th></th>
            </tr>
          </thead>
          <tbody>
            @for (item of accountants; track item.id) {
              <tr>
                <td>{{ item.name }}</td>
                <td>{{ item.firm || '—' }}</td>
                <td>{{ item.email }}</td>
                <td>{{ scopeSummary(item) }}</td>
                <td>{{ item.last_invited_at ? (item.last_invited_at | date: 'mediumDate') : '—' }}</td>
                <td>{{ item.last_pack_sent_at ? (item.last_pack_sent_at | date: 'mediumDate') : '—' }}</td>
                <td class="row-actions">
                  <button type="button" class="secondary-button" (click)="startPack(item)">{{ 'Accountants.SendPack' | translate }}</button>
                  <button type="button" class="secondary-button" (click)="startEdit(item)">{{ 'Accountants.Edit' | translate }}</button>
                  <button type="button" class="secondary-button" (click)="resetPassword(item)">{{ 'Accountants.ResetPassword' | translate }}</button>
                  <button type="button" class="danger-button" (click)="revoke(item)">{{ 'Accountants.Revoke' | translate }}</button>
                </td>
              </tr>
            } @empty {
              <tr><td colspan="7" class="empty">{{ 'Accountants.Empty' | translate }}</td></tr>
            }
          </tbody>
        </table>
      </div>
    </section>
  `,
  styles: [`
    .page { display: grid; gap: 16px; width: min(100%, var(--tv-content-max-width)); margin: 0 auto; padding: 40px 40px 64px; }
    .primary-button { padding: 9px 16px; border: 1px solid var(--tv-color-primary); border-radius: 4px; background: var(--tv-color-primary); color: #fff; font-weight: 700; cursor: pointer; white-space: nowrap; transition: background-color 140ms ease, border-color 140ms ease, box-shadow 140ms ease; }
    .primary-button:hover:not(:disabled) { border-color: var(--tv-color-primary-hover); background: var(--tv-color-primary-hover); box-shadow: 0 2px 5px rgba(15, 61, 120, 0.2); }
    .primary-button:disabled { opacity: 0.6; }
    .row-actions button, .actions button:not(.primary-button), .notice button { padding: 5px 10px; border: 1px solid #c3cddb; background: #fff; border-radius: 4px; cursor: pointer; }
    .secondary-button:hover:not(:disabled) { border-color: var(--tv-color-primary); background: var(--tv-color-secondary-hover); color: var(--tv-color-primary-dark); }
    .danger-button:hover:not(:disabled) { border-color: var(--tv-color-danger); background: var(--tv-color-danger-soft); }
    .head { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
    .head h1 { margin: 0; }
    .head p { margin: 4px 0 0; color: #5b6b7f; }
    .panel { background: #fff; border: 1px solid #d9e0ea; border-radius: 6px; overflow: hidden; }
    .panel-title { background: var(--tv-color-surface); color: var(--tv-color-text); border-bottom: 1px solid var(--tv-color-border); padding: 14px 16px; font-weight: 650; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e6ebf2; vertical-align: middle; }
    thead, tbody tr:nth-child(even) { background: var(--tv-color-table-stripe); }
    .row-actions { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
    .row-actions .danger { color: #b42318; }
    .empty { text-align: center; color: #5b6b7f; }
    .form { padding: 16px; display: grid; gap: 12px; }
    .form h2 { margin: 0; }
    .form label { display: grid; gap: 4px; font-weight: 600; }
    .form input:not([type='checkbox']), .form select, .form textarea { padding: 8px; border: 1px solid #c3cddb; border-radius: 4px; font: inherit; }
    .form .check { display: flex; align-items: center; gap: 8px; font-weight: 400; }
    fieldset { border: 1px solid #e6ebf2; border-radius: 4px; display: grid; gap: 6px; }
    .actions { display: flex; gap: 8px; }
    .notice { padding: 10px 14px; border-radius: 6px; background: #e8f0fb; margin: 0; }
    .notice.error { background: #fde8e6; color: #b42318; }
    .notice.password { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; background: #fff7e0; }
    .notice code { font-size: 15px; padding: 2px 8px; background: #fff; border-radius: 4px; }
    .pack-preview { display: grid; gap: 6px; padding: 12px; border: 1px solid #c9d8ec; border-radius: 5px; background: #f4f8ff; }
    .pack-preview > div { display: flex; justify-content: space-between; gap: 12px; }
    .pack-preview span, .pack-preview p { color: #5b6b7f; font-size: 12px; }
    .pack-preview strong { color: var(--tv-color-primary); font-size: 20px; }
    .pack-preview .pack-warning { color: #9b5f00; font-weight: 700; }
    @media (max-width: 900px) { table { display: block; overflow-x: auto; } }
    @media (max-width: 700px) { .page { padding: 28px 16px 48px; } }
  `],
})
export class AccountantsComponent implements OnInit {
  private readonly api = inject(AccountantsApiService);
  private readonly documents = inject(DocumentApiService);
  private readonly cdr = inject(ChangeDetectorRef);
  private readonly workspaceApi = inject(TaxWorkspaceApiService);
  private readonly i18n = inject(TranslationService);

  readonly sectionKeys = Object.keys(SECTION_LABEL_KEYS) as AccessSection[];
  readonly sectionLabels = SECTION_LABEL_KEYS;

  accountants: AccountantDto[] = [];
  financialYears: FinancialYearDto[] = [];
  form: AccountantForm | null = null;
  editingId: string | null = null;
  packFor: AccountantDto | null = null;
  packYear = '';
  packNote = '';
  packFrom = '';
  packFromName = 'TaxVault';
  packTo = '';
  packSubject = '';
  packContent = '';
  packReadiness: TaxReadinessDto | null = null;
  temporaryPassword = '';
  temporaryFor = '';
  message = '';
  error = '';
  busy = false;

  ngOnInit(): void {
    this.reload();
    this.documents.listAllFinancialYears().subscribe((years) => {
      this.financialYears = years;
      this.cdr.markForCheck();
    });
  }

  scopeSummary(item: AccountantDto): string {
    const sections = item.sections.map((s) => this.i18n.translate(SECTION_LABEL_KEYS[s])).join(', ');
    const years = item.financial_year_ids === null
      ? 'all years'
      : this.financialYears
          .filter((y) => item.financial_year_ids?.includes(y.id))
          .map((y) => y.year_code)
          .join(', ') || 'selected years';

    return `${sections} · ${years}`;
  }

  packYears(item: AccountantDto): FinancialYearDto[] {
    return item.financial_year_ids === null
      ? this.financialYears
      : this.financialYears.filter((y) => item.financial_year_ids?.includes(y.id));
  }

  sectionLabel(section: AccessSection): string {
    return SECTION_LABEL_KEYS[section];
  }

  startCreate(): void {
    this.reset();
    this.editingId = null;
    this.form = {
      name: '',
      email: '',
      firm: '',
      sections: ['documents', 'transactions', 'reviews'],
      allYears: true,
      yearIds: [],
    };
  }

  startEdit(item: AccountantDto): void {
    this.reset();
    this.editingId = item.id;
    this.form = {
      name: item.name,
      email: item.email,
      firm: item.firm ?? '',
      sections: [...item.sections],
      allYears: item.financial_year_ids === null,
      yearIds: [...(item.financial_year_ids ?? [])],
    };
  }

  startPack(item: AccountantDto): void {
    this.reset();
    this.packFor = item;
    this.packYear = this.packYears(item)[0]?.id ?? '';
    this.packNote = '';
    this.packFrom = '';
    this.packFromName = 'TaxVault';
    this.packTo = item.email;
    this.packSubject = 'Accountant pack from TaxVault';
    this.packContent = 'Please find the accountant pack attached. You can also open TaxVault using the link below.';
    this.loadPackPreview();
  }

  loadPackPreview(): void {
    if (!this.packFor) return;
    const yearId = this.packYear || this.packYears(this.packFor)[0]?.id;
    this.packReadiness = null;
    if (!yearId) return;
    this.workspaceApi.getTaxReadiness(yearId).subscribe({
      next: (readiness) => { this.packReadiness = readiness; this.cdr.markForCheck(); },
      error: () => { this.packReadiness = null; this.cdr.markForCheck(); },
    });
  }

  toggleSection(section: AccessSection): void {
    if (this.form) this.form.sections = this.toggle(this.form.sections, section);
  }

  toggleYear(id: string): void {
    if (this.form) this.form.yearIds = this.toggle(this.form.yearIds, id);
  }

  save(): void {
    if (!this.form || this.busy) return;
    if (this.form.sections.length === 0) {
      this.error = 'Choose at least one section.';
      return;
    }
    if (!this.form.allYears && this.form.yearIds.length === 0) {
      this.error = 'Choose at least one financial year, or all years.';
      return;
    }
    this.error = '';

    const payload: AccountantRequest = {
      name: this.form.name,
      firm: this.form.firm || null,
      sections: this.form.sections,
      financial_year_ids: this.form.allYears ? null : this.form.yearIds,
    };
    const editing = this.editingId;
    if (!editing) payload.email = this.form.email;

    this.busy = true;
    const request = editing ? this.api.update(editing, payload) : this.api.create(payload);
    request.subscribe({
      next: (item) => {
        this.busy = false;
        this.form = null;
        if (item.temporary_password) {
          this.temporaryPassword = item.temporary_password;
          this.temporaryFor = item.email;
        }
        this.message = editing
          ? 'Access updated.'
          : item.invitation_sent === false
            ? 'Accountant added, but the invitation email could not be sent. Check the mail server settings before retrying.'
            : 'Accountant added and invitation sent.';
        this.reload();
      },
      error: (e) => this.fail(e, 'Unable to save the accountant.'),
    });
  }

  sendPack(): void {
    if (!this.packFor || this.busy) return;
    const target = this.packFor;
    this.busy = true;
    this.api
      .sendPack(target.id, {
        financial_year_id: this.packYear || null,
        note: this.packNote || null,
        to: this.packTo,
        from_email: this.packFrom,
        from_name: this.packFromName,
        subject: this.packSubject,
        content: this.packContent,
      })
      .subscribe({
        next: () => {
          this.busy = false;
          this.message = `Accountant pack sent to ${target.email}.`;
          this.packFor = null;
          this.reload();
        },
        error: (e) => this.fail(e, 'Unable to send the pack.'),
      });
  }

  resetPassword(item: AccountantDto): void {
    if (!confirm(`Reset the password for ${item.email}?`)) return;
    this.reset();
    this.api.resetPassword(item.id).subscribe({
      next: (password) => {
        this.temporaryPassword = password;
        this.temporaryFor = item.email;
        this.cdr.markForCheck();
      },
      error: (e) => this.fail(e, 'Unable to reset the password.'),
    });
  }

  revoke(item: AccountantDto): void {
    if (!confirm(`Revoke ${item.name}'s access?`)) return;
    this.reset();
    this.api.revoke(item.id).subscribe({
      next: () => {
        this.message = 'Access revoked.';
        this.reload();
      },
      error: (e) => this.fail(e, 'Unable to revoke access.'),
    });
  }

  copyPassword(): void {
    void navigator.clipboard?.writeText(this.temporaryPassword);
  }

  private reload(): void {
    this.api.list().subscribe({
      next: (items) => {
        this.accountants = items;
        this.cdr.markForCheck();
      },
      error: (e) => this.fail(e, 'Unable to load accountants.'),
    });
    this.cdr.markForCheck();
  }

  private reset(): void {
    this.message = '';
    this.error = '';
    this.form = null;
    this.packFor = null;
    this.packReadiness = null;
  }

  private fail(e: { error?: { message?: string } }, fallback: string): void {
    this.busy = false;
    this.error = e?.error?.message || fallback;
    this.cdr.markForCheck();
  }

  private toggle<T>(list: T[], value: T): T[] {
    return list.includes(value) ? list.filter((v) => v !== value) : [...list, value];
  }
}
