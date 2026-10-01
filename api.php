<?php
/**
 * Öffentliche API der Anmeldeseite.
 *   GET  api.php?action=status    Belegung und Status
 *   POST api.php?action=register  Neue Anmeldung (JSON)
 */
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/register.php';

$action = $_GET['action'] ?? 'status';

try {
    if ($action === 'status') {
        $s = store();
        json_response(registration_status($s->registrations(), $s->state()));
    }

    if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $result = register_attempt(read_json_body());
        json_response($result, $result['ok'] ? 200 : (int) $result['code']);
    }

    json_response(['ok' => false, 'error' => 'Unbekannte Aktion.'], 404);
} catch (Throwable $ex) {
    error_log('[anmeldung] ' . $ex->getMessage());
    json_response(['ok' => false, 'error' => 'Technischer Fehler. Bitte versuche es später erneut.'], 500);
}
