export type SearchResultKind = 'Page' | 'Document' | 'Transaction' | 'Property' | 'Investment' | 'Financial year';

export interface SearchResult {
  kind: SearchResultKind;
  title: string;
  subtitle: string;
  link: string;
  queryParams?: Record<string, string>;
}

export const SEARCH_PAGES: SearchResult[] = [
  { kind: 'Page', title: 'Dashboard', subtitle: 'Overview', link: '/dashboard' },
  { kind: 'Page', title: 'Documents', subtitle: 'Receipts and tax documents', link: '/documents' },
  { kind: 'Page', title: 'Upload documents', subtitle: 'Add receipts or tax documents', link: '/documents/upload' },
  { kind: 'Page', title: 'Financial years', subtitle: 'Manage financial years', link: '/financial-years' },
  { kind: 'Page', title: 'Reviews', subtitle: 'Document reviews', link: '/reviews' },
  { kind: 'Page', title: 'Income & expenses', subtitle: 'Transactions, bank import, working from home', link: '/transactions' },
  { kind: 'Page', title: 'Properties', subtitle: 'Properties and mortgages', link: '/properties' },
  { kind: 'Page', title: 'Investments', subtitle: 'Investments and capital gains', link: '/investments' },
  { kind: 'Page', title: 'Accountant pack', subtitle: 'Tax return items and exports', link: '/tax-return' },
  { kind: 'Page', title: 'Accountants', subtitle: 'Invite and manage accountants', link: '/accountants' },
  { kind: 'Page', title: 'Settings', subtitle: 'Preferences and notifications', link: '/settings' },
];

export function searchResults(query: string, candidates: SearchResult[], limit = 30): SearchResult[] {
  const terms = query.toLowerCase().split(/\s+/).filter(Boolean);

  if (terms.length === 0) {
    return [];
  }

  return candidates
    .filter((item) => {
      const haystack = `${item.title} ${item.subtitle} ${item.kind}`.toLowerCase();

      return terms.every((term) => haystack.includes(term));
    })
    .slice(0, limit);
}
