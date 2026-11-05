import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of } from 'rxjs';

import { TaxWorkspaceApiService } from '../../services/tax-workspace-api.service';
import { WealthSummary } from '../../utils/wealth';
import { WealthOverviewComponent } from './wealth-overview.component';

const summary: WealthSummary = {
  assets: [{ kind: 'property', id: 'p', label: 'House', value: 900_000, as_of: '2026-09-01' }],
  total_assets: 900_000,
  total_liabilities: 500_000,
  net_worth: 400_000,
  average_interest_rate: 6,
  history: [
    { month: '2026-06', assets: 800_000, liabilities: 500_000, net_worth: 300_000 },
    { month: '2026-09', assets: 900_000, liabilities: 500_000, net_worth: 400_000 },
  ],
  cash_flow: [{ month: '2026-09', income: 5000, expenses: 3000 }],
};

describe('WealthOverviewComponent', () => {
  function render(data: WealthSummary) {
    TestBed.configureTestingModule({
      providers: [provideRouter([]), { provide: TaxWorkspaceApiService, useValue: { getWealth: () => of(data) } }],
    });
    const fixture = TestBed.createComponent(WealthOverviewComponent);
    fixture.detectChanges();

    return fixture;
  }

  it('shows net worth, savings rate and the three scenarios', () => {
    const fixture = render(summary);
    const text = (fixture.nativeElement as HTMLElement).textContent ?? '';

    expect(text).toContain('$400,000');
    expect(text).toContain('40%');
    expect(text).toContain('conservative');
    expect(text).toContain('optimistic');
    expect(text).toContain('not financial or tax advice');
  });

  it('redraws the projection when an assumption changes', () => {
    const fixture = render(summary);
    const before = fixture.componentInstance.yearsAt('base', 10);

    fixture.componentInstance.setAssumption('assetGrowth', { target: { value: '9' } } as unknown as Event);

    expect(fixture.componentInstance.yearsAt('base', 10)).toBeGreaterThan(before);
  });

  it('prompts for values when there is no trend yet', () => {
    const fixture = render({ ...summary, history: [], assets: [] });

    expect((fixture.nativeElement as HTMLElement).textContent).toContain('Add your');
  });
});
