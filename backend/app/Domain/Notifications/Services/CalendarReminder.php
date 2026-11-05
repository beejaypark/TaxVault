<?php

namespace App\Domain\Notifications\Services;

use Carbon\CarbonImmutable;

class CalendarReminder
{
    /** An all-day event with alerts one week and one day before. */
    public static function allDayEvent(string $uid, string $title, string $description, CarbonImmutable $date): string
    {
        $escape = fn (string $text) => str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $text);
        $alarm = fn (string $trigger) => [
            'BEGIN:VALARM',
            'ACTION:DISPLAY',
            'DESCRIPTION:'.$escape($title),
            'TRIGGER:'.$trigger,
            'END:VALARM',
        ];

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//TaxVault//Reminders//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$uid.'@taxvault',
            'DTSTAMP:'.CarbonImmutable::now('UTC')->format('Ymd\THis\Z'),
            'DTSTART;VALUE=DATE:'.$date->format('Ymd'),
            'DTEND;VALUE=DATE:'.$date->addDay()->format('Ymd'),
            'SUMMARY:'.$escape($title),
            'DESCRIPTION:'.$escape($description),
            ...$alarm('-P7D'),
            ...$alarm('-P1D'),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", $lines)."\r\n";
    }
}
