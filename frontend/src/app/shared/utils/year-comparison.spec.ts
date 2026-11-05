import { ReturnSummaryDto, ReturnSummaryLine } from '../services/tax-workspace-api.service';
import { compareReturnSummaries, findPreviousYearId } from './year-comparison';

function line(category: string, amount: number, propertyId: string | null = null): ReturnSummaryLine {
  return {
    category,
    label: category,
    group: 'Deductions',
    ato_ref: 'D5',
    direction: 'expense',
    amount,
    records: 1,
    property_id: propertyId,
    property_name: '',
    source: null,
  };
}

function summary(lines: ReturnSummaryLine[]): ReturnSummaryDto {
  return { lines } as ReturnSummaryDto;
}

describe('year comparison', () => {
  it('flags missing, new and materially changed items only', () => {
    const items = compareReturnSummaries(
      summary([line('a', 1000), line('b', 100), line('d', 50), line('new', 500)]),
      summary([line('a', 400), line('b', 120), line('c', 300), line('d', 50)]),
    );

    expect(items.map((item) => `${item.kind}:${item.label}`).sort()).toEqual([
      'changed:a',
      'missing:c',
      'new:new',
    ]);
  });

  it('treats the same category on different properties separately', () => {
    const items = compareReturnSummaries(
      summary([line('rates', 500, 'p1')]),
      summary([line('rates', 500, 'p1'), line('rates', 400, 'p2')]),
    );

    expect(items).toHaveLength(1);
    expect(items[0].kind).toBe('missing');
  });

  it('finds the closest earlier financial year', () => {
    const years = [
      { id: 'a', start_date: '2023-07-01' },
      { id: 'b', start_date: '2024-07-01' },
      { id: 'c', start_date: '2025-07-01' },
    ];

    expect(findPreviousYearId(years, 'c')).toBe('b');
    expect(findPreviousYearId(years, 'a')).toBeNull();
  });
});
