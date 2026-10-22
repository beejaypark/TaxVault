import { Routes } from '@angular/router';

import { AppShellComponent } from './shared/components/app-shell/app-shell.component';
import { authGuard } from './shared/guards/auth.guard';
import { LoginComponent } from './features/auth/pages/login/login.component';
import { DashboardComponent } from './features/dashboard/pages/dashboard/dashboard.component';
import { DocumentListComponent } from './features/documents/pages/document-list/document-list.component';
import { UploadDocumentComponent } from './features/documents/pages/upload-document/upload-document.component';
import { ReviewListComponent } from './features/reviews/pages/review-list/review-list.component';
import { ReviewDetailComponent } from './features/reviews/pages/review-detail/review-detail.component';

export const routes: Routes = [
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
      { path: 'documents/upload', component: UploadDocumentComponent },
      { path: 'reviews', component: ReviewListComponent },
      { path: 'reviews/:id', component: ReviewDetailComponent },
    ],
  },
  { path: '**', redirectTo: 'dashboard' },
];
