import { Routes } from '@angular/router';

import { AppShellComponent } from './shared/components/app-shell/app-shell.component';
import { authGuard, ownerGuard } from './shared/guards/auth.guard';
import { AccountantsComponent } from './features/accountants/accountants.component';
import { LoginComponent } from './features/auth/pages/login/login.component';
import { DashboardComponent } from './features/dashboard/pages/dashboard/dashboard.component';
import { DocumentListComponent } from './features/documents/pages/document-list/document-list.component';
import { UploadDocumentComponent } from './features/documents/pages/upload-document/upload-document.component';
import { AssetsComponent } from './features/assets/assets.component';
import { TaxReturnComponent } from './features/tax-return/tax-return.component';
import { TransactionsComponent } from './features/transactions/transactions.component';
import { FinancialYearsComponent } from './features/financial-years/financial-years.component';
import { SettingsComponent } from './features/settings/settings.component';

export const routes: Routes = [
  {
    path: '',
    pathMatch: 'full',
    redirectTo: 'login',
  },
  {
    path: 'login',
    component: LoginComponent,
  },
  {
    path: '',
    component: AppShellComponent,
    canActivateChild: [authGuard],
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'dashboard' },
      { path: 'dashboard', component: DashboardComponent },
      { path: 'documents', component: DocumentListComponent },
      { path: 'documents/upload', component: UploadDocumentComponent, canActivate: [ownerGuard] },
      { path: 'properties', component: AssetsComponent },
      { path: 'investments', component: AssetsComponent },
      { path: 'transactions', component: TransactionsComponent },
      { path: 'financial-years', component: FinancialYearsComponent },
      {
        path: 'reviews',
        loadComponent: () =>
          import('./features/reviews/pages/review-list/review-list.component').then(
            (module) => module.ReviewListComponent,
          ),
      },
      {
        path: 'reviews/:id',
        loadComponent: () =>
          import('./features/reviews/pages/review-detail/review-detail.component').then(
            (module) => module.ReviewDetailComponent,
          ),
      },
      { path: 'tax-return', component: TaxReturnComponent },
      { path: 'accountants', component: AccountantsComponent, canActivate: [ownerGuard] },
      { path: 'settings', component: SettingsComponent },
    ],
  },
  { path: '**', redirectTo: 'dashboard' },
];
