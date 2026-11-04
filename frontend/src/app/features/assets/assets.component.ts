import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  OnInit,
  inject,
} from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { forkJoin } from 'rxjs';

import { TaxIconComponent } from '../../shared/components/tax-icon/tax-icon.component';
import { BackNavigationComponent } from '../../shared/components/back-navigation/back-navigation.component';
import {
  InvestmentDto,
  PropertyDto,
  TaxWorkspaceApiService,
} from '../../shared/services/tax-workspace-api.service';

import { AuthService } from '../../shared/services/auth/auth.service';

@Component({
  selector: 'app-assets',
  imports: [FormsModule, RouterLink, TaxIconComponent, BackNavigationComponent],
  templateUrl: './assets.component.html',
  styleUrl: './assets.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AssetsComponent implements OnInit {
  private readonly api = inject(TaxWorkspaceApiService);
  private readonly route = inject(ActivatedRoute);
  private readonly cdr = inject(ChangeDetectorRef);
  readonly auth = inject(AuthService);

  readonly states = ['ACT', 'NSW', 'NT', 'QLD', 'SA', 'TAS', 'VIC', 'WA'];
  readonly investmentTypes = [
    { value: 'shares', label: 'Shares' },
    { value: 'managed_fund', label: 'Managed fund' },
    { value: 'cryptocurrency', label: 'Cryptocurrency' },
    { value: 'other', label: 'Other investment' },
  ];

  properties: PropertyDto[] = [];
  investments: InvestmentDto[] = [];
  isLoading = true;
  isSaving = false;
  showPropertyForm = false;
  showInvestmentForm = false;
  editingPropertyId: string | null = null;
  editingInvestmentId: string | null = null;
  errorMessage = '';
  successMessage = '';

  propertyForm = this.emptyPropertyForm();
  investmentForm = this.emptyInvestmentForm();

  get activeTab(): 'properties' | 'investments' {
    return this.route.snapshot.routeConfig?.path === 'investments'
      ? 'investments'
      : 'properties';
  }

  ngOnInit(): void {
    forkJoin({
      properties: this.api.listProperties(),
      investments: this.api.listInvestments(),
    }).subscribe({
      next: (data) => {
        this.properties = data.properties;
        this.investments = data.investments;
        this.isLoading = false;
        this.cdr.markForCheck();
      },
      error: () => {
        this.errorMessage = 'Unable to load your asset records. Please try again.';
        this.isLoading = false;
        this.cdr.markForCheck();
      },
    });
  }

  saveProperty(): void {
    if (this.isSaving || !this.propertyForm.address_line_1.trim()) {
      return;
    }

    this.isSaving = true;
    this.errorMessage = '';
    this.successMessage = '';

    const editing = this.editingPropertyId;
    const trimmed = (value: string) => value.trim();
    const payload = {
      ...this.propertyForm,
      reference_code: editing ? trimmed(this.propertyForm.reference_code) : this.optional(this.propertyForm.reference_code),
      address_line_1: trimmed(this.propertyForm.address_line_1),
      address_line_2: editing ? trimmed(this.propertyForm.address_line_2) : this.optional(this.propertyForm.address_line_2),
      suburb: editing ? trimmed(this.propertyForm.suburb) : this.optional(this.propertyForm.suburb),
      state: editing ? trimmed(this.propertyForm.state) : this.optional(this.propertyForm.state),
      postcode: editing ? trimmed(this.propertyForm.postcode) : this.optional(this.propertyForm.postcode),
    };
    const request$ = editing
      ? this.api.updateProperty(editing, payload)
      : this.api.createProperty(payload);

    request$.subscribe({
      next: (property) => {
        this.properties = editing
          ? this.properties.map((item) => (item.id === property.id ? property : item))
          : [property, ...this.properties];
        this.closePropertyForm();
        this.successMessage = editing ? 'Property updated.' : 'Property added.';
        this.isSaving = false;
        this.cdr.markForCheck();
      },
      error: () => this.handleSaveError(),
    });
  }

  saveInvestment(): void {
    if (this.isSaving || !this.investmentForm.acquisition_date) {
      return;
    }

    this.isSaving = true;
    this.errorMessage = '';
    this.successMessage = '';

    const editing = this.editingInvestmentId;
    const payload = {
      property_id: this.optional(this.investmentForm.property_id) ?? null,
      investment_type: this.investmentForm.investment_type,
      acquisition_date: this.investmentForm.acquisition_date,
      cost_base: this.investmentForm.cost_base,
      incidental_costs: this.investmentForm.incidental_costs,
      proceeds: this.investmentForm.proceeds,
      quantity: this.investmentForm.quantity,
      ownership_percentage: this.investmentForm.ownership_percentage,
    };
    const request$ = editing
      ? this.api.updateInvestment(editing, payload)
      : this.api.createInvestment(payload);

    request$.subscribe({
      next: (investment) => {
        this.investments = editing
          ? this.investments.map((item) => (item.id === investment.id ? investment : item))
          : [investment, ...this.investments];
        this.closeInvestmentForm();
        this.successMessage = editing ? 'Investment updated.' : 'Investment added.';
        this.isSaving = false;
        this.cdr.markForCheck();
      },
      error: () => this.handleSaveError(),
    });
  }

  startAddProperty(): void {
    this.closePropertyForm();
    this.showPropertyForm = true;
  }

  startEditProperty(property: PropertyDto): void {
    this.errorMessage = '';
    this.successMessage = '';
    this.editingPropertyId = property.id;
    this.propertyForm = {
      reference_code: property.reference_code ?? '',
      address_line_1: property.address_line_1 ?? '',
      address_line_2: property.address_line_2 ?? '',
      suburb: property.suburb ?? '',
      state: property.state ?? '',
      postcode: property.postcode ?? '',
      country_code: property.country_code,
    };
    this.showPropertyForm = true;
  }

  closePropertyForm(): void {
    this.showPropertyForm = false;
    this.editingPropertyId = null;
    this.propertyForm = this.emptyPropertyForm();
  }

  deleteProperty(property: PropertyDto): void {
    const name = property.reference_code || property.address_line_1 || 'this property';

    if (!confirm(`Delete ${name}? This cannot be undone.`)) {
      return;
    }

    this.errorMessage = '';
    this.successMessage = '';

    this.api.deleteProperty(property.id).subscribe({
      next: () => {
        this.properties = this.properties.filter((item) => item.id !== property.id);
        this.successMessage = 'Property deleted.';
        this.cdr.markForCheck();
      },
      error: (error: { error?: { message?: string } }) => {
        this.errorMessage = error.error?.message ?? 'Unable to delete this property.';
        this.cdr.markForCheck();
      },
    });
  }

  startAddInvestment(): void {
    this.closeInvestmentForm();
    this.showInvestmentForm = true;
  }

  startEditInvestment(investment: InvestmentDto): void {
    const num = (value: string | number | null) => (value === null ? null : Number(value));

    this.errorMessage = '';
    this.successMessage = '';
    this.editingInvestmentId = investment.id;
    this.investmentForm = {
      property_id: investment.property_id ?? '',
      investment_type: investment.investment_type,
      acquisition_date: investment.acquisition_date.slice(0, 10),
      cost_base: num(investment.cost_base),
      incidental_costs: num(investment.incidental_costs),
      proceeds: num(investment.proceeds),
      quantity: num(investment.quantity),
      ownership_percentage: num(investment.ownership_percentage),
    };
    this.showInvestmentForm = true;
  }

  closeInvestmentForm(): void {
    this.showInvestmentForm = false;
    this.editingInvestmentId = null;
    this.investmentForm = this.emptyInvestmentForm();
  }

  deleteInvestment(investment: InvestmentDto): void {
    if (!confirm('Delete this investment? This cannot be undone.')) {
      return;
    }

    this.errorMessage = '';
    this.successMessage = '';

    this.api.deleteInvestment(investment.id).subscribe({
      next: () => {
        this.investments = this.investments.filter((item) => item.id !== investment.id);
        this.successMessage = 'Investment deleted.';
        this.cdr.markForCheck();
      },
      error: () => {
        this.errorMessage = 'Unable to delete this investment.';
        this.cdr.markForCheck();
      },
    });
  }

  propertyAddress(property: PropertyDto): string {
    return [
      property.address_line_1,
      property.address_line_2,
      property.suburb,
      property.state,
      property.postcode,
    ].filter(Boolean).join(', ');
  }

  formatDate(value: string): string {
    const date = new Date(value);

    return Number.isNaN(date.getTime())
      ? 'Date unavailable'
      : new Intl.DateTimeFormat('en-AU', {
          day: 'numeric',
          month: 'short',
          year: 'numeric',
        }).format(date);
  }

  formatCurrency(value: string | number | null): string {
    const amount = Number(value);

    return Number.isFinite(amount)
      ? new Intl.NumberFormat('en-AU', {
          style: 'currency',
          currency: 'AUD',
        }).format(amount)
      : '—';
  }

  private handleSaveError(): void {
    this.errorMessage = 'Unable to save this record. Check the details and try again.';
    this.isSaving = false;
    this.cdr.markForCheck();
  }

  private optional(value: string): string | undefined {
    const trimmed = value.trim();
    return trimmed ? trimmed : undefined;
  }

  private emptyPropertyForm() {
    return {
      reference_code: '',
      address_line_1: '',
      address_line_2: '',
      suburb: '',
      state: '',
      postcode: '',
      country_code: 'AU',
    };
  }

  private emptyInvestmentForm() {
    return {
      property_id: '',
      investment_type: 'shares',
      acquisition_date: '',
      cost_base: null as number | null,
      incidental_costs: null as number | null,
      proceeds: null as number | null,
      quantity: null as number | null,
      ownership_percentage: null as number | null,
    };
  }
}
