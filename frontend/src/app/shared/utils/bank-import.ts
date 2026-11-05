export interface ParsedBankRow {
  date: string;
  description: string;
  amount: number;
}

export interface ParsedBankFile {
  rows: ParsedBankRow[];
  skipped: number;
  error: string;
}

const MONTHS = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];

export function parseCsv(text: string): string[][] {
  const rows: string[][] = [];
  let row: string[] = [];
  let cell = '';
  let quoted = false;
  const source = text.replace(/^\uFEFF/, '');

  for (let i = 0; i < source.length; i++) {
    const char = source[i];

    if (quoted) {
      if (char === '"' && source[i + 1] === '"') {
        cell += '"';
        i++;
      } else if (char === '"') {
        quoted = false;
      } else {
        cell += char;
      }
    } else if (char === '"') {
      quoted = true;
    } else if (char === ',') {
      row.push(cell);
      cell = '';
    } else if (char === '\n' || char === '\r') {
      if (char === '\r' && source[i + 1] === '\n') {
        i++;
      }
      row.push(cell);
      cell = '';
      if (row.some((value) => value.trim() !== '')) {
        rows.push(row);
      }
      row = [];
    } else {
      cell += char;
    }
  }

  row.push(cell);
  if (row.some((value) => value.trim() !== '')) {
    rows.push(row);
  }

  return rows;
}

export function parseBankDate(value: string): string | null {
  const text = value.trim();
  let match = /^(\d{4})-(\d{2})-(\d{2})/.exec(text) ?? /^(\d{4})(\d{2})(\d{2})/.exec(text);

  if (match) {
    return validDate(+match[1], +match[2], +match[3]);
  }

  match = /^(\d{1,2})[/.-](\d{1,2})[/.-](\d{2}|\d{4})$/.exec(text);
  if (match) {
    const year = match[3].length === 2 ? 2000 + +match[3] : +match[3];

    return validDate(year, +match[2], +match[1]);
  }

  match = /^(\d{1,2})[ -]([A-Za-z]{3})[a-z]*[ ,-]+(\d{2}|\d{4})$/.exec(text);
  if (match) {
    const month = MONTHS.indexOf(match[2].toLowerCase()) + 1;
    const year = match[3].length === 2 ? 2000 + +match[3] : +match[3];

    return month > 0 ? validDate(year, month, +match[1]) : null;
  }

  return null;
}

function validDate(year: number, month: number, day: number): string | null {
  const date = new Date(Date.UTC(year, month - 1, day));

  if (date.getUTCFullYear() !== year || date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day) {
    return null;
  }

  return `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

export function parseBankAmount(value: string | undefined): number | null {
  if (value === undefined) {
    return null;
  }

  let text = value.trim();
  if (!text) {
    return null;
  }

  const negative = /^\(.*\)$/.test(text) || /\bDR$/i.test(text) || text.startsWith('-') || text.endsWith('-');
  text = text.replace(/[()$,\s]|DR$|CR$/gi, '').replace(/^[-+]|-$/g, '');

  if (!/^\d+(\.\d+)?$/.test(text)) {
    return null;
  }

  const amount = Math.round(parseFloat(text) * 100) / 100;

  return negative ? -amount : amount;
}

export function parseBankFile(text: string, fileName: string): ParsedBankFile {
  const isOfx = /\.(ofx|qfx)$/i.test(fileName) || /<STMTTRN>/i.test(text);

  return isOfx ? parseOfx(text) : parseBankCsv(text);
}

function parseOfx(text: string): ParsedBankFile {
  const rows: ParsedBankRow[] = [];
  let skipped = 0;
  const blocks = text.match(/<STMTTRN>[\s\S]*?(?=<\/STMTTRN>|<STMTTRN>|<\/BANKTRANLIST>|$)/gi) ?? [];
  const tag = (block: string, name: string): string =>
    new RegExp(`<${name}>([^<\\r\\n]*)`, 'i').exec(block)?.[1].trim() ?? '';

  for (const block of blocks) {
    const date = parseBankDate(tag(block, 'DTPOSTED').slice(0, 8));
    const amount = parseBankAmount(tag(block, 'TRNAMT'));
    const description = [tag(block, 'NAME'), tag(block, 'MEMO')].filter(Boolean).join(' - ');

    if (date && amount !== null && amount !== 0 && description) {
      rows.push({ date, description, amount });
    } else {
      skipped++;
    }
  }

  return {
    rows,
    skipped,
    error: rows.length === 0 ? 'No transactions were found in this OFX file.' : '',
  };
}

function parseBankCsv(text: string): ParsedBankFile {
  const table = parseCsv(text);
  const rows: ParsedBankRow[] = [];
  let skipped = 0;

  if (table.length === 0) {
    return { rows, skipped, error: 'The file is empty.' };
  }

  const header = table[0].map((cell) => cell.trim().toLowerCase());
  const hasHeader = header.some((cell) => /date/.test(cell)) && parseBankDate(table[0][0]) === null;
  const find = (pattern: RegExp): number => header.findIndex((cell) => pattern.test(cell));

  let dateCol = 0;
  let descCol = 2;
  let amountCol = 1;
  let debitCol = -1;
  let creditCol = -1;

  if (hasHeader) {
    dateCol = find(/date/);
    descCol = find(/desc|narrat|detail|memo|particular|payee|reference|transaction/);
    debitCol = find(/debit|withdraw|paid out/);
    creditCol = find(/credit|deposit|paid in/);
    amountCol = find(/^amount|^value|amount \(/);

    if (descCol < 0 || (amountCol < 0 && debitCol < 0 && creditCol < 0)) {
      return { rows, skipped, error: 'Could not find description and amount columns in the header row.' };
    }
  }

  for (const cells of hasHeader ? table.slice(1) : table) {
    const date = parseBankDate(cells[dateCol] ?? '');
    let amount = amountCol >= 0 ? parseBankAmount(cells[amountCol]) : null;

    if (amount === null && (debitCol >= 0 || creditCol >= 0)) {
      const debit = parseBankAmount(cells[debitCol]);
      const credit = parseBankAmount(cells[creditCol]);
      amount = credit ? Math.abs(credit) : debit ? -Math.abs(debit) : null;
    }

    const description = (cells[descCol] ?? '').replace(/\s+/g, ' ').trim();

    if (date && amount !== null && amount !== 0 && description) {
      rows.push({ date, description, amount });
    } else {
      skipped++;
    }
  }

  return {
    rows,
    skipped,
    error: rows.length === 0
      ? 'No transactions could be read. Expected columns: date, description and amount (or debit/credit).'
      : '',
  };
}
