<?php
/**
 * Erzeugt den Kalendereintrag (.ics) für den Anlass.
 * Wird von calendar.php (Download) und der Bestätigungsmail (Anhang) verwendet.
 */
declare(strict_types=1);

function ics_escape(string $s): string
{
    return str_replace(["\\", ";", ",", "\r\n", "\n"], ["\\\\", "\\;", "\\,", "\\n", "\\n"], $s);
}

/** Zeilen nach RFC 5545 auf 75 Oktette umbrechen. */
function ics_fold(string $line): string
{
    $out = '';
    while (strlen($line) > 75) {
        $cut = 75;
        while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
            $cut--; // keine UTF-8 Zeichen zerschneiden
        }
        $out .= substr($line, 0, $cut) . "\r\n ";
        $line = substr($line, $cut);
    }
    return $out . $line;
}

function ics_filename(): string
{
    return preg_replace('/[^a-z0-9\-]/i', '-', (string) cfg('event.id')) . '.ics';
}

function build_ics(): string
{
    $date  = (string) cfg('event.date');
    $start = strtotime($date . ' ' . cfg('event.start', '00:00'));
    $end   = strtotime($date . ' ' . cfg('event.end', '23:59'));
    if ($end <= $start) {
        $end += 86400; // Ende nach Mitternacht
    }

    $description = (string) cfg('event.description', '');
    foreach (cfg('program', []) as $p) {
        $description .= "\n" . $p['time'] . ': ' . $p['title'];
    }

    $location = trim(cfg('event.location', '') . ', ' . cfg('event.address', ''), ' ,');
    $locUrl   = trim((string) cfg('event.location_url', ''));
    if ($locUrl !== '') {
        $description .= "\n\n" . cfg('event.location') . ': ' . $locUrl;
    }
    $host     = preg_replace('/[^a-z0-9.\-]/i', '', (string) (cfg('mail.from') ? substr(strrchr((string) cfg('mail.from'), '@'), 1) : ($_SERVER['HTTP_HOST'] ?? 'localhost')));

    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//' . cfg('club.short') . '//Eventanmeldung//DE',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VTIMEZONE',
        'TZID:Europe/Zurich',
        'BEGIN:DAYLIGHT',
        'TZOFFSETFROM:+0100',
        'TZOFFSETTO:+0200',
        'TZNAME:CEST',
        'DTSTART:19700329T020000',
        'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
        'END:DAYLIGHT',
        'BEGIN:STANDARD',
        'TZOFFSETFROM:+0200',
        'TZOFFSETTO:+0100',
        'TZNAME:CET',
        'DTSTART:19701025T030000',
        'RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
        'END:STANDARD',
        'END:VTIMEZONE',
        'BEGIN:VEVENT',
        'UID:' . cfg('event.id') . '-' . date('Ymd', $start) . '@' . $host,
        'DTSTAMP:' . gmdate('Ymd\THis\Z'),
        'DTSTART;TZID=Europe/Zurich:' . date('Ymd\THis', $start),
        'DTEND;TZID=Europe/Zurich:' . date('Ymd\THis', $end),
        'SUMMARY:' . ics_escape(cfg('club.short') . ': ' . cfg('event.title')),
        'DESCRIPTION:' . ics_escape(trim($description)),
        'LOCATION:' . ics_escape($location),
        ...($locUrl !== '' ? ['URL:' . $locUrl] : []),
        'BEGIN:VALARM',
        'TRIGGER:-P1D',
        'ACTION:DISPLAY',
        'DESCRIPTION:' . ics_escape((string) cfg('event.title')),
        'END:VALARM',
        'END:VEVENT',
        'END:VCALENDAR',
    ];

    return implode("\r\n", array_map('ics_fold', $lines)) . "\r\n";
}
