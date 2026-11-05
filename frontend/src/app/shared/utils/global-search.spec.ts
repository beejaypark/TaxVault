import { SEARCH_PAGES, SearchResult, searchResults } from './global-search';

describe('global search', () => {
  const candidates: SearchResult[] = [
    ...SEARCH_PAGES,
    { kind: 'Transaction', title: 'Officeworks stationery', subtitle: '2025-08-01 · expense $10.00', link: '/transactions' },
    { kind: 'Document', title: 'water-bill.pdf', subtitle: 'Utility bill · FY 2025 - 26', link: '/documents' },
  ];

  it('returns nothing for a blank query', () => {
    expect(searchResults('  ', candidates)).toEqual([]);
  });

  it('matches every term in any order, ignoring case', () => {
    const results = searchResults('STATIONERY office', candidates);

    expect(results.map((item) => item.title)).toEqual(['Officeworks stationery']);
  });

  it('matches on kind and subtitle', () => {
    expect(searchResults('document utility', candidates).map((item) => item.title)).toEqual(['water-bill.pdf']);
  });

  it('finds pages and caps the number of results', () => {
    expect(searchResults('settings', candidates)[0].link).toBe('/settings');
    expect(searchResults('a', candidates, 3)).toHaveLength(3);
  });
});
