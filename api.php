<?php
/**
 * Öffentliche API der Anmeldeseite.
 *   GET  api.php?action=status    Belegung und Status
 *   POST api.php?action=register  Neue Anmeldung (JSON)
 */
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';

$action = $_GET['action'] ?? 'status';

try {
    if ($action === 'status') {
        $s = store();
        json_response(registration_status($s->registrations(), $s->state()));
    }

    if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $in = read_json_body();

        // Spamschutz: verstecktes Feld muss leer bleiben
        if (!empty($in['website'])) {
            json_response(['ok' => false, 'error' => 'Anmeldung konnte nicht verarbeitet werden.'], 400);
        }
        if (empty($in['consent'])) {
            json_response(['ok' => false, 'error' => 'Bitte bestätige die Datenschutzerklärung.', 'fields' => ['consent' => 'Bitte bestätigen.']], 422);
        }

        [$reg, $errors] = normalize_registration($in, false);
        if ($errors) {
            json_response(['ok' => false, 'error' => 'Bitte prüfe die markierten Felder.', 'fields' => $errors], 422);
        }

        $result = store()->locked(function (Store $s) use ($reg) {
            // Einfaches Rate Limit: max. 10 Anmeldungen pro Stunde und IP
            $rl  = $s->read('ratelimit');
            $key = client_ip_hash();
            $now = time();
            $rl  = array_filter($rl, fn ($times) => is_array($times) && max($times ?: [0]) > $now - 3600);
            $recent = array_filter($rl[$key] ?? [], fn ($t) => $t > $now - 3600);
            if (count($recent) >= 10) {
                return ['error' => 'Zu viele Anmeldungen in kurzer Zeit. Bitte versuche es später erneut.', 'code' => 429];
            }

            $list   = $s->registrations();
            $status = registration_status($list, $s->state());

            if ($status['status'] !== 'open') {
                return ['error' => $status['message'], 'code' => 409, 'status' => $status];
            }
            foreach ($list as $r) {
                if (($r['email'] ?? '') === $reg['email']) {
                    return ['error' => 'Mit dieser E-Mail Adresse besteht bereits eine Anmeldung. Für Änderungen melde dich bitte beim Vorstand.', 'code' => 409, 'fields' => ['email' => 'Bereits angemeldet.']];
                }
            }
            $needed = count($reg['persons']);
            if ($needed > $status['remaining']) {
                return ['error' => over_capacity_message($status['remaining']), 'code' => 409, 'status' => $status];
            }

            $reg = array_merge([
                'id'         => random_id(8),
                'created_at' => date('c'),
                'updated_at' => date('c'),
                'source'     => 'web',
                'paid'       => false,
                'notes'      => '',
            ], $reg);
            $list[] = $reg;
            $s->saveRegistrations($list);

            $recent[]  = $now;
            $rl[$key]  = array_values($recent);
            $s->write('ratelimit', $rl);

            return ['reg' => $reg, 'status' => registration_status($list, $s->state())];
        });

        if (isset($result['error'])) {
            json_response(['ok' => false] + $result, $result['code']);
        }

        $reg = $result['reg'];
        json_response([
            'ok'      => true,
            'status'  => $result['status'],
            'summary' => [
                'email'   => $reg['email'],
                'persons' => array_map(fn ($p) => [
                    'name'  => $p['first'] . ' ' . $p['last'],
                    'type'  => $p['type'],
                    'menu'  => $p['menu'],
                    'price' => price_for($p),
                ], $reg['persons']),
                'total'   => registration_total($reg),
            ],
        ]);
    }

    json_response(['ok' => false, 'error' => 'Unbekannte Aktion.'], 404);
} catch (Throwable $ex) {
    error_log('[anmeldung] ' . $ex->getMessage());
    json_response(['ok' => false, 'error' => 'Technischer Fehler. Bitte versuche es später erneut.'], 500);
}
