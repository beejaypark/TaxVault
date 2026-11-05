import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  OnInit,
  inject,
} from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';

import { TaxIconComponent } from '../tax-icon/tax-icon.component';
import { BrandLogoComponent } from '../brand-logo/brand-logo.component';
import { GlobalSearchComponent } from '../global-search/global-search.component';
import { NotificationsComponent } from '../notifications/notifications.component';
import { UserPreferencesService } from '../../services/user-preferences.service';
import { formatFinancialYear } from '../../utils/financial-year';
import { AuthService } from '../../services/auth/auth.service';
import { NavigationHistoryService } from '../../services/navigation-history.service';
import {
  DocumentApiService,
  FinancialYearDto,
} from '../../services/documents/document-api.service';

@Component({
  selector: 'app-shell',
  imports: [FormsModule, RouterLink, RouterLinkActive, RouterOutlet, TaxIconComponent, BrandLogoComponent, GlobalSearchComponent, NotificationsComponent],
  templateUrl: './app-shell.component.html',
  styleUrl: './app-shell.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AppShellComponent implements OnInit {
  private readonly documentsApi = inject(DocumentApiService);
  readonly navigationHistory = inject(NavigationHistoryService);
  private readonly cdr = inject(ChangeDetectorRef);
  private readonly preferences = inject(UserPreferencesService);

  logoutError = false;
  menuOpen = false;
  userMenuOpen = false;
  selectedFinancialYearId = '';

  get userInitial(): string {
    const name = this.auth.currentUser?.display_name || this.auth.currentUser?.email || '?';
    return name.trim().charAt(0);
  }

  toggleMenu(): void {
    this.menuOpen = !this.menuOpen;
  }

  closeMenu(): void {
    this.menuOpen = false;
  }

  toggleUserMenu(): void {
    this.userMenuOpen = !this.userMenuOpen;
  }
  isLoadingFinancialYears = true;

  get financialYears(): FinancialYearDto[] {
    return this.documentsApi.financialYears().filter(
      (year) => year.status === 'active',
    );
  }

  formatYearCode(yearCode: string): string {
    return formatFinancialYear(yearCode);
  }

  constructor(
    public readonly auth: AuthService,
    private readonly router: Router,
  ) {}

  ngOnInit(): void {
    this.documentsApi.listFinancialYears().subscribe({
      next: (years) => {
        const selectedYearId = this.documentsApi.selectedFinancialYearId();
        const prefersAll = this.preferences.preferences().defaultFinancialYear === 'all';
        this.selectedFinancialYearId = years.some(
          (year) => year.id === selectedYearId,
        )
          ? years.find((year) => year.id === selectedYearId)?.id ?? ''
          : prefersAll ? '' : this.documentsApi.defaultFinancialYearId(years) ?? '';
        this.documentsApi.selectFinancialYear(
          this.selectedFinancialYearId || null,
        );
        this.isLoadingFinancialYears = false;
        this.cdr.markForCheck();
      },
      error: () => {
        this.isLoadingFinancialYears = false;
        this.cdr.markForCheck();
      },
    });
  }

  onFinancialYearChange(financialYearId: string): void {
    this.selectedFinancialYearId = financialYearId;
    this.documentsApi.selectFinancialYear(financialYearId || null);
  }

  logout(): void {
    this.logoutError = false;

    this.auth.logout().subscribe({
      next: (logoutUrl) => {
        if (logoutUrl) {
          window.location.assign(logoutUrl);
          return;
        }

        void this.router.navigate(['/login']);
      },
      error: () => {
        this.logoutError = true;
      },
    });
  }
}
