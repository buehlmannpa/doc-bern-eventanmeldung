<?php
/**
 * API des CMS (nur mit gültiger Sitzung und CSRF Token).
 */
declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/auth.php';
require __DIR__ . '/../lib/mail.php';
require __DIR__ . '/../lib/xlsx.php';

admin_session_start();

if (!admin_logged_in()) {
    json_response(['ok' => false, 'error' => 'Nicht angemeldet.'], 401);
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST' && !csrf_valid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    json_response(['ok' => false, 'error' => 'Sitzung abgelaufen. Bitte Seite neu laden.'], 403);
}

function snapshot(Store $s): array
{
    $list = $s->registrations();
    foreach ($list as &$r) {
        $r['total']    = registration_total($r);
        $r['payState'] = registration_payment_state($r);
    }
    unset($r);
    $waitlist = $s->waitlist();
    $pos = 0;
    foreach ($waitlist as &$w) {
        $w['position'] = in_array($w['status'] ?? 'waiting', ['waiting', 'contacted'], true) ? ++$pos : null;
    }
    unset($w);
    return [
        'ok'            => true,
        'registrations' => $list,
        'status'        => registration_status($list, $s->state(), $waitlist),
        'waitlist'      => $waitlist,
        'deadline'      => deadline_text(),
        'deadlinePassed'=> deadline_passed(),
        'state'         => $s->state(),
        'defaultCap'    => (int) cfg('capacity', 80),
        'mailEnabled'   => mail_enabled(),
    ];
}

function find_index(array $list, string $id): ?int
{
    foreach ($list as $i => $r) {
        if (($r['id'] ?? '') === $id) {
            return $i;
        }
    }
    return null;
}

