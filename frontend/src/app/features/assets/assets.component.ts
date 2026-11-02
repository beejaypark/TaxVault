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
import {
  InvestmentDto,
  PropertyDto,
  TaxWorkspaceApiService,
} from '../../shared/services/tax-workspace-api.service';

@Component({
  selector: 'app-assets',
  imports: [FormsModule, RouterLink, TaxIconComponent],
  templateUrl: './assets.component.html',
  styleUrl: './assets.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AssetsComponent implements OnInit {
  private readonly api = inject(TaxWorkspaceApiService);
  private readonly route = inject(ActivatedRoute);
  private readonly cdr = inject(ChangeDetectorRef);

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

    this.api.createProperty({
      ...this.propertyForm,
      reference_code: this.optional(this.propertyForm.reference_code),
      address_line_1: this.propertyForm.address_line_1.trim(),
      address_line_2: this.optional(this.propertyForm.address_line_2),
      suburb: this.optional(this.propertyForm.suburb),
      state: this.optional(this.propertyForm.state),
      postcode: this.optional(this.propertyForm.postcode),
    }).subscribe({
      next: (property) => {
        this.properties = [property, ...this.properties];
        this.propertyForm = this.emptyPropertyForm();
        this.showPropertyForm = false;
        this.successMessage = 'Property added.';
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

    this.api.createInvestment({
      property_id: this.optional(this.investmentForm.property_id),
      investment_type: this.investmentForm.investment_type,
      acquisition_date: this.investmentForm.acquisition_date,
      cost_base: this.investmentForm.cost_base,
      incidental_costs: this.investmentForm.incidental_costs,
      proceeds: this.investmentForm.proceeds,
      quantity: this.investmentForm.quantity,
      ownership_percentage: this.investmentForm.ownership_percentage,
    }).subscribe({
      next: (investment) => {
        this.investments = [investment, ...this.investments];
        this.investmentForm = this.emptyInvestmentForm();
        this.showInvestmentForm = false;
        this.successMessage = 'Investment added.';
        this.isSaving = false;
        this.cdr.markForCheck();
      },
      error: () => this.handleSaveError(),
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
