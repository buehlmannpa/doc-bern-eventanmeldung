<?php
/**
 * Kalendereintrag (.ics) für den Anlass.
 */
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/calendar.php';

if (!cfg('features.calendar', true)) {
    http_response_code(404);
    exit;
}

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . ics_filename() . '"');
header('Cache-Control: no-store');
echo build_ics();
