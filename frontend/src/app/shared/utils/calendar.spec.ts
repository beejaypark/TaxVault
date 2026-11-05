import { buildReminderIcs, defaultLodgementDate } from './calendar';

describe('calendar reminders', () => {
  it('defaults to 31 October of the year the financial year ends', () => {
    expect(defaultLodgementDate('2026-06-30')).toBe('2026-10-31');
    expect(defaultLodgementDate('2026-06-30T00:00:00.000000Z')).toBe('2026-10-31');
    expect(defaultLodgementDate('')).toBeNull();
  });

  it('builds an all-day event with two alerts', () => {
    const ics = buildReminderIcs('2026-10-31', 'Tax return due, 31 Oct', 'Line one\nLine two', new Date('2026-03-01T01:02:03.456Z'));

    expect(ics).toContain('DTSTART;VALUE=DATE:20261031');
    expect(ics).toContain('DTEND;VALUE=DATE:20261101');
    expect(ics).toContain('DTSTAMP:20260301T010203Z');
    expect(ics).toContain('SUMMARY:Tax return due\\, 31 Oct');
    expect(ics).toContain('DESCRIPTION:Line one\\nLine two');
    expect(ics.match(/BEGIN:VALARM/g)).toHaveLength(2);
    expect(ics.endsWith('\r\n')).toBe(true);
  });

  it('rolls the end date over a month boundary', () => {
    expect(buildReminderIcs('2026-12-31', 'x', 'y')).toContain('DTEND;VALUE=DATE:20270101');
  });
});
