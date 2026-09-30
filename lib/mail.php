<?php
/**
 * Bestätigungsmail nach der Anmeldung.
 *
 * Versand über die PHP Funktion mail() des Webservers (bei Hostpoint ohne Einrichtung
 * verfügbar). Absender ist eine Mailadresse der eigenen Domain (z.B. info@doc-bern.ch),
 * damit die Mail nicht im Spam landet. Es wird kein Passwort benötigt.
 */
declare(strict_types=1);

require_once __DIR__ . '/calendar.php';

/** Basis URL der Anmeldeseite (aus Konfiguration oder automatisch ermittelt). */
function site_url(): string
{
    $url = trim((string) cfg('site_url', ''));
    if ($url !== '') {
        return rtrim($url, '/') . '/';
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = preg_replace('/[^a-z0-9.\-:]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $path = preg_replace('#/admin$#', '', rtrim($path, '/'));
    return ($https ? 'https' : 'http') . '://' . $host . $path . '/';
}

/** Vermerkt den Versandzeitpunkt bei der Anmeldung. */
function mark_mailed(Store $store, string $id): void
{
    $store->locked(function (Store $s) use ($id) {
        $list = $s->registrations();
        foreach ($list as &$r) {
            if (($r['id'] ?? '') === $id) {
                $r['mailed_at'] = date('c');
            }
        }
        unset($r);
        $s->saveRegistrations($list);
    });
}

function mail_enabled(): bool
{
    return (bool) cfg('mail.enabled', false) && filter_var((string) cfg('mail.from', ''), FILTER_VALIDATE_EMAIL);
}

/**
 * Sendet die Bestätigung für eine Anmeldung.
 * @return bool true, wenn die Mail an den Mailserver übergeben wurde
 */
function send_confirmation(array $reg): bool
{
    if (!mail_enabled() || empty($reg['email']) || !filter_var($reg['email'], FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $content  = confirmation_content($reg);
    $from     = (string) cfg('mail.from');
    $fromName = (string) cfg('mail.from_name', cfg('club.short', ''));
    $replyTo  = (string) cfg('mail.reply_to', $from);
    $bcc      = trim((string) cfg('mail.bcc', ''));
    $domain   = substr((string) strrchr($from, '@'), 1);

    $b = fn () => '=_' . random_id(12);
    $mixed = $b();
    $alt   = $b();
    $rel   = $b();

    // Optional: TWINT QR Code als eingebettetes Bild (nur PNG oder JPG, SVG zeigen Mailprogramme nicht an)
    $qrPart = '';
    $qrFile = APP_ROOT . '/' . ltrim((string) cfg('twint.qr', ''), '/');
    $qrExt  = strtolower(pathinfo($qrFile, PATHINFO_EXTENSION));
    $useQr  = cfg('features.payment') && is_file($qrFile) && in_array($qrExt, ['png', 'jpg', 'jpeg'], true);
    if ($useQr) {
        $mime   = $qrExt === 'png' ? 'image/png' : 'image/jpeg';
        $qrPart = "--$rel\r\n"
            . "Content-Type: $mime; name=\"twint-qr.$qrExt\"\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . "Content-ID: <twintqr@$domain>\r\n"
            . "Content-Disposition: inline; filename=\"twint-qr.$qrExt\"\r\n\r\n"
            . chunk_split(base64_encode((string) file_get_contents($qrFile))) . "\r\n";
    }
    $html = str_replace('{{QR}}', $useQr ? '<img src="cid:twintqr@' . e($domain) . '" width="180" height="180" alt="TWINT QR Code" style="display:block;margin:12px 0;border:0;">' : '', $content['html']);

    $body = "This is a multi-part message in MIME format.\r\n\r\n"
        . "--$mixed\r\n"
        . "Content-Type: multipart/alternative; boundary=\"$alt\"\r\n\r\n"
        . "--$alt\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($content['text'])) . "\r\n"
        . "--$alt\r\n"
        . "Content-Type: multipart/related; boundary=\"$rel\"\r\n\r\n"
        . "--$rel\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html)) . "\r\n"
        . $qrPart
        . "--$rel--\r\n\r\n"
        . "--$alt--\r\n\r\n";

    if (cfg('features.calendar', true)) {
        $body .= "--$mixed\r\n"
            . "Content-Type: text/calendar; charset=UTF-8; method=PUBLISH; name=\"" . ics_filename() . "\"\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . "Content-Disposition: attachment; filename=\"" . ics_filename() . "\"\r\n\r\n"
            . chunk_split(base64_encode(build_ics())) . "\r\n";
    }
    $body .= "--$mixed--\r\n";

    $headers = [
        'From: ' . mb_encode_mimeheader($fromName, 'UTF-8', 'Q') . " <$from>",
        'Reply-To: ' . $replyTo,
        'MIME-Version: 1.0',
        "Content-Type: multipart/mixed; boundary=\"$mixed\"",
        'Date: ' . date('r'),
        'Message-ID: <' . random_id(12) . '@' . $domain . '>',
        'X-Mailer: DOC Eventanmeldung',
        'Auto-Submitted: auto-generated',
    ];
    if ($bcc !== '' && filter_var($bcc, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Bcc: ' . $bcc;
    }

    $subject = mb_encode_mimeheader('Anmeldebestätigung: ' . cfg('event.title'), 'UTF-8', 'B', "\r\n");

    // -f setzt den Envelope Absender (wichtig für SPF und Rückläufer)
    $ok = @mail($reg['email'], $subject, $body, implode("\r\n", $headers), '-f' . $from);
    if (!$ok) {
        error_log('[anmeldung] Bestätigungsmail an ' . $reg['email'] . ' konnte nicht versendet werden.');
    }
    return $ok;
}

/** Erstellt Text und HTML Version der Bestätigung. */
function confirmation_content(array $reg): array
{
    $f       = cfg('features');
    $persons = $reg['persons'] ?? [];
    $main    = $persons[0] ?? ['first' => ''];
    $title   = (string) cfg('event.title');
    $when    = format_date_long((string) cfg('event.date')) . ', ' . cfg('event.start') . ' bis ' . cfg('event.end') . ' Uhr';
    $where   = trim(cfg('event.location', '') . ', ' . cfg('event.address', ''), ' ,');
    $url     = site_url();
    $contact = (string) cfg('club.contact_email', '');
    $total   = registration_total($reg);
    $notice  = cfg('notice_after_registration', []);
    $menuLbl = fn ($m) => (string) cfg("menu.$m.label", $m);

    // --- Text ---------------------------------------------------------------
    $t   = [];
    $t[] = 'Hallo ' . $main['first'];
    $t[] = '';
    $t[] = 'Vielen Dank für deine Anmeldung zum Anlass «' . $title . '». Folgende Personen sind angemeldet:';
    $t[] = '';
    foreach ($persons as $p) {
        $line = '* ' . $p['first'] . ' ' . $p['last'] . ($p['type'] === 'child' ? ' (Kind)' : '');
        if ($f['alt_menu']) {
            $line .= ', ' . $menuLbl($p['menu']);
        }
        if ($f['payment']) {
            $line .= ', ' . format_chf(price_for($p));
        }
        $t[] = $line;
    }
    $t[] = '';
    $t[] = 'Wann: ' . $when;
    if ($where !== '') {
        $t[] = 'Wo: ' . $where;
    }
    if (cfg('program', [])) {
        $t[] = '';
        $t[] = 'Ablauf:';
        foreach (cfg('program', []) as $p) {
            $t[] = $p['time'] . ': ' . $p['title'];
        }
    }
    if ($f['payment'] && $total > 0) {
        $t[] = '';
        $t[] = 'Zu bezahlen: ' . format_chf($total);
        $t[] = 'Bitte per TWINT bezahlen. Den QR Code findest du jederzeit auf der Anmeldeseite: ' . $url;
        if (cfg('twint.note')) {
            $t[] = cfg('twint.note');
        }
    }
    if (!empty($notice['title']) || !empty($notice['text'])) {
        $t[] = '';
        $t[] = mb_strtoupper((string) ($notice['title'] ?? ''));
        $t[] = (string) ($notice['text'] ?? '');
        if (!empty($notice['link'])) {
            $t[] = $notice['link'];
        }
    }
    $t[] = '';
    $t[] = 'Änderungen oder Abmeldungen bitte mit dem Vorstand besprechen' . ($contact ? ": $contact." : '.') . ' Du kannst auch einfach auf diese Mail antworten.';
    if ($f['calendar']) {
        $t[] = 'Im Anhang findest du den Kalendereintrag.';
    }
    $t[] = '';
    $t[] = 'Wir freuen uns auf dich!';
    $t[] = cfg('club.name');
    $text = implode("\r\n", $t);

    // --- HTML ---------------------------------------------------------------
    $rows = '';
    foreach ($persons as $p) {
        $rows .= '<tr><td style="padding:8px 0;border-bottom:1px solid #eee;">' . e($p['first'] . ' ' . $p['last'])
            . ($p['type'] === 'child' ? ' <span style="color:#888;">(Kind)</span>' : '') . '</td>'
            . ($f['alt_menu'] ? '<td style="padding:8px 0 8px 12px;border-bottom:1px solid #eee;color:#555;">' . e($menuLbl($p['menu'])) . '</td>' : '')
            . ($f['payment'] ? '<td style="padding:8px 0 8px 12px;border-bottom:1px solid #eee;text-align:right;white-space:nowrap;">' . e(format_chf(price_for($p))) . '</td>' : '')
            . '</tr>';
    }
    if ($f['payment']) {
        $cols  = 1 + ($f['alt_menu'] ? 1 : 0);
        $rows .= '<tr><td colspan="' . $cols . '" style="padding:10px 0;font-weight:bold;">Total</td><td style="padding:10px 0 10px 12px;text-align:right;font-weight:bold;white-space:nowrap;">' . e(format_chf($total)) . '</td></tr>';
    }

    $program = '';
    if (cfg('program', [])) {
        $program = '<br><br><strong>Ablauf</strong>';
        foreach (cfg('program', []) as $p) {
            $program .= '<br>' . e($p['time'] . ': ' . $p['title']);
        }
    }

    $pay = '';
    if ($f['payment'] && $total > 0) {
        $pay = '<div style="margin:20px 0;padding:16px;border-radius:12px;background:#f7f7f8;">'
            . '<strong>Zu bezahlen: ' . e(format_chf($total)) . '</strong><br>'
            . 'Bitte per TWINT bezahlen: QR Code in der TWINT App scannen.{{QR}}'
            . (cfg('twint.note') ? '<span style="color:#555;">' . e(cfg('twint.note')) . '</span><br>' : '')
            . '<a href="' . e($url) . '" style="color:#cc0000;">QR Code auf der Anmeldeseite anzeigen</a></div>';
    }

    $noticeHtml = '';
    if (!empty($notice['title']) || !empty($notice['text'])) {
        $noticeHtml = '<div style="margin:20px 0;padding:16px;border-radius:12px;background:#fff8e1;border:1px solid #f0d27a;">'
            . (!empty($notice['title']) ? '<strong>' . e($notice['title']) . '</strong><br>' : '')
            . e($notice['text'] ?? '')
            . (!empty($notice['link']) ? '<br><a href="' . e($notice['link']) . '" style="color:#cc0000;font-weight:bold;">' . e($notice['link_label'] ?: 'Zur Anmeldung') . '</a>' : '')
            . '</div>';
    }

    $html = '<!doctype html><html lang="de-CH"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>'
        . '<body style="margin:0;padding:0;background:#f0f0f2;font-family:-apple-system,Segoe UI,Helvetica,Arial,sans-serif;color:#1c1c1e;line-height:1.5;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f0f0f2;"><tr><td align="center" style="padding:24px 12px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#fff;border-radius:16px;overflow:hidden;">'
        . '<tr><td style="background:#cc0000;padding:20px 24px;color:#fff;">'
        . '<div style="font-size:12px;letter-spacing:1px;text-transform:uppercase;opacity:.85;">' . e(cfg('club.name')) . '</div>'
        . '<div style="font-size:22px;font-weight:bold;">' . e($title) . '</div></td></tr>'
        . '<tr><td style="padding:24px;">'
        . '<p style="margin:0 0 12px;">Hallo ' . e($main['first']) . '</p>'
        . '<p style="margin:0 0 16px;">Vielen Dank für deine Anmeldung! Folgende Personen sind angemeldet:</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px;">' . $rows . '</table>'
        . '<p style="margin:20px 0 0;"><strong>Wann:</strong> ' . e($when)
        . ($where !== '' ? '<br><strong>Wo:</strong> ' . e($where) : '') . $program . '</p>'
        . $pay . $noticeHtml
        . '<p style="margin:20px 0 0;color:#555;font-size:14px;">Änderungen oder Abmeldungen bitte mit dem Vorstand besprechen'
        . ($contact ? ': <a href="mailto:' . e($contact) . '" style="color:#cc0000;">' . e($contact) . '</a>.' : '.')
        . ' Du kannst auch einfach auf diese Mail antworten.'
        . ($f['calendar'] ? '<br>Im Anhang findest du den Kalendereintrag.' : '') . '</p>'
        . '<p style="margin:20px 0 0;">Wir freuen uns auf dich!<br><strong>' . e(cfg('club.name')) . '</strong></p>'
        . '</td></tr></table></td></tr></table></body></html>';

    return ['text' => $text, 'html' => $html];
}
