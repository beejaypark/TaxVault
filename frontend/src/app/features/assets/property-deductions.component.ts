import { ChangeDetectionStrategy, ChangeDetectorRef, Component, inject, input } from '@angular/core';
import { FormsModule } from '@angular/forms';

import { PropertyDeductionDto, PropertyDeductionRequest, PropertyDto, TaxWorkspaceApiService } from '../../shared/services/tax-workspace-api.service';
import { DocumentApiService, DocumentDto } from '../../shared/services/documents/document-api.service';
import { AuthService } from '../../shared/services/auth/auth.service';
import { TranslatePipe } from '../../shared/i18n/translate.pipe';

@Component({
  selector: 'app-property-deductions',
  imports: [FormsModule, TranslatePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <section class="deductions" aria-labelledby="property-deductions-heading">
      <div class="deductions-head">
        <div>
          <h3 id="property-deductions-heading">{{ 'Assets.PropertyDeductions' | translate }}</h3>
          <p>{{ 'Assets.PropertyDeductionsDescription' | translate }}</p>
        </div>
      </div>

      @if ((property().deductions ?? []).length > 0) {
        <div class="deduction-list">
          @for (deduction of property().deductions; track deduction.id) {
            <div class="deduction-row">
              <div>
                <strong>{{ label(deduction.deduction_type) }}</strong>
                <span>{{ yearLabel(deduction) }}{{ deduction.notes ? ' · ' + deduction.notes : '' }}</span>
              </div>
              <strong>{{ money(deduction.amount) }}</strong>
              @if (auth.canEdit) { <button type="button" class="delete" (click)="remove(deduction)">{{ 'Assets.Delete' | translate }}</button> }
            </div>
          }
        </div>
      } @else {
        <p class="empty">{{ 'Assets.NoPropertyDeductions' | translate }}</p>
      }

      @if (auth.canEdit) {
        <form class="deduction-form" (ngSubmit)="save()">
          <select name="financialYear" [(ngModel)]="form.financial_year_id" required>
            <option value="">{{ 'Assets.SelectFinancialYear' | translate }}</option>
            @for (year of documents.financialYears(); track year.id) { <option [value]="year.id">FY {{ year.year_code }}</option> }
          </select>
          <select name="deductionType" [(ngModel)]="form.deduction_type" required>
            <option value="capital_works">{{ 'Assets.CapitalWorks' | translate }}</option>
            <option value="plant_equipment">{{ 'Assets.PlantEquipment' | translate }}</option>
          </select>
          <input name="amount" type="number" min="0" step="0.01" [(ngModel)]="form.amount" [placeholder]="'Assets.Amount' | translate" required />
          <input name="notes" [(ngModel)]="form.notes" [placeholder]="'Assets.NotesOptional' | translate" maxlength="2000" />
          <select name="sourceDocument" [(ngModel)]="form.source_document_id">
            <option [ngValue]="null">{{ 'Assets.SourceDocumentOptional' | translate }}</option>
            @for (document of availableDocuments; track document.id) { <option [ngValue]="document.id">{{ document.original_filename }}</option> }
          </select>
          <button type="submit" class="secondary-button" [disabled]="saving || !form.financial_year_id || form.amount === null">{{ saving ? ('Assets.Saving' | translate) : ('Assets.SaveDeduction' | translate) }}</button>
        </form>
        @if (errorMessage) { <p class="error" role="alert">{{ errorMessage }}</p> }
      }
    </section>
  `,
  styles: [`
    :host { display: block; flex: 0 0 100%; min-width: 0; width: 100%; box-sizing: border-box; }
    .deductions { display: grid; gap: 10px; padding-top: 16px; border-top: 1px solid var(--tv-color-border); }
    .deductions-head { display: flex; justify-content: space-between; gap: 12px; }
    h3, p { margin: 0; } h3 { font-size: 14px; } .deductions-head p, .empty { color: var(--tv-color-text-secondary); font-size: 12px; }
    .deduction-list { display: grid; gap: 6px; }
    .deduction-row { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; gap: 10px; align-items: center; padding: 8px 10px; border: 1px solid var(--tv-color-border); border-radius: 6px; font-size: 12px; }
    .deduction-row span { display: block; margin-top: 3px; color: var(--tv-color-text-secondary); font-size: 11px; }
    .delete { border: 0; background: transparent; color: var(--tv-color-danger); cursor: pointer; font-size: 11px; }
    .deduction-form { display: grid; grid-template-columns: 1fr 1.2fr .8fr 1fr 1.2fr auto; gap: 8px; align-items: center; }
    input, select { min-width: 0; padding: 7px 8px; border: 1px solid var(--tv-color-border); border-radius: 6px; background: var(--tv-color-surface); font-size: 12px; }
    .error { color: var(--tv-color-danger); font-size: 12px; }
    @media (max-width: 800px) { .deduction-form { grid-template-columns: 1fr 1fr; } .deduction-form button { grid-column: span 2; } }
  `],
})
export class PropertyDeductionsComponent {
  readonly property = input.required<PropertyDto>();
  readonly auth = inject(AuthService);
  readonly documents = inject(DocumentApiService);
  private readonly api = inject(TaxWorkspaceApiService);
  private readonly cdr = inject(ChangeDetectorRef);

  saving = false;
  errorMessage = '';
  form: PropertyDeductionRequest = { financial_year_id: '', deduction_type: 'capital_works', amount: 0, notes: null };
  availableDocuments: DocumentDto[] = [];

  constructor() {
    this.documents.listDocuments().subscribe({ next: (documents) => { this.availableDocuments = documents; this.cdr.markForCheck(); } });
  }

  label(type: PropertyDeductionDto['deduction_type']): string {
    return type === 'capital_works' ? 'Capital works deduction' : 'Plant and equipment depreciation';
  }

  yearLabel(deduction: PropertyDeductionDto): string {
    return deduction.financial_year?.year_code ? `FY ${deduction.financial_year.year_code}` : deduction.financial_year_id;
  }

  money(value: string | number): string {
    return new Intl.NumberFormat('en-AU', { style: 'currency', currency: 'AUD' }).format(Number(value));
  }

  save(): void {
    if (this.saving || !this.form.financial_year_id || this.form.amount === null) return;
    this.saving = true;
    this.errorMessage = '';
    this.api.savePropertyDeduction(this.property().id, { ...this.form, amount: Number(this.form.amount) }).subscribe({
      next: (deduction) => {
        const existing = this.property().deductions ?? [];
        this.property().deductions = [...existing.filter((item) => item.id !== deduction.id && !(item.financial_year_id === deduction.financial_year_id && item.deduction_type === deduction.deduction_type)), deduction];
        this.form = { ...this.form, amount: 0, notes: null };
        this.saving = false;
        this.cdr.markForCheck();
      },
      error: () => { this.errorMessage = 'Unable to save the property deduction.'; this.saving = false; this.cdr.markForCheck(); },
    });
  }

  remove(deduction: PropertyDeductionDto): void {
    if (!confirm('Delete this property deduction?')) return;
    this.api.deletePropertyDeduction(this.property().id, deduction.id).subscribe({
      next: () => { this.property().deductions = (this.property().deductions ?? []).filter((item) => item.id !== deduction.id); this.cdr.markForCheck(); },
      error: () => { this.errorMessage = 'Unable to delete the property deduction.'; this.cdr.markForCheck(); },
    });
  }
}
