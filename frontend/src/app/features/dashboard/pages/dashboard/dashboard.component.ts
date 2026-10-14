import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterLink } from '@angular/router';

interface DashboardDocument {
  supplier: string;
  amount: string;
  status: 'ready' | 'review';
}

@Component({
  selector: 'app-dashboard',
  imports: [RouterLink],
  templateUrl: './dashboard.component.html',
  styleUrl: './dashboard.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DashboardComponent {
  readonly financialYear = 'FY 2025–26';

  readonly summary = {
    documents: 24,
    needsReview: 3,
    ready: 21,
  };

  readonly attentionDocuments: DashboardDocument[] = [
    {
      supplier: 'Bunnings',
      amount: '$340.00',
      status: 'review',
    },
    {
      supplier: 'Officeworks',
      amount: '$89.90',
      status: 'review',
    },
    {
      supplier: 'JB Hi-Fi',
      amount: '$129.00',
      status: 'review',
    },
  ];

  readonly recentDocuments: DashboardDocument[] = [
    {
      supplier: 'Woolworths',
      amount: '$54.20',
      status: 'ready',
    },
    {
      supplier: 'Coles',
      amount: '$76.40',
      status: 'ready',
    },
    {
      supplier: 'Harvey Norman',
      amount: '$219.00',
      status: 'ready',
    },
  ];
}
