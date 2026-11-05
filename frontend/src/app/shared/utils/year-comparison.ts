import { ReturnSummaryDto, ReturnSummaryLine } from '../services/tax-workspace-api.service';

export interface YearComparisonItem {
  kind: 'missing' | 'new' | 'changed';
  label: string;
  propertyName: string;
  atoRef: string;
  previous: number;
  current: number;
}

const CHANGE_RATIO = 0.5;
const CHANGE_MIN_DOLLARS = 200;

function key(line: ReturnSummaryLine): string {
  return `${line.category}|${line.property_id ?? ''}`;
}

export function compareReturnSummaries(
  current: ReturnSummaryDto,
  previous: ReturnSummaryDto,
): YearComparisonItem[] {
  const now = new Map(current.lines.map((line) => [key(line), line]));
  const before = new Map(previous.lines.map((line) => [key(line), line]));
  const items: YearComparisonItem[] = [];

  for (const [id, old] of before) {
    const line = now.get(id);

    if (!line) {
      items.push({
        kind: 'missing',
        label: old.label,
        propertyName: old.property_name,
        atoRef: old.ato_ref,
        previous: old.amount,
        current: 0,
      });
      continue;
    }

    const difference = Math.abs(line.amount - old.amount);

    if (difference >= CHANGE_MIN_DOLLARS && difference / Math.max(old.amount, 1) >= CHANGE_RATIO) {
      items.push({
        kind: 'changed',
        label: line.label,
        propertyName: line.property_name,
        atoRef: line.ato_ref,
        previous: old.amount,
        current: line.amount,
      });
    }
  }

  for (const [id, line] of now) {
    if (!before.has(id)) {
      items.push({
        kind: 'new',
        label: line.label,
        propertyName: line.property_name,
        atoRef: line.ato_ref,
        previous: 0,
        current: line.amount,
      });
    }
  }

  const order = { missing: 0, changed: 1, new: 2 };

  return items.sort((a, b) => order[a.kind] - order[b.kind] || a.label.localeCompare(b.label));
}

export function findPreviousYearId(
  years: { id: string; start_date: string }[],
  currentId: string,
): string | null {
  const current = years.find((year) => year.id === currentId);

  if (!current) {
    return null;
  }

  const previous = years
    .filter((year) => year.start_date.slice(0, 10) < current.start_date.slice(0, 10))
    .sort((a, b) => b.start_date.localeCompare(a.start_date))[0];

  return previous?.id ?? null;
}
