<?php
/**
 * CMS: Anmeldungen verwalten, Auswertungen, Anmeldung öffnen und schliessen.
 */
declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/auth.php';

admin_session_start();
send_security_headers();
header('Cache-Control: no-store');

$error = '';
$generatedHash = '';
$hashConfigured = (string) cfg('admin_password_hash', '') !== '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $error = 'Sitzung abgelaufen. Bitte erneut versuchen.';
    } elseif (isset($_POST['logout'])) {
        admin_logout();
        header('Location: ' . base_path() . 'admin/');
        exit;
    } elseif (!$hashConfigured && isset($_POST['new_password'])) {
        // Einrichtung: Hash erzeugen (wird NICHT gespeichert, sondern angezeigt)
        $pw = (string) $_POST['new_password'];
        if (mb_strlen($pw) < 12) {
            $error = 'Das Passwort muss mindestens 12 Zeichen lang sein.';
        } elseif ($pw !== (string) ($_POST['new_password2'] ?? '')) {
            $error = 'Die Passwörter stimmen nicht überein.';
        } else {
            $generatedHash = password_hash($pw, PASSWORD_DEFAULT);
        }
    } elseif (isset($_POST['password'])) {
        $error = admin_login((string) $_POST['password']) ?? '';
        if ($error === '') {
            header('Location: ' . base_path() . 'admin/');
            exit;
        }
    }
}

$asset = fn (string $p) => e(asset_url($p));
$base  = e(base_path());
?>
<!doctype html>
<html lang="de-CH">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#f5f5f7" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0e0e10" media="(prefers-color-scheme: dark)">
    <meta name="robots" content="noindex, nofollow">
    <title>CMS | <?= e(cfg('event.title')) ?></title>
    <?= favicon_tag() ?>
    <link rel="stylesheet" href="<?= $asset('assets/css/base.css') ?>">
    <link rel="stylesheet" href="<?= $asset('assets/css/admin.css') ?>">
</head>
<body class="admin">
<div class="backdrop" aria-hidden="true"><span class="blob b1"></span><span class="blob b2"></span><span class="blob b3"></span></div>

<?php if (!admin_logged_in()): ?>

<main class="wrap login-wrap">
    <section class="glass login-card">
        <div class="brand"><?php if (cfg('club.logo')): ?><img src="<?= $asset((string) cfg('club.logo')) ?>" alt="<?= e(cfg('club.name')) ?>" class="brand-logo"><?php else: ?><span class="brand-mark">DOC</span><?php endif; ?><span class="brand-text">CMS <?= e(cfg('club.short')) ?></span></div>
        <h1><?= e(cfg('event.title')) ?></h1>

        <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>

        <?php if (!$hashConfigured): ?>
            <?php if ($generatedHash): ?>
                <p>Kopiere diesen Hash in <code>config/event.php</code> bei <code>'admin_password_hash'</code> und lade die Datei hoch. Danach kannst du dich hier anmelden.</p>
                <textarea class="hash" readonly rows="3"><?= e($generatedHash) ?></textarea>
            <?php else: ?>
                <p class="muted">Ersteinrichtung: Wähle ein Passwort (mindestens 12 Zeichen). Es wird ein Hash erzeugt, den du in die Konfiguration einträgst. Das Passwort selbst wird nirgends gespeichert.</p>
                <form method="post" class="stack">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <label class="field"><span>Neues Passwort</span><input type="password" name="new_password" minlength="12" required autocomplete="new-password"></label>
                    <label class="field"><span>Passwort wiederholen</span><input type="password" name="new_password2" minlength="12" required autocomplete="new-password"></label>
                    <button class="btn btn-primary btn-block">Hash erzeugen</button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <form method="post" class="stack">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <label class="field"><span>Passwort</span><input type="password" name="password" required autofocus autocomplete="current-password"></label>
                <button class="btn btn-primary btn-block">Anmelden</button>
            </form>
        <?php endif; ?>
        <p class="small muted"><a href="<?= $base ?>">Zur Anmeldeseite</a></p>
    </section>
</main>

<?php else: ?>

<header class="topbar">
    <div class="wrap wrap-wide topbar-inner">
        <div class="brand"><?php if (cfg('club.logo')): ?><img src="<?= $asset((string) cfg('club.logo')) ?>" alt="<?= e(cfg('club.name')) ?>" class="brand-logo"><?php else: ?><span class="brand-mark">DOC</span><?php endif; ?><span class="brand-text">CMS · <?= e(cfg('event.title')) ?></span></div>
        <div class="top-actions">
            <span class="status-pill" id="status-pill">…</span>
            <a class="btn btn-glass btn-sm" href="<?= $base ?>" target="_blank" rel="noopener">Seite ansehen</a>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <button class="btn btn-glass btn-sm" name="logout" value="1">Abmelden</button>
            </form>
        </div>
    </div>
</header>

<main class="wrap wrap-wide admin-main">
    <nav class="tabs" role="tablist">
        <button role="tab" class="tab" data-tab="overview" aria-selected="true">Übersicht</button>
        <button role="tab" class="tab" data-tab="list" aria-selected="false">Anmeldungen</button>
        <button role="tab" class="tab" data-tab="settings" aria-selected="false">Einstellungen</button>
    </nav>

    <section id="tab-overview" class="tab-panel"></section>
    <section id="tab-list" class="tab-panel" hidden></section>
    <section id="tab-settings" class="tab-panel" hidden></section>
</main>

<dialog id="editor" class="glass dialog"></dialog>
<div id="toast" class="toast" role="status" hidden></div>

<script type="application/json" id="admin-config"><?= json_encode([
    'csrf'     => csrf_token(),
    'api'      => base_path() . 'admin/api.php',
    'features' => cfg('features'),
    'prices'   => cfg('prices'),
    'roles'    => cfg('roles', []),
    'menu'     => [
        'standard'    => cfg('menu.standard.label', 'Menü'),
        'alternative' => cfg('menu.alternative.label', 'Alternatives Menü'),
    ],
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= $asset('assets/js/admin.js') ?>"></script>

<?php endif; ?>
</body>
</html>
