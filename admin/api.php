<?php
/**
 * API des CMS (nur mit gültiger Sitzung und CSRF Token).
 */
declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/auth.php';

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
        $r['total'] = registration_total($r);
    }
    unset($r);
    return [
        'ok'            => true,
        'registrations' => $list,
        'status'        => registration_status($list, $s->state()),
        'state'         => $s->state(),
        'defaultCap'    => (int) cfg('capacity', 80),
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
                $list[] = array_merge([
                    'id'         => random_id(8),
                    'created_at' => date('c'),
                    'source'     => 'admin',
                ], $reg, ['updated_at' => date('c')]);
            } else {
                $list[$idx] = array_merge($list[$idx], $reg, ['updated_at' => date('c')]);
            }
            $s->saveRegistrations($list);
            return [];
        });

        if (isset($result['error'])) {
            json_response(['ok' => false] + $result, $result['code']);
        }
        json_response(snapshot($store));
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
