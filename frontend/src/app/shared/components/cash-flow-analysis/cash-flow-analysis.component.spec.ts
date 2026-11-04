import { ComponentFixture, TestBed } from '@angular/core/testing';

import { TransactionDto } from '../../services/tax-workspace-api.service';
import { CashFlowAnalysisComponent } from './cash-flow-analysis.component';

function tx(date: string, direction: 'income' | 'expense', amount: string, description: string): TransactionDto {
  return {
    id: `${date}-${description}`,
    financial_year_id: 'fy',
    document_id: null,
    transaction_date: date,
    description,
    direction,
    amount,
    currency: 'AUD',
    source_system: null,
  };
}

describe('CashFlowAnalysisComponent', () => {
  let fixture: ComponentFixture<CashFlowAnalysisComponent>;

  function render(transactions: TransactionDto[]): HTMLElement {
    fixture = TestBed.createComponent(CashFlowAnalysisComponent);
    fixture.componentRef.setInput('transactions', transactions);
    fixture.componentRef.setInput('startDate', '2025-07-01');
    fixture.componentRef.setInput('endDate', '2026-06-30');
    fixture.detectChanges();

    return fixture.nativeElement as HTMLElement;
  }

  it('draws bars with real geometry for ISO timestamp dates', () => {
    const element = render([
      tx('2025-09-12T00:00:00.000000Z', 'income', '134000.00', 'Rent'),
      tx('2025-10-02T00:00:00.000000Z', 'expense', '1134.25', 'Plumber'),
    ]);
    const rects = Array.from(element.querySelectorAll('svg rect'));
    const visible = rects.filter((rect) => Number(rect.getAttribute('height')) > 0);

    expect(rects.length).toBe(24);
    expect(visible.length).toBeGreaterThanOrEqual(2);
    expect(element.innerHTML).not.toContain('NaN');
    expect(element.querySelector('polyline')?.getAttribute('points')).not.toContain('NaN');
  });

  it('shows an empty state instead of disappearing when the year has no transactions', () => {
    const element = render([]);

    expect(element.textContent).toContain('No transactions are recorded');
    expect(element.querySelector('svg')).toBeNull();
  });

  it('explains the analysis lens and a takeaway for each chart', () => {
    const element = render([
      tx('2025-09-12', 'income', '5000', 'Rent'),
      tx('2025-10-02', 'expense', '1000', 'Plumber'),
    ]);

    expect(element.querySelectorAll('.lens').length).toBe(4);
    expect(element.textContent).toContain('Strongest month: Sep');
    expect(element.textContent).toContain('is the largest income source');
  });

  it('shows forecast and insights sections', () => {
    const element = render([tx('2025-09-12', 'income', '1000', 'Rent')]);

    expect(element.textContent).toContain('Cash flow forecast');
    expect(element.textContent).toContain('Where to cut or grow');
  });
});
