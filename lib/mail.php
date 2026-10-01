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
 * Versendet eine Mail (Text und HTML) mit optionalen eingebetteten Bildern und Anhängen.
 *
 * @param array $inline      [['cid' => 'name', 'file' => Pfad, 'filename' => 'x.png', 'mime' => 'image/png']]
 * @param array $attachments [['filename' => 'x.ics', 'mime' => 'text/calendar', 'content' => '...']]
 */
function mail_send(string $to, string $subject, string $text, string $html, array $inline = [], array $attachments = []): bool
{
    if (!mail_enabled() || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $from     = (string) cfg('mail.from');
    $fromName = (string) cfg('mail.from_name', cfg('club.short', ''));
    $replyTo  = (string) cfg('mail.reply_to', $from);
    $bcc      = trim((string) cfg('mail.bcc', ''));
    $domain   = substr((string) strrchr($from, '@'), 1);

    $b = fn () => '=_' . random_id(12);
    $mixed = $b();
    $alt   = $b();
    $rel   = $b();

    foreach ($inline as $img) {
        $html = str_replace('cid:' . $img['cid'], 'cid:' . $img['cid'] . '@' . $domain, $html);
    }

    $part = fn (string $mime, string $data, string $extra = '') => "Content-Type: $mime\r\n"
        . "Content-Transfer-Encoding: base64\r\n" . $extra . "\r\n"
        . chunk_split(base64_encode($data)) . "\r\n";

    $related = "--$rel\r\n" . $part('text/html; charset=UTF-8', $html);
    foreach ($inline as $img) {
        $related .= "--$rel\r\n" . $part(
            $img['mime'] . '; name="' . $img['filename'] . '"',
            (string) file_get_contents($img['file']),
            'Content-ID: <' . $img['cid'] . '@' . $domain . ">\r\nContent-Disposition: inline; filename=\"" . $img['filename'] . "\"\r\n"
        );
    }

    $body = "This is a multi-part message in MIME format.\r\n\r\n"
        . "--$mixed\r\n"
        . "Content-Type: multipart/alternative; boundary=\"$alt\"\r\n\r\n"
        . "--$alt\r\n" . $part('text/plain; charset=UTF-8', $text)
        . "--$alt\r\n"
        . "Content-Type: multipart/related; boundary=\"$rel\"\r\n\r\n"
        . $related
        . "--$rel--\r\n\r\n"
        . "--$alt--\r\n\r\n";
    foreach ($attachments as $att) {
        $body .= "--$mixed\r\n" . $part(
            $att['mime'] . '; name="' . $att['filename'] . '"',
            $att['content'],
            'Content-Disposition: attachment; filename="' . $att['filename'] . "\"\r\n"
        );
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

    // -f setzt den Envelope Absender (wichtig für SPF und Rückläufer)
    $ok = @mail($to, mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n"), $body, implode("\r\n", $headers), '-f' . $from);
    if (!$ok) {
        error_log('[anmeldung] Mail an ' . $to . ' konnte nicht versendet werden.');
    }
    return $ok;
}

/** TWINT QR Code für die Mail (nur PNG oder JPG, SVG zeigen Mailprogramme nicht an). */
function mail_qr_image(): ?array
{
    if (!cfg('features.payment')) {
        return null;
    }
    $path = twint_qr_path(false);
    $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($path === '' || !in_array($ext, ['png', 'jpg', 'jpeg'], true)) {
        return null;
    }
    return [
        'cid'      => 'twintqr',
        'file'     => APP_ROOT . '/' . $path,
        'filename' => basename($path),                 // z.B. twint_code_big.PNG
        'mime'     => $ext === 'png' ? 'image/png' : 'image/jpeg',
    ];
}

/**
 * Sendet die Bestätigung für eine Anmeldung.
 * Anhänge: TWINT QR Code (eingebettet und als Datei) sowie Kalendereintrag.
 * @return bool true, wenn die Mail an den Mailserver übergeben wurde
 */
function send_confirmation(array $reg): bool
{
    if (empty($reg['email'])) {
        return false;
    }
    $content = confirmation_content($reg);
    $qr      = mail_qr_image();
    $inline  = [];
    $files   = [];

    if ($qr && registration_total($reg) > 0) {
        $inline[] = $qr;
        // Zusätzlich als normaler Anhang, damit der Code gespeichert und in der TWINT App geöffnet werden kann
        $files[] = ['filename' => $qr['filename'], 'mime' => $qr['mime'], 'content' => (string) file_get_contents($qr['file'])];
        $html = str_replace('{{QR}}', '<img src="cid:twintqr" width="180" height="180" alt="TWINT QR Code" style="display:block;margin:12px 0;border:0;">', $content['html']);
    } else {
        $html = str_replace('{{QR}}', '', $content['html']);
    }
    if (cfg('features.calendar', true)) {
        $files[] = ['filename' => ics_filename(), 'mime' => 'text/calendar; charset=UTF-8; method=PUBLISH', 'content' => build_ics()];
    }

    return mail_send($reg['email'], 'Anmeldebestätigung: ' . cfg('event.title'), $content['text'], $html, $inline, $files);
}

/** Bestätigung für einen Eintrag auf der Warteliste. */
function send_waitlist_confirmation(array $entry, int $position): bool
{
    $title   = (string) cfg('event.title');
    $contact = (string) cfg('club.contact_email', '');
    $persons = (int) ($entry['persons'] ?? 1);
    $who     = $persons === 1 ? '1 Person' : "$persons Personen";

    $text = implode("\r\n", [
        'Hallo ' . $entry['first'],
        '',
        "Der Anlass «{$title}» ist zurzeit ausgebucht. Du stehst auf der Warteliste ($who).",
        "Deine Position: $position",
        '',
        'Sobald ein Platz frei wird, melden wir uns in der Reihenfolge der Warteliste per E-Mail bei dir.',
        'Bitte beachte: Der Eintrag auf der Warteliste ist noch keine Anmeldung.',
        '',
        'Fragen oder Abmeldung von der Warteliste' . ($contact ? ": $contact." : '.'),
        '',
        'Freundliche Grüsse',
        (string) cfg('club.name'),
    ]);

    $inner = '<p style="margin:0 0 12px;">Hallo ' . e($entry['first']) . '</p>'
        . '<p style="margin:0 0 16px;">Der Anlass ist zurzeit ausgebucht. Du stehst auf der <strong>Warteliste</strong> (' . e($who) . ').</p>'
        . '<div style="margin:0 0 16px;padding:16px;border-radius:12px;background:#f7f7f8;text-align:center;">'
        . '<div style="font-size:13px;color:#555;text-transform:uppercase;letter-spacing:1px;">Deine Position</div>'
        . '<div style="font-size:40px;font-weight:bold;color:#cc0000;">' . $position . '</div></div>'
        . '<p style="margin:0 0 12px;">Sobald ein Platz frei wird, melden wir uns in der Reihenfolge der Warteliste per E-Mail bei dir.</p>'
        . '<p style="margin:0 0 12px;color:#555;font-size:14px;">Bitte beachte: Der Eintrag auf der Warteliste ist noch keine Anmeldung.</p>'
        . '<p style="margin:20px 0 0;color:#555;font-size:14px;">Fragen oder Abmeldung von der Warteliste'
        . ($contact ? ': <a href="mailto:' . e($contact) . '" style="color:#cc0000;">' . e($contact) . '</a>.' : '.') . '</p>'
        . '<p style="margin:20px 0 0;">Freundliche Grüsse<br><strong>' . e(cfg('club.name')) . '</strong></p>';

    return mail_send($entry['email'], 'Warteliste: ' . $title, $text, mail_layout($title, $inner));
}

/** Gemeinsamer HTML Rahmen der Mails. */
function mail_layout(string $title, string $inner): string
{
    return '<!doctype html><html lang="de-CH"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>'
        . '<body style="margin:0;padding:0;background:#f0f0f2;font-family:-apple-system,Segoe UI,Helvetica,Arial,sans-serif;color:#1c1c1e;line-height:1.5;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f0f0f2;"><tr><td align="center" style="padding:24px 12px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#fff;border-radius:16px;overflow:hidden;">'
        . '<tr><td style="background:#cc0000;padding:20px 24px;color:#fff;">'
        . '<div style="font-size:12px;letter-spacing:1px;text-transform:uppercase;opacity:.85;">' . e(cfg('club.name')) . '</div>'
        . '<div style="font-size:22px;font-weight:bold;">' . e($title) . '</div></td></tr>'
        . '<tr><td style="padding:24px;">' . $inner . '</td></tr></table></td></tr></table></body></html>';
}

/** Erstellt Text und HTML Version der Bestätigung. */
function confirmation_content(array $reg): array
{
    $f       = cfg('features');
    $persons = $reg['persons'] ?? [];
    $main    = $persons[0] ?? ['first' => ''];
    $title   = (string) cfg('event.title');
    $when    = format_date_long((string) cfg('event.date')) . ', ' . cfg('event.start') . ' bis ' . cfg('event.end') . ' Uhr';
    $locName = trim((string) cfg('event.location', ''));
    $locAddr = trim((string) cfg('event.address', ''));
    $locUrl  = trim((string) cfg('event.location_url', ''));
    $url     = site_url();
    $terms   = payment_terms_text();
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
    if ($locName !== '') {
        $t[] = 'Wo: ' . $locName;
    }
    if ($locAddr !== '') {
        $t[] = '     ' . $locAddr;
    }
    if ($locUrl !== '') {
        $t[] = '     ' . $locUrl;
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
        $t[] = 'Bitte per TWINT bezahlen. Den QR Code findest du im Anhang und jederzeit auf der Anmeldeseite: ' . $url;
        if (cfg('twint.note')) {
            $t[] = cfg('twint.note');
        }
        $t[] = '';
        $t[] = 'WICHTIG: ' . $terms;
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
            . '<a href="' . e($url) . '" style="color:#cc0000;">QR Code auf der Anmeldeseite anzeigen</a>'
            . '<p style="margin:12px 0 0;padding:10px 12px;border-radius:8px;background:#fdecec;color:#8e1b1b;font-size:14px;"><strong>Wichtig:</strong> ' . e($terms) . '</p></div>';
    }

    $noticeHtml = '';
    if (!empty($notice['title']) || !empty($notice['text'])) {
        $noticeHtml = '<div style="margin:20px 0;padding:16px;border-radius:12px;background:#fff8e1;border:1px solid #f0d27a;">'
            . (!empty($notice['title']) ? '<strong>' . e($notice['title']) . '</strong><br>' : '')
            . e($notice['text'] ?? '')
            . (!empty($notice['link']) ? '<br><a href="' . e($notice['link']) . '" style="color:#cc0000;font-weight:bold;">' . e($notice['link_label'] ?: 'Zur Anmeldung') . '</a>' : '')
            . '</div>';
    }

    $where = '';
    if ($locName !== '') {
        $where = '<br><strong>Wo:</strong> ' . ($locUrl !== '' ? '<a href="' . e($locUrl) . '" style="color:#cc0000;">' . e($locName) . '</a>' : e($locName))
            . ($locAddr !== '' ? '<br><span style="color:#555;">' . e($locAddr) . '</span>' : '');
    }

    $inner = '<p style="margin:0 0 12px;">Hallo ' . e($main['first']) . '</p>'
        . '<p style="margin:0 0 16px;">Vielen Dank für deine Anmeldung! Folgende Personen sind angemeldet:</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px;">' . $rows . '</table>'
        . '<p style="margin:20px 0 0;"><strong>Wann:</strong> ' . e($when) . $where . $program . '</p>'
        . $pay . $noticeHtml
        . '<p style="margin:20px 0 0;color:#555;font-size:14px;">Änderungen oder Abmeldungen bitte mit dem Vorstand besprechen'
        . ($contact ? ': <a href="mailto:' . e($contact) . '" style="color:#cc0000;">' . e($contact) . '</a>.' : '.')
        . ' Du kannst auch einfach auf diese Mail antworten.'
        . ($f['calendar'] ? '<br>Im Anhang findest du den Kalendereintrag.' : '') . '</p>'
        . '<p style="margin:20px 0 0;">Wir freuen uns auf dich!<br><strong>' . e(cfg('club.name')) . '</strong></p>';
    $html = mail_layout($title, $inner);

    return ['text' => $text, 'html' => $html];
}