try {
    $store = store();

    // --- Lesen --------------------------------------------------------------
    if ($action === 'list' && $method === 'GET') {
        json_response(snapshot($store));
    }

    // --- Excel Export (.xlsx mit Übersicht, Personen und Anmeldungen) -------
    if ($action === 'excel' && $method === 'GET') {
        if (!csrf_valid($_GET['csrf'] ?? null)) {
            http_response_code(403);
            exit('Sitzung abgelaufen.');
        }
        $f       = cfg('features');
        $roles   = cfg('roles', []);
        $list    = $store->registrations();
        usort($list, fn ($a, $b) => strcmp($a['created_at'], $b['created_at']));
        $status  = registration_status($list, $store->state());
        $payName = ['definitive' => 'Definitiv', 'pending' => 'Provisorisch', 'overdue' => 'Zahlung überfällig'];
        $relName = ['main' => 'Hauptperson', 'companion' => 'Weitere Person', 'child' => 'Kind'];
        $menu    = fn ($m) => (string) cfg("menu.$m.label", $m);
        $date    = fn ($iso) => $iso ? date('d.m.Y H:i', strtotime($iso)) : '';

        // Tabelle Personen
        $pHead = ['Nr.', 'Angemeldet am', 'Vorname', 'Nachname', 'E-Mail', 'Art', 'Rolle'];
        $pW    = [6, 17, 16, 18, 28, 16, 15];
        if ($f['alt_menu']) { $pHead[] = 'Menü'; $pW[] = 20; }
        if ($f['payment'])  { $pHead[] = 'Betrag'; $pW[] = 11; $pHead[] = 'Bezahlt'; $pW[] = 9; }
        $pHead[] = 'Notiz'; $pW[] = 30;
        $pRows = []; $pStyles = [];

        // Tabelle Anmeldungen
        $gHead = ['Nr.', 'Angemeldet am', 'Hauptperson', 'E-Mail', 'Personen', 'Erwachsene', 'Kinder'];
        $gW    = [6, 17, 24, 28, 10, 11, 8];
        if ($f['payment']) { $gHead[] = 'Betrag'; $gW[] = 11; $gHead[] = 'Bezahlt'; $gW[] = 9; $gHead[] = 'Status'; $gW[] = 18; }
        if (mail_enabled()) { $gHead[] = 'Bestätigung gesendet'; $gW[] = 20; }
        $gHead[] = 'Quelle'; $gW[] = 10;
        $gHead[] = 'Notiz'; $gW[] = 30;
        $gRows = [];

        $sum = ['adults' => 0, 'children' => 0, 'alt' => 0, 'total' => 0.0, 'paid' => 0.0];
        $roleCount = array_fill_keys(array_keys($roles), 0);

        foreach ($list as $i => $r) {
            $nr     = $i + 1;
            $adults = count(array_filter($r['persons'], fn ($p) => $p['type'] === 'adult'));
            $kids   = count($r['persons']) - $adults;
            $total  = registration_total($r);
            $paid   = !empty($r['paid']);
            $sum['adults'] += $adults; $sum['children'] += $kids; $sum['total'] += $total;
            if ($paid) { $sum['paid'] += $total; }

            foreach ($r['persons'] as $p) {
                $row = [$nr, $date($r['created_at']), $p['first'], $p['last'], $p['relation'] === 'main' ? $r['email'] : '',
                        $relName[$p['relation']] ?? '', $roles[$p['role']]['label'] ?? ''];
                if ($f['alt_menu']) { $row[] = $menu($p['menu']); }
                if ($f['payment'])  { $row[] = ['money' => price_for($p)]; $row[] = $paid ? 'ja' : 'nein'; }
                $row[] = $p['relation'] === 'main' ? ($r['notes'] ?? '') : '';
                $pRows[]   = $row;
                $pStyles[] = $p['role'] !== '' && isset($roles[$p['role']]) ? $p['role'] : null;
                if ($p['menu'] === 'alternative') { $sum['alt']++; }
                if (isset($roleCount[$p['role']])) { $roleCount[$p['role']]++; }
            }

            $main = $r['persons'][0];
            $row  = [$nr, $date($r['created_at']), $main['first'] . ' ' . $main['last'], $r['email'], count($r['persons']), $adults, $kids];
            if ($f['payment']) { $row[] = ['money' => $total]; $row[] = $paid ? 'ja' : 'nein'; $row[] = $payName[registration_payment_state($r)]; }
            if (mail_enabled()) { $row[] = $date($r['mailed_at'] ?? ''); }
            $row[] = ($r['source'] ?? 'web') === 'admin' ? 'manuell' : 'Website';
            $row[] = $r['notes'] ?? '';
            $gRows[] = $row;
        }

        // Tabelle Übersicht
        $ov = [
            [['title' => cfg('club.name') . ': ' . cfg('event.title')]],
            ['Stand', date('d.m.Y H:i')],
            ['Datum Anlass', format_date_long((string) cfg('event.date'))],
            [],
            ['Belegte Plätze', $status['taken']],
            ['Maximale Plätze', $status['capacity']],
            ['Freie Plätze', $status['remaining']],
            ['Anmeldungen (Gruppen)', count($list)],
            ['Erwachsene', $sum['adults']],
            ['Kinder', $sum['children']],
        ];
        if ($f['alt_menu']) {
            $ov[] = [$menu('standard'), $status['taken'] - $sum['alt']];
            $ov[] = [$menu('alternative'), $sum['alt']];
        }
        foreach ($roles as $k => $role) {
            $ov[] = [$role['label'] . ($role['free'] ? ' (kostenlos)' : ''), $roleCount[$k]];
        }
        if ($f['payment']) {
            $defin = 0;
            foreach ($list as $r) {
                if (registration_payment_state($r) === 'definitive') { $defin += count($r['persons']); }
            }
            $ov[] = ['Personen definitiv (bezahlt)', $defin];
            $ov[] = ['Personen provisorisch (offen)', $status['taken'] - $defin];
        }
        if (cfg('features.waitlist')) {
            $ov[] = ['Warteliste (offene Einträge)', $status['waiting']];
        }
        if ($f['payment']) {
            $ov[] = [];
            $ov[] = ['Betrag total', ['money' => $sum['total']]];
            $ov[] = ['Bezahlt', ['money' => $sum['paid']]];
            $ov[] = ['Offen', ['money' => $sum['total'] - $sum['paid']]];
        }

        $fills = [];
        foreach ($roles as $k => $role) {
            // Rollenfarbe aufgehellt, damit der Text gut lesbar bleibt
            $hex = ltrim((string) $role['color'], '#');
            $rgb = array_map('hexdec', str_split(strlen($hex) === 3 ? preg_replace('/(.)/', '$1$1', $hex) : $hex, 2));
            $fills[$k] = vsprintf('%02X%02X%02X', array_map(fn ($c) => (int) round($c + (255 - $c) * 0.65), $rgb));
        }

        $sheets = [
            ['name' => 'Übersicht',   'widths' => [28, 22], 'rows' => $ov],
            ['name' => 'Personen',    'widths' => $pW, 'header' => $pHead, 'rows' => $pRows, 'styles' => $pStyles],
            ['name' => 'Anmeldungen', 'widths' => $gW, 'header' => $gHead, 'rows' => $gRows],
        ];
        if (cfg('features.waitlist')) {
            $wlName = ['waiting' => 'Wartend', 'contacted' => 'Kontaktiert', 'converted' => 'Übernommen', 'declined' => 'Abgesagt'];
            $wRows  = [];
            $pos    = 0;
            foreach ($store->waitlist() as $w) {
                $open    = in_array($w['status'] ?? 'waiting', ['waiting', 'contacted'], true);
                $wRows[] = [$open ? ++$pos : '', $date($w['created_at']), $w['first'], $w['last'], $w['email'], (int) ($w['persons'] ?? 1), $wlName[$w['status'] ?? 'waiting'] ?? '', $w['notes'] ?? ''];
            }
            $sheets[] = ['name' => 'Warteliste', 'widths' => [10, 17, 16, 18, 28, 10, 14, 30],
                'header' => ['Position', 'Eingetragen am', 'Vorname', 'Nachname', 'E-Mail', 'Personen', 'Status', 'Notiz'], 'rows' => $wRows];
        }
        $xlsx = (new XlsxWriter($fills))->build($sheets);

        $name = preg_replace('/[^a-z0-9\-]/i', '-', (string) cfg('event.id')) . '-' . date('Y-m-d') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . strlen($xlsx));
        header('Cache-Control: no-store');
        echo $xlsx;
        exit;
    }

    // --- CSV Export (eine Zeile pro Person, Excel tauglich) -----------------
    if ($action === 'export' && $method === 'GET') {
        if (!csrf_valid($_GET['csrf'] ?? null)) {
            http_response_code(403);
            exit('Sitzung abgelaufen.');
        }
        $roles = cfg('roles', []);
        $csvCell = function ($v): string {
            $v = (string) $v;
            if ($v !== '' && strpbrk($v[0], '=+-@') !== false) {
                $v = "'" . $v; // Schutz vor Formel Injection in Excel
            }
            return '"' . str_replace('"', '""', $v) . '"';
        };
        $rows = [['Anmeldung', 'Datum', 'Vorname', 'Nachname', 'E-Mail', 'Art', 'Rolle', 'Menü', 'Betrag', 'Bezahlt', 'Notiz']];
        foreach ($store->registrations() as $r) {
            foreach ($r['persons'] as $p) {
                $rows[] = [
                    $r['id'],
                    date('d.m.Y H:i', strtotime($r['created_at'])),
                    $p['first'],
                    $p['last'],
                    $p['relation'] === 'main' ? $r['email'] : '',
                    ['main' => 'Hauptperson', 'companion' => 'Weitere Person', 'child' => 'Kind'][$p['relation']] ?? '',
                    $roles[$p['role']]['label'] ?? '',
                    cfg('menu.' . $p['menu'] . '.label', $p['menu']),
                    number_format(price_for($p), 2, '.', ''),
                    !empty($r['paid']) ? 'ja' : 'nein',
                    $p['relation'] === 'main' ? ($r['notes'] ?? '') : '',
                ];
            }
        }
        $name = preg_replace('/[^a-z0-9\-]/i', '-', (string) cfg('event.id')) . '-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Cache-Control: no-store');
        echo "\xEF\xBB\xBF";
        foreach ($rows as $row) {
            echo implode(';', array_map($csvCell, $row)) . "\r\n";
        }
        exit;
    }

    if ($method !== 'POST') {
        json_response(['ok' => false, 'error' => 'Unbekannte Aktion.'], 404);
    }

    $in = read_json_body();

    // --- Erstellen oder bearbeiten ------------------------------------------
    if ($action === 'save') {
        [$reg, $errors] = normalize_registration((array) ($in['registration'] ?? []), true);
        if ($errors) {
            json_response(['ok' => false, 'error' => 'Bitte prüfe die markierten Felder.', 'fields' => $errors], 422);
        }
        $id    = (string) ($in['registration']['id'] ?? '');
        $force = !empty($in['force']);

        $result = $store->locked(function (Store $s) use ($reg, $id, $force) {
            $list = $s->registrations();
            $idx  = $id !== '' ? find_index($list, $id) : null;
            if ($id !== '' && $idx === null) {
                return ['error' => 'Anmeldung nicht gefunden.', 'code' => 404];
            }

            // Platzprüfung: bestehende Anmeldung wird dabei nicht mitgezählt
            $others = $list;
            if ($idx !== null) {
                unset($others[$idx]);
            }
            $cap  = capacity($s->state());
            $free = $cap - count_persons($others);
            if (!$force && count($reg['persons']) > $free) {
                return [
                    'error'      => over_capacity_message(max(0, $free)) . ' Trotzdem speichern?',
                    'code'       => 409,
                    'needsForce' => true,
                ];
            }

            if ($reg['email'] !== '') {
                foreach ($others as $o) {
                    if (($o['email'] ?? '') === $reg['email']) {
                        return ['error' => 'Diese E-Mail Adresse ist bereits bei einer anderen Anmeldung erfasst.', 'code' => 409, 'fields' => ['email' => 'Bereits vorhanden.']];
                    }
                }
            }

            if ($idx === null) {
                $list[] = $saved = array_merge([
                    'id'         => random_id(8),
                    'created_at' => date('c'),
                    'source'     => 'admin',
                ], $reg, ['updated_at' => date('c')]);
            } else {
                $list[$idx] = $saved = array_merge($list[$idx], $reg, ['updated_at' => date('c')]);
            }
            $s->saveRegistrations($list);
            return ['reg' => $saved];
        });

        if (isset($result['error'])) {
            json_response(['ok' => false] + $result, $result['code']);
        }

        // Aus der Warteliste übernommen: Eintrag als erledigt markieren
        $wlId = (string) ($in['waitlistId'] ?? '');
        if ($wlId !== '') {
            $store->locked(function (Store $s) use ($wlId, $result) {
                $wl = $s->waitlist();
                foreach ($wl as &$w) {
                    if (($w['id'] ?? '') === $wlId) {
                        $w['status']       = 'converted';
                        $w['converted_at'] = date('c');
                        $w['registration'] = $result['reg']['id'];
                    }
                }
                unset($w);
                $s->saveWaitlist($wl);
            });
        }

        $mailSent = null;
        if (!empty($in['sendMail']) && $result['reg']['email'] !== '') {
            $mailSent = send_confirmation($result['reg']);
            if ($mailSent) {
                mark_mailed($store, $result['reg']['id']);
            }
        }
        json_response(snapshot($store) + ['mailSent' => $mailSent]);
    }

    // --- Bestätigung erneut senden -------------------------------------------
    if ($action === 'mail') {
        $id  = (string) ($in['id'] ?? '');
        $reg = null;
        foreach ($store->registrations() as $r) {
            if (($r['id'] ?? '') === $id) {
                $reg = $r;
            }
        }
        if (!$reg || empty($reg['email'])) {
            json_response(['ok' => false, 'error' => 'Für diese Anmeldung ist keine E-Mail Adresse erfasst.'], 422);
        }
        if (!send_confirmation($reg)) {
            json_response(['ok' => false, 'error' => 'Die Mail konnte nicht versendet werden. Bitte Mail Konfiguration prüfen.'], 500);
        }
        mark_mailed($store, $id);
        json_response(snapshot($store) + ['mailSent' => true]);
    }

    // --- Löschen --------------------------------------------------------------
    if ($action === 'delete') {
        $id = (string) ($in['id'] ?? '');
        $store->locked(function (Store $s) use ($id) {
            $list = array_filter($s->registrations(), fn ($r) => ($r['id'] ?? '') !== $id);
            $s->saveRegistrations($list);
        });
        json_response(snapshot($store));
    }

    // --- Bezahlstatus ---------------------------------------------------------
    if ($action === 'paid') {
        $id   = (string) ($in['id'] ?? '');
        $paid = !empty($in['paid']);
        $store->locked(function (Store $s) use ($id, $paid) {
            $list = $s->registrations();
            $idx  = find_index($list, $id);
            if ($idx !== null) {
                $list[$idx]['paid']       = $paid;
                $list[$idx]['updated_at'] = date('c');
                $s->saveRegistrations($list);
            }
        });
        json_response(snapshot($store));
    }

    // --- Warteliste: Status/Notiz ändern oder löschen ---------------------------
    if ($action === 'waitlist_update' || $action === 'waitlist_delete') {
        $id = (string) ($in['id'] ?? '');
        $store->locked(function (Store $s) use ($action, $id, $in) {
            $wl = $s->waitlist();
            if ($action === 'waitlist_delete') {
                $wl = array_filter($wl, fn ($w) => ($w['id'] ?? '') !== $id);
            } else {
                foreach ($wl as &$w) {
                    if (($w['id'] ?? '') !== $id) {
                        continue;
                    }
                    if (in_array($in['status'] ?? '', ['waiting', 'contacted', 'converted', 'declined'], true)) {
                        $w['status'] = $in['status'];
                    }
                    if (array_key_exists('notes', $in)) {
                        $w['notes'] = clean_text($in['notes'], 300);
                    }
                    $w['updated_at'] = date('c');
                }
                unset($w);
            }
            $s->saveWaitlist($wl);
        });
        json_response(snapshot($store));
    }

    // --- Status der Anmeldung -------------------------------------------------
    if ($action === 'state') {
        $mode = in_array($in['mode'] ?? '', ['open', 'closed', 'paused'], true) ? $in['mode'] : 'open';
        $cap  = $in['capacity_override'] ?? null;
        $cap  = ($cap === null || $cap === '') ? null : max(0, min(10000, (int) $cap));
        $store->locked(function (Store $s) use ($mode, $in, $cap) {
            $s->saveState([
                'mode'              => $mode,
                'message'           => clean_text($in['message'] ?? '', 300),
                'capacity_override' => $cap,
            ]);
        });
        json_response(snapshot($store));
    }

    json_response(['ok' => false, 'error' => 'Unbekannte Aktion.'], 404);
} catch (Throwable $ex) {
    error_log('[anmeldung admin] ' . $ex->getMessage());
    json_response(['ok' => false, 'error' => 'Technischer Fehler: ' . $ex->getMessage()], 500);
}
