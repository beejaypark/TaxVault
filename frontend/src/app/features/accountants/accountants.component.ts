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

const SECTION_LABELS: Record<AccessSection, string> = {
  documents: 'Documents',
  transactions: 'Transactions',
  reviews: 'Reviews',
  assets: 'Properties & investments',
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
    <section class="page">
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
          <button type="button" (click)="copyPassword()">{{ 'Accountants.Copy' | translate }}</button>
          <button type="button" (click)="temporaryPassword = ''">{{ 'Accountants.Dismiss' | translate }}</button>
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
                {{ sectionLabels[section] }}
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
            <button type="button" (click)="form = null">{{ 'Accountants.Cancel' | translate }}</button>
          </div>
        </form>
      }

      @if (packFor) {
        <form class="panel form" (ngSubmit)="sendPack()">
          <h2>{{ 'Accountants.SendPackTo' | translate }} {{ packFor.name }}</h2>
          <label>
            {{ 'Accountants.FinancialYears' | translate }}
            <select name="packYear" [(ngModel)]="packYear">
              <option value="">{{ 'Accountants.LatestAvailable' | translate }}</option>
              @for (year of packYears(packFor); track year.id) {
                <option [value]="year.id">{{ year.year_code }}</option>
              }
            </select>
          </label>
          <label>{{ 'Accountants.Note' | translate }} <textarea name="packNote" [(ngModel)]="packNote" maxlength="1000"></textarea></label>
          <div class="actions">
            <button type="submit" class="primary-button" [disabled]="busy">{{ 'Accountants.SendEmail' | translate }}</button>
            <button type="button" (click)="packFor = null">{{ 'Accountants.Cancel' | translate }}</button>
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
                  <button type="button" (click)="startPack(item)">{{ 'Accountants.SendPack' | translate }}</button>
                  <button type="button" (click)="startEdit(item)">{{ 'Accountants.Edit' | translate }}</button>
                  <button type="button" (click)="resetPassword(item)">{{ 'Accountants.ResetPassword' | translate }}</button>
                  <button type="button" class="danger" (click)="revoke(item)">{{ 'Accountants.Revoke' | translate }}</button>
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
  styles: `
    .page { display: grid; gap: 16px; width: min(100%, var(--tv-content-max-width)); margin: 0 auto; padding: 40px 40px 64px; }
    .primary-button { padding: 9px 16px; border: 0; border-radius: 4px; background: #1b2f4e; color: #fff; font-weight: 700; cursor: pointer; white-space: nowrap; }
    .primary-button:disabled { opacity: 0.6; }
    .row-actions button, .actions button:not(.primary-button), .notice button { padding: 5px 10px; border: 1px solid #c3cddb; background: #fff; border-radius: 4px; cursor: pointer; }
    .head { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
    .head h1 { margin: 0; }
    .head p { margin: 4px 0 0; color: #5b6b7f; }
    .panel { background: #fff; border: 1px solid #d9e0ea; border-radius: 6px; overflow: hidden; }
    .panel-title { background: #1b2f4e; color: #fff; padding: 10px 16px; font-weight: 700; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e6ebf2; vertical-align: middle; }
    tbody tr:nth-child(even) { background: #f6f8fb; }
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
    @media (max-width: 900px) { table { display: block; overflow-x: auto; } }
    @media (max-width: 700px) { .page { padding: 28px 16px 48px; } }
  `,
})
export class AccountantsComponent implements OnInit {
  private readonly api = inject(AccountantsApiService);
  private readonly documents = inject(DocumentApiService);
  private readonly cdr = inject(ChangeDetectorRef);

  readonly sectionKeys = Object.keys(SECTION_LABELS) as AccessSection[];
  readonly sectionLabels = SECTION_LABELS;

  accountants: AccountantDto[] = [];
  financialYears: FinancialYearDto[] = [];
  form: AccountantForm | null = null;
  editingId: string | null = null;
  packFor: AccountantDto | null = null;
  packYear = '';
  packNote = '';
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
    const sections = item.sections.map((s) => SECTION_LABELS[s]).join(', ');
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
    this.packYear = '';
    this.packNote = '';
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
        this.message = editing ? 'Access updated.' : 'Accountant added and invitation sent.';
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
      .sendPack(target.id, { financial_year_id: this.packYear || null, note: this.packNote || null })
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
