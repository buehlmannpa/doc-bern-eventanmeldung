<?php
/**
 * Verarbeitet eine öffentliche Anmeldung.
 * Wird von api.php (JavaScript) und index.php (Rückfall ohne JavaScript) verwendet.
 */
declare(strict_types=1);

require_once __DIR__ . '/mail.php';

/**
 * @return array ok: bool, error?: string, code?: int, fields?: array, status?: array,
 *               reg?: array, mailSent?: bool, summary?: array
 */
function register_attempt(array $in): array
{
    // Spamschutz: verstecktes Feld muss leer bleiben
    if (!empty($in['website'])) {
        return ['ok' => false, 'code' => 400, 'error' => 'Anmeldung konnte nicht verarbeitet werden.'];
    }
    if (empty($in['consent'])) {
        return ['ok' => false, 'code' => 422, 'error' => 'Bitte bestätige die Datenschutzerklärung.', 'fields' => ['consent' => 'Bitte bestätigen.']];
    }

    [$reg, $errors] = normalize_registration($in, false);
    if ($errors) {
        return ['ok' => false, 'code' => 422, 'error' => 'Bitte prüfe die markierten Felder.', 'fields' => $errors];
    }

    $result = store()->locked(function (Store $s) use ($reg) {
        // Einfaches Rate Limit: max. 10 Anmeldungen pro Stunde und IP
        $rl     = $s->read('ratelimit');
        $key    = client_ip_hash();
        $now    = time();
        $rl     = array_filter($rl, fn ($times) => is_array($times) && max($times ?: [0]) > $now - 3600);
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
                $contact = (string) cfg('club.contact_email', '');
                return [
                    'error'     => 'Du bist bereits angemeldet. Änderungen an deiner Anmeldung bitte mit dem Vorstand besprechen' . ($contact ? " ($contact)." : '.'),
                    'code'      => 409,
                    'duplicate' => true,
                    'fields'    => ['email' => 'Bereits angemeldet.'],
                ];
            }
        }
        if (count($reg['persons']) > $status['remaining']) {
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

        $recent[] = $now;
        $rl[$key] = array_values($recent);
        $s->write('ratelimit', $rl);

        return ['reg' => $reg, 'status' => registration_status($list, $s->state())];
    });

    if (isset($result['error'])) {
        return ['ok' => false] + $result;
    }

    $reg = $result['reg'];

    // Bestätigungsmail (ein Fehler beim Versand macht die Anmeldung nicht ungültig)
    $mailSent = false;
    if (mail_enabled()) {
        $mailSent = send_confirmation($reg);
        if ($mailSent) {
            mark_mailed(store(), $reg['id']);
        }
    }

    return [
        'ok'       => true,
        'mailSent' => $mailSent,
        'status'   => $result['status'],
        'summary'  => [
            'email'   => $reg['email'],
            'persons' => array_map(fn ($p) => [
                'name'  => $p['first'] . ' ' . $p['last'],
                'type'  => $p['type'],
                'menu'  => $p['menu'],
                'price' => price_for($p),
            ], $reg['persons']),
            'total'   => registration_total($reg),
        ],
    ];
}

/**
 * Wandelt ein klassisch abgeschicktes Formular (ohne JavaScript) in das Format der API um.
 * PHP ersetzt Punkte in Feldnamen durch Unterstriche: "main.first" wird zu "main_first".
 */
function form_post_to_input(array $post): array
{
    $get = fn (string $k) => is_string($post[$k] ?? null) ? $post[$k] : '';
    $in  = [
        'email'    => $get('email'),
        'consent'  => $get('consent') !== '',
        'website'  => $get('website'),
        'main'     => ['first' => $get('main_first'), 'last' => $get('main_last'), 'menu' => $get('main_menu')],
        'children' => [],
    ];
    if ($get('companion_first') !== '' || $get('companion_last') !== '') {
        $in['companion'] = ['first' => $get('companion_first'), 'last' => $get('companion_last'), 'menu' => $get('companion_menu')];
    }
    foreach ($post as $k => $v) {
        if (preg_match('/^(child\d+)_first$/', (string) $k, $m)) {
            $in['children'][] = ['first' => $get($m[1] . '_first'), 'last' => $get($m[1] . '_last'), 'menu' => $get($m[1] . '_menu')];
        }
    }
    return $in;
}
