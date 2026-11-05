import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  ElementRef,
  HostListener,
  ViewChild,
  inject,
} from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { catchError, forkJoin, of } from 'rxjs';

import { TaxIconComponent } from '../tax-icon/tax-icon.component';
import { DocumentApiService } from '../../services/documents/document-api.service';
import { TaxWorkspaceApiService } from '../../services/tax-workspace-api.service';
import { formatFinancialYear } from '../../utils/financial-year';
import { SEARCH_PAGES, SearchResult, searchResults } from '../../utils/global-search';

const CACHE_MS = 30_000;

@Component({
  selector: 'app-global-search',
  imports: [FormsModule, TaxIconComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './global-search.component.html',
  styleUrl: './global-search.component.scss',
})
export class GlobalSearchComponent {
  private readonly documentsApi = inject(DocumentApiService);
  private readonly api = inject(TaxWorkspaceApiService);
  private readonly router = inject(Router);
  private readonly cdr = inject(ChangeDetectorRef);

  @ViewChild('searchInput') searchInput?: ElementRef<HTMLInputElement>;

  isOpen = false;
  isLoading = false;
  query = '';
  activeIndex = 0;
  results: SearchResult[] = [];
  readonly isMac = typeof navigator !== 'undefined' && /mac/i.test(navigator.platform);

  private candidates: SearchResult[] = [...SEARCH_PAGES];
  private loadedAt = 0;

  @HostListener('document:keydown', ['$event'])
  onKeydown(event: KeyboardEvent): void {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault();
      this.open();
    } else if (event.key === 'Escape' && this.isOpen) {
      this.close();
    }
  }

  open(): void {
    this.isOpen = true;
    this.query = '';
    this.results = [];
    this.activeIndex = 0;
    this.loadIfStale();
    this.cdr.markForCheck();
    setTimeout(() => this.searchInput?.nativeElement.focus());
  }

  close(): void {
    this.isOpen = false;
    this.cdr.markForCheck();
  }

  onQueryChange(): void {
    this.results = searchResults(this.query, this.candidates);
    this.activeIndex = 0;
  }

  onInputKeydown(event: KeyboardEvent): void {
    if (event.key === 'ArrowDown' && this.results.length > 0) {
      event.preventDefault();
      this.activeIndex = (this.activeIndex + 1) % this.results.length;
    } else if (event.key === 'ArrowUp' && this.results.length > 0) {
      event.preventDefault();
      this.activeIndex = (this.activeIndex - 1 + this.results.length) % this.results.length;
    } else if (event.key === 'Enter' && this.results[this.activeIndex]) {
      event.preventDefault();
      this.select(this.results[this.activeIndex]);
    }
  }

  select(result: SearchResult): void {
    this.close();
    void this.router.navigate([result.link], { queryParams: result.queryParams });
  }

  private loadIfStale(): void {
    if (this.isLoading || Date.now() - this.loadedAt < CACHE_MS) {
      return;
    }

    this.isLoading = true;

    forkJoin({
      documents: this.documentsApi.listDocuments().pipe(catchError(() => of([]))),
      types: this.documentsApi.listDocumentTypes().pipe(catchError(() => of([]))),
      years: this.documentsApi.listAllFinancialYears().pipe(catchError(() => of([]))),
      transactions: this.api.listTransactions().pipe(catchError(() => of([]))),
      properties: this.api.listProperties().pipe(catchError(() => of([]))),
      investments: this.api.listInvestments().pipe(catchError(() => of([]))),
    }).subscribe(({ documents, types, years, transactions, properties, investments }) => {
      const typeName = (id: string) => types.find((type) => type.id === id)?.name ?? 'Document';
      const yearLabel = (id: string | null) => {
        const year = years.find((item) => item.id === id);

        return year ? `FY ${formatFinancialYear(year.year_code)}` : 'No financial year';
      };
      const money = (value: string | number) =>
        new Intl.NumberFormat('en-AU', { style: 'currency', currency: 'AUD' }).format(Number(value));

      this.candidates = [
        ...SEARCH_PAGES,
        ...years.map((year): SearchResult => ({
          kind: 'Financial year',
          title: `FY ${formatFinancialYear(year.year_code)}`,
          subtitle: `${year.start_date.slice(0, 10)} to ${year.end_date.slice(0, 10)}`,
          link: '/financial-years',
        })),
        ...documents.map((document): SearchResult => ({
          kind: 'Document',
          title: document.original_filename,
          subtitle: `${typeName(document.document_type_id)} · ${yearLabel(document.financial_year_id)}`,
          link: '/documents',
          queryParams: { q: document.original_filename },
        })),
        ...transactions.map((transaction): SearchResult => ({
          kind: 'Transaction',
          title: transaction.description,
          subtitle: `${transaction.transaction_date.slice(0, 10)} · ${transaction.direction} ${money(transaction.amount)}`,
          link: '/transactions',
          queryParams: { q: transaction.description },
        })),
        ...properties.map((property): SearchResult => ({
          kind: 'Property',
          title: property.reference_code || property.address_line_1 || 'Property',
          subtitle: [property.address_line_1, property.suburb, property.state, property.postcode]
            .filter(Boolean)
            .join(', '),
          link: '/properties',
        })),
        ...investments.map((investment): SearchResult => ({
          kind: 'Investment',
          title: investment.investment_type,
          subtitle: `Acquired ${investment.acquisition_date.slice(0, 10)}`,
          link: '/investments',
        })),
      ];
      this.loadedAt = Date.now();
      this.isLoading = false;
      this.onQueryChange();
      this.cdr.markForCheck();
    });
  }
}
