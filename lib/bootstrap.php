<?php
/**
 * Gemeinsame Funktionen für Anmeldeseite, API und CMS.
 */
declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';

$GLOBALS['config'] = require APP_ROOT . '/config/event.php';
date_default_timezone_set(cfg('timezone', 'Europe/Zurich'));
mb_internal_encoding('UTF-8');

/** Liest einen Konfigurationswert mit Punktnotation, z.B. cfg('features.children'). */
function cfg(string $key, $default = null)
{
    $value = $GLOBALS['config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/**
 * Absoluter Pfad des Installationsordners, z.B. "/" oder "/test/".
 * Wird automatisch ermittelt, damit die Seite in jedem Unterordner läuft.
 */
function base_path(): string
{
    static $base = null;
    if ($base === null) {
        $dir  = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $dir  = preg_replace('#/admin$#', '', rtrim($dir, '/'));
        $base = $dir . '/';
    }
    return $base;
}

/** URL zu einer Datei im Installationsordner, mit Versionsparameter gegen veralteten Cache. */
function asset_url(string $path): string
{
    $path = ltrim($path, '/');
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $mtime = @filemtime(APP_ROOT . '/' . $path);
    return base_path() . $path . ($mtime ? '?v=' . $mtime : '');
}

/** Favicon: Club Logo, falls konfiguriert, sonst das Standard Symbol. */
function favicon_tag(): string
{
    $logo = trim((string) cfg('club.logo', ''));
    $path = ($logo !== '' && is_file(APP_ROOT . '/' . ltrim($logo, '/'))) ? $logo : 'assets/img/favicon.svg';
    $types = ['svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'ico' => 'image/x-icon', 'webp' => 'image/webp', 'gif' => 'image/gif'];
    $type  = $types[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'image/png';
    $url   = htmlspecialchars(asset_url($path), ENT_QUOTES, 'UTF-8');
    return '<link rel="icon" href="' . $url . '" type="' . $type . '">' . "\n    "
        . '<link rel="apple-touch-icon" href="' . $url . '">';
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function send_security_headers(bool $html = true): void
{
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Frame-Options: DENY');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if ($html) {
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    }
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    send_security_headers(false);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input', false, null, 0, 200000);
    $data = json_decode($raw ?: '', true);
    return is_array($data) ? $data : [];
}

function client_ip_hash(): string
{
    return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . __FILE__);
}

function random_id(int $bytes = 8): string
{
    return bin2hex(random_bytes($bytes));
}

// ----------------------------------------------------------------------
// Datenspeicher (JSON Dateien mit exklusiver Sperre)
// ----------------------------------------------------------------------

final class Store
{
    /**
     * Jede Datendatei ist technisch eine PHP Datei, die sofort abbricht.
     * Selbst wenn .htaccess einmal nicht greift, liefert der Server so keine Daten aus.
     */
    private const GUARD = "<?php http_response_code(404); exit; ?>\n";

    private string $dir;
    /** @var resource|null */
    private $lock = null;

    public function __construct(string $dir)
    {
        $this->dir = rtrim($dir, '/');
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0750, true)) {
            throw new RuntimeException('Datenverzeichnis kann nicht erstellt werden.');
        }
        $this->protectDir();
    }

    /** Schützt das Datenverzeichnis zusätzlich, falls es im Web Verzeichnis liegt. */
    private function protectDir(): void
    {
        $ht = $this->dir . '/.htaccess';
        if (!file_exists($ht)) {
            @file_put_contents($ht, "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
        }
        $idx = $this->dir . '/index.html';
        if (!file_exists($idx)) {
            @file_put_contents($idx, '');
        }
    }

    /** Führt $fn unter exklusiver Sperre aus (verhindert Überbuchung bei gleichzeitigen Anmeldungen). */
    public function locked(callable $fn)
    {
        $this->lock = fopen($this->dir . '/.lock', 'c');
        if (!$this->lock || !flock($this->lock, LOCK_EX)) {
            throw new RuntimeException('Datensperre fehlgeschlagen.');
        }
        try {
            return $fn($this);
        } finally {
            flock($this->lock, LOCK_UN);
            fclose($this->lock);
            $this->lock = null;
        }
    }

    private function file(string $name): string
    {
        return $this->dir . '/' . $name . '.json.php';
    }

    public function read(string $name, array $default = []): array
    {
        $file = $this->file($name);
        if (!is_file($file)) {
            return $default;
        }
        $raw  = (string) file_get_contents($file);
        $data = json_decode(substr($raw, strlen(self::GUARD)), true);
        return is_array($data) ? $data : $default;
    }

    public function write(string $name, array $data): void
    {
        $file = $this->file($name);
        $tmp  = $file . '.' . random_id(4) . '.tmp';
        $json = self::GUARD . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (file_put_contents($tmp, $json) === false || !rename($tmp, $file)) {
            @unlink($tmp);
            throw new RuntimeException('Daten konnten nicht gespeichert werden.');
        }
        @chmod($file, 0640);
    }

    // --- Anmeldungen -----------------------------------------------------

    public function registrations(): array
    {
        return $this->read('registrations', ['registrations' => []])['registrations'] ?? [];
    }

    public function saveRegistrations(array $list): void
    {
        $this->write('registrations', ['registrations' => array_values($list)]);
    }

    // --- Warteliste -------------------------------------------------------

    /** Einträge der Warteliste in Reihenfolge der Eintragung. */
    public function waitlist(): array
    {
        $list = $this->read('waitlist', ['entries' => []])['entries'] ?? [];
        usort($list, fn ($a, $b) => strcmp($a['created_at'] ?? '', $b['created_at'] ?? ''));
        return $list;
    }

    public function saveWaitlist(array $list): void
    {
        $this->write('waitlist', ['entries' => array_values($list)]);
    }

    /** Aktueller Status (manuell im CMS gesetzt). */
    public function state(): array
    {
        return array_merge([
            'mode'              => 'open',   // open | closed | paused
            'message'           => '',
            'capacity_override' => null,
        ], $this->read('state'));
    }

    public function saveState(array $state): void
    {
        $this->write('state', $state);
    }
}

function store(): Store
{
    static $store = null;
    return $store ??= new Store((string) cfg('data_dir'));
}

// ----------------------------------------------------------------------
// Fachlogik
// ----------------------------------------------------------------------

function capacity(array $state): int
{
    $override = $state['capacity_override'] ?? null;
    return ($override !== null && $override !== '') ? max(0, (int) $override) : (int) cfg('capacity', 80);
}

function count_persons(array $registrations): int
{
    $n = 0;
    foreach ($registrations as $r) {
        $n += count($r['persons'] ?? []);
    }
    return $n;
}

function deadline_passed(): bool
{
    $d = trim((string) cfg('deadline', ''));
    if ($d === '') {
        return false;
    }
    $ts = strtotime($d);
    return $ts !== false && time() > $ts;
}

/** Offene Einträge der Warteliste (noch nicht übernommen). */
function waitlist_open(array $entries): array
{
    return array_values(array_filter($entries, fn ($w) => in_array($w['status'] ?? 'waiting', ['waiting', 'contacted'], true)));
}

/**
 * Gesamtstatus der Anmeldung.
 * status: open | full | closed | paused | deadline
 *
 * Sobald jemand auf der Warteliste steht, bleibt die Anmeldung für neue Personen
 * geschlossen, auch wenn ein Platz frei wird. Freie Plätze vergibt der Vorstand
 * in der Reihenfolge der Warteliste.
 */
function registration_status(array $registrations, array $state, ?array $waitlist = null): array
{
    $cap     = capacity($state);
    $taken   = count_persons($registrations);
    $mode    = $state['mode'] ?? 'open';
    $waiting = count(waitlist_open($waitlist ?? store()->waitlist()));

    if ($mode === 'paused') {
        $status  = 'paused';
        $message = $state['message'] ?: 'Die Anmeldung ist vorübergehend nicht verfügbar. Bitte versuche es in Kürze erneut.';
    } elseif ($mode === 'closed') {
        $status  = 'closed';
        $message = $state['message'] ?: 'Der Anlass ist ausgebucht. Vielen Dank für dein Interesse!';
    } elseif (deadline_passed()) {
        $status  = 'deadline';
        $message = 'Der Anmeldeschluss ist vorbei.';
    } elseif ($taken >= $cap || $waiting > 0) {
        $status  = 'full';
        $message = 'Der Anlass ist ausgebucht. Vielen Dank für dein Interesse!';
    } else {
        $status  = 'open';
        $message = '';
    }

    $waitlistOpen = (bool) cfg('features.waitlist', false) && in_array($status, ['full', 'closed', 'deadline'], true);
    if ($waitlistOpen) {
        $message = rtrim($message, '!. ') . '. Trag dich gerne auf die Warteliste ein.';
    }

    return [
        'status'    => $status,
        'message'   => $message,
        'capacity'  => $cap,
        'taken'     => $taken,
        'remaining' => max(0, $cap - $taken),
        'waitlist'  => $waitlistOpen,
        'waiting'   => $waiting,
    ];
}

/** Anmeldeschluss als Text, z.B. "Samstag, 28. November 2026, 23:59 Uhr" (leer, wenn keiner gesetzt). */
function deadline_text(): string
{
    $d  = trim((string) cfg('deadline', ''));
    $ts = $d !== '' ? strtotime($d) : false;
    return $ts ? format_date_long(date('Y-m-d', $ts)) . ', ' . date('H:i', $ts) . ' Uhr' : '';
}

/** Hinweis: Anmeldung ist erst nach Zahlung definitiv. */
function payment_terms_text(): string
{
    if (!cfg('features.payment')) {
        return '';
    }
    $until = deadline_text();
    return 'Die Anmeldung ist erst definitiv, wenn der Betrag ' . ($until !== '' ? "bis zum Anmeldeschluss ($until)" : 'bis zum Anmeldeschluss')
        . ' bezahlt ist. Ohne Zahlung wird der Platz an die nächste Person auf der Warteliste weitergegeben.';
}

/**
 * Zahlungsstatus einer Anmeldung:
 * definitive = bezahlt oder nichts zu bezahlen, pending = offen, overdue = offen nach Anmeldeschluss
 */
function registration_payment_state(array $reg): string
{
    if (!cfg('features.payment') || !empty($reg['paid']) || registration_total($reg) <= 0) {
        return 'definitive';
    }
    return deadline_passed() ? 'overdue' : 'pending';
}

/** Pfad zum TWINT QR Code, falls die Datei vorhanden ist (sonst Platzhalter bzw. leer). */
function twint_qr_path(bool $allowPlaceholder = true): string
{
    $qr = ltrim(trim((string) cfg('twint.qr', '')), '/');
    if ($qr !== '' && is_file(APP_ROOT . '/' . $qr)) {
        return $qr;
    }
    return $allowPlaceholder ? 'assets/img/twint-qr.svg' : '';
}

function price_for(array $person): float
{
    if (!cfg('features.payment')) {
        return 0.0;
    }
    $role = $person['role'] ?? '';
    if ($role !== '' && cfg("roles.$role.free")) {
        return 0.0;
    }
    return (float) (($person['type'] ?? 'adult') === 'child' ? cfg('prices.child', 0) : cfg('prices.adult', 0));
}

function registration_total(array $reg): float
{
    $sum = 0.0;
    foreach ($reg['persons'] ?? [] as $p) {
        $sum += price_for($p);
    }
    return $sum;
}

function clean_text($value, int $max = 80): string
{
    $value = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
    $value = preg_replace('/[\x00-\x1F\x7F<>]/u', '', $value) ?? '';
    return mb_substr($value, 0, $max);
}

/**
 * Prüft und normalisiert eine Anmeldung.
 * $admin = true erlaubt Rollen, Bezahlstatus, Notizen und fehlende E-Mail.
 *
 * @return array{0: array|null, 1: array<string,string>} [Anmeldung, Fehler]
 */
function normalize_registration(array $in, bool $admin = false): array
{
    $errors  = [];
    $altMenu = (bool) cfg('features.alt_menu');
    $roles   = array_keys(cfg('roles', []));

    $person = function (array $p, string $relation, string $type, string $prefix) use (&$errors, $altMenu, $admin, $roles): array {
        $first = clean_text($p['first'] ?? '', 60);
        $last  = clean_text($p['last'] ?? '', 60);
        if ($first === '') {
            $errors[$prefix . '.first'] = 'Bitte Vorname angeben.';
        }
        if ($last === '') {
            $errors[$prefix . '.last'] = 'Bitte Nachname angeben.';
        }
        $menu = ($altMenu && ($p['menu'] ?? '') === 'alternative') ? 'alternative' : 'standard';
        $role = ($admin && in_array($p['role'] ?? '', $roles, true)) ? $p['role'] : '';
        return [
            'id'       => preg_match('/^[a-f0-9]{8,32}$/', (string) ($p['id'] ?? '')) ? $p['id'] : random_id(6),
            'relation' => $relation,
            'type'     => $type,
            'first'    => $first,
            'last'     => $last,
            'menu'     => $menu,
            'role'     => $role,
        ];
    };

    $persons = [];
    $persons[] = $person((array) ($in['main'] ?? []), 'main', 'adult', 'main');

    $email = mb_strtolower(clean_text($in['email'] ?? '', 120));
    if ($email === '' && !$admin) {
        $errors['email'] = 'Bitte E-Mail Adresse angeben.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Diese E-Mail Adresse ist ungültig.';
    }

    if (!empty($in['companion']) && is_array($in['companion'])) {
        if (!cfg('features.companion')) {
            $errors['companion'] = 'Begleitpersonen sind für diesen Anlass nicht möglich.';
        } else {
            $persons[] = $person($in['companion'], 'companion', 'adult', 'companion');
        }
    }

    $children = is_array($in['children'] ?? null) ? array_values($in['children']) : [];
    if ($children) {
        if (!cfg('features.children')) {
            $errors['children'] = 'Kinder können für diesen Anlass nicht angemeldet werden.';
        } elseif (count($children) > (int) cfg('features.max_children', 8)) {
            $errors['children'] = 'Es können maximal ' . (int) cfg('features.max_children', 8) . ' Kinder angemeldet werden.';
        } else {
            foreach ($children as $i => $c) {
                $persons[] = $person((array) $c, 'child', 'child', 'children.' . $i);
            }
        }
    }

    $reg = [
        'email'   => $email,
        'persons' => $persons,
    ];
    if ($admin) {
        $reg['paid']  = !empty($in['paid']);
        $reg['notes'] = clean_text($in['notes'] ?? '', 500);
    }

    return [$errors ? null : $reg, $errors];
}

function over_capacity_message(int $remaining): string
{
    if ($remaining <= 0) {
        return 'Die maximale Anzahl an Anmeldungen ist erreicht. Es sind keine Plätze mehr frei.';
    }
    return 'Die maximale Anzahl an Anmeldungen ist überschritten. Es ' . ($remaining === 1 ? 'ist nur noch 1 Platz' : "sind nur noch $remaining Plätze") . ' frei.';
}

/** Datum im Schweizer Format, z.B. "Samstag, 5. Dezember 2026". */
function format_date_long(string $date): string
{
    $ts = strtotime($date);
    if ($ts === false) {
        return $date;
    }
    $days   = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
    $months = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
    return $days[(int) date('w', $ts)] . ', ' . date('j', $ts) . '. ' . $months[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
}

function format_chf(float $amount): string
{
    return cfg('prices.currency', 'CHF') . ' ' . number_format($amount, 2, '.', "\u{2019}");
}

/** Öffentliche Konfiguration für das Frontend (keine sensiblen Werte). */
function public_config(): array
{
    return [
        'features' => cfg('features'),
        'prices'   => cfg('prices'),
        'menu'     => [
            'standard'    => cfg('menu.standard.label', 'Menü'),
            'alternative' => cfg('menu.alternative.label', 'Alternatives Menü'),
        ],
    ];
}
