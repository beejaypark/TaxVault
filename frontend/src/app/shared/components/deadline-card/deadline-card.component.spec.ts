import { signal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of } from 'rxjs';

import { AuthService } from '../../services/auth/auth.service';
import { DeadlineDto, TaxWorkspaceApiService } from '../../services/tax-workspace-api.service';
import { DeadlineCardComponent } from './deadline-card.component';

describe('DeadlineCardComponent', () => {
  const api = { getDeadlines: vi.fn(), setDeadlineCompleted: vi.fn() };
  const open: DeadlineDto = {
    label: 'FY 2025-26',
    date: '2026-10-31',
    days_left: 12,
    completed: false,
    financial_year_id: 'fy1',
    uncategorised_transactions: 4,
    documents_to_review: 0,
  };

  async function create(deadlines: DeadlineDto[], isAccountant = false): Promise<ComponentFixture<DeadlineCardComponent>> {
    api.getDeadlines.mockReturnValue(of(deadlines));
    await TestBed.configureTestingModule({
      imports: [DeadlineCardComponent],
      providers: [
        provideRouter([]),
        { provide: TaxWorkspaceApiService, useValue: api },
        { provide: AuthService, useValue: { isAccountant, user: signal(null) } },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(DeadlineCardComponent);
    fixture.detectChanges();

    return fixture;
  }

  const text = (fixture: ComponentFixture<unknown>) => (fixture.nativeElement as HTMLElement).textContent ?? '';

  beforeEach(() => {
    vi.resetAllMocks();
    TestBed.resetTestingModule();
  });

  it('shows days left and what is still open', async () => {
    const fixture = await create([open]);

    expect(text(fixture)).toContain('12');
    expect(text(fixture)).toContain('4 transactions without a tax return item');
    expect(text(fixture)).toContain('No documents waiting for review');
  });

  it('renders nothing when there is no upcoming deadline', async () => {
    const fixture = await create([]);

    expect(text(fixture).trim()).toBe('');
  });

  it('marks the deadline as done', async () => {
    api.setDeadlineCompleted.mockReturnValue(of({}));
    const fixture = await create([open]);

    fixture.componentInstance.toggle(open);
    fixture.detectChanges();

    expect(api.setDeadlineCompleted).toHaveBeenCalledWith('2026-10-31', true);
    expect(text(fixture)).toContain('Marked as done');
    expect(text(fixture)).toContain('Reopen');
  });

  it('hides the done button from accountants', async () => {
    const fixture = await create([open], true);

    expect(text(fixture)).not.toContain('Mark as done');
  });
});
