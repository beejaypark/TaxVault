/** 31 October after the financial year ends: the usual self-lodgement date. Confirm it with the ATO or your tax agent. */
export function defaultLodgementDate(financialYearEnd: string): string | null {
  const year = /^(\d{4})-\d{2}-\d{2}/.exec(financialYearEnd)?.[1];

  return year ? `${year}-10-31` : null;
}

export function buildReminderIcs(date: string, title: string, description: string, now = new Date()): string {
  const compact = date.replace(/-/g, '');
  const next = new Date(Date.UTC(+date.slice(0, 4), +date.slice(5, 7) - 1, +date.slice(8, 10) + 1));
  const end = next.toISOString().slice(0, 10).replace(/-/g, '');
  const stamp = now.toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');
  const escape = (text: string) => text.replace(/\\/g, '\\\\').replace(/[;,]/g, '\\$&').replace(/\r?\n/g, '\\n');
  const alarm = (trigger: string) => ['BEGIN:VALARM', 'ACTION:DISPLAY', `DESCRIPTION:${escape(title)}`, `TRIGGER:${trigger}`, 'END:VALARM'];

  return [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//TaxVault//Reminders//EN',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'BEGIN:VEVENT',
    `UID:lodgement-${compact}@taxvault`,
    `DTSTAMP:${stamp}`,
    `DTSTART;VALUE=DATE:${compact}`,
    `DTEND;VALUE=DATE:${end}`,
    `SUMMARY:${escape(title)}`,
    `DESCRIPTION:${escape(description)}`,
    ...alarm('-P7D'),
    ...alarm('-P1D'),
    'END:VEVENT',
    'END:VCALENDAR',
    '',
  ].join('\r\n');
}
