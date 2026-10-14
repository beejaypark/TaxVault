import { Routes } from '@angular/router';

import { DashboardComponent } from './features/dashboard/pages/dashboard/dashboard.component';
import { DocumentListComponent } from './features/documents/pages/document-list/document-list.component';
import { UploadDocumentComponent } from './features/documents/pages/upload-document/upload-document.component';
import { ReviewListComponent } from './features/reviews/pages/review-list/review-list.component';
import { ReviewDetailComponent } from './features/reviews/pages/review-detail/review-detail.component';

export const routes: Routes = [
  {
    path: '',
    pathMatch: 'full',
    redirectTo: 'dashboard',
  },
  {
    path: 'dashboard',
    component: DashboardComponent,
  },
  {
    path: 'documents',
    component: DocumentListComponent,
  },
  {
    path: 'documents/upload',
    component: UploadDocumentComponent,
  },
  {
    path: 'reviews',
    component: ReviewListComponent,
  },
  {
    path: 'reviews/:id',
    component: ReviewDetailComponent,
  },
];
