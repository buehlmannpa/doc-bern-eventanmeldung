<?php
/**
 * Anmeldung am CMS: Session, Login mit Sperre nach Fehlversuchen, CSRF Schutz.
 */
declare(strict_types=1);

const ADMIN_IDLE_TIMEOUT = 7200;   // 2 Stunden
const LOGIN_MAX_FAILS    = 5;
const LOGIN_LOCK_SECONDS = 900;    // 15 Minuten

function admin_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('DOCADMIN');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/') . '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();

    if (!empty($_SESSION['admin']) && (time() - ($_SESSION['last'] ?? 0)) > ADMIN_IDLE_TIMEOUT) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last'] = time();
    $_SESSION['csrf'] ??= random_id(16);
}

function admin_logged_in(): bool
{
    return !empty($_SESSION['admin']);
}

function csrf_token(): string
{
    return (string) ($_SESSION['csrf'] ?? '');
}

function csrf_valid(?string $token): bool
{
    return is_string($token) && $token !== '' && hash_equals(csrf_token(), $token);
}

/** @return string|null Fehlermeldung oder null bei Erfolg */
function admin_login(string $password): ?string
{
    $hash = (string) cfg('admin_password_hash', '');
    if ($hash === '') {
        return 'Es ist noch kein Passwort konfiguriert.';
    }

    return store()->locked(function (Store $s) use ($password, $hash) {
        $all = $s->read('logins');
        $key = client_ip_hash();
        $now = time();
        $rec = $all[$key] ?? ['fails' => 0, 'until' => 0];

        if ($rec['until'] > $now) {
            $min = (int) ceil(($rec['until'] - $now) / 60);
            return "Zu viele Fehlversuche. Bitte in $min Minuten erneut versuchen.";
        }

        if (password_verify($password, $hash)) {
            unset($all[$key]);
            $s->write('logins', $all);
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            $_SESSION['csrf']  = random_id(16);
            return null;
        }

        usleep(random_int(300000, 700000));
        $rec['fails']++;
        if ($rec['fails'] >= LOGIN_MAX_FAILS) {
            $rec = ['fails' => 0, 'until' => $now + LOGIN_LOCK_SECONDS];
        }
        $all[$key] = $rec;
        $s->write('logins', $all);
        return 'Passwort ist falsch.';
    });
}

function admin_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}
