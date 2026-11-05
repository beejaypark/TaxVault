import { parseBankFile, parseCsv } from './bank-import';

describe('bank import', () => {
  it('parses quoted CSV cells', () => {
    expect(parseCsv('a,"b,c"\n1,2')).toEqual([['a', 'b,c'], ['1', '2']]);
  });

  it('parses a headed CSV with debit and credit columns', () => {
    const csv = 'Date,Description,Debit,Credit\n03/08/2025,Officeworks,25.50,\n04/08/2025,Salary,,1000.00\n';
    const result = parseBankFile(csv, 'x.csv');

    expect(result.error).toBe('');
    expect(result.rows).toEqual([
      { date: '2025-08-03', description: 'Officeworks', amount: -25.5 },
      { date: '2025-08-04', description: 'Salary', amount: 1000 },
    ]);
  });

  it('parses a headerless date, amount, description CSV', () => {
    const result = parseBankFile('05/08/2025,-12.30,Coffee\n', 'x.csv');

    expect(result.rows).toEqual([{ date: '2025-08-05', description: 'Coffee', amount: -12.3 }]);
  });

  it('parses OFX transactions', () => {
    const ofx = '<STMTTRN><DTPOSTED>20250806000000<TRNAMT>-40.00<NAME>Water bill</STMTTRN>';
    const result = parseBankFile(ofx, 'x.ofx');

    expect(result.rows).toEqual([{ date: '2025-08-06', description: 'Water bill', amount: -40 }]);
  });

  it('reports an error when nothing is readable', () => {
    expect(parseBankFile('nothing useful', 'x.csv').error).not.toBe('');
  });
});
