<?php
/**
 * Öffentliche Anmeldeseite.
 */
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/register.php';

// Rückfall ohne JavaScript: Formular wurde klassisch abgeschickt
$post = null;
$old  = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $post = register_attempt(form_post_to_input($_POST));
    } catch (Throwable $ex) {
        error_log('[anmeldung] ' . $ex->getMessage());
        $post = ['ok' => false, 'error' => 'Technischer Fehler. Bitte versuche es später erneut.'];
    }
    if (!$post['ok']) {
        $old = array_map(fn ($v) => is_string($v) ? $v : '', $_POST);
    }
}
$done   = $post && $post['ok'];
$fields = $post['fields'] ?? [];

$store  = store();
$status = registration_status($store->registrations(), $store->state());
send_security_headers();
header('Cache-Control: no-store');

$f        = cfg('features');
$program  = cfg('program', []);
$menuStd  = cfg('menu.standard', []);
$menuAlt  = cfg('menu.alternative', []);
$hasMenu  = !empty($menuStd['courses']) || ($f['alt_menu'] && !empty($menuAlt['courses']));
$theme    = preg_replace('/[^a-z0-9\-]/i', '', (string) cfg('theme', ''));
$timeText = cfg('event.start') . ' bis ' . cfg('event.end') . ' Uhr';
$twintQr  = (string) cfg('twint.qr', '');
$pct      = $status['capacity'] > 0 ? min(100, round($status['taken'] / $status['capacity'] * 100)) : 100;
$contact  = (string) cfg('club.contact_email', '');
$asset    = fn (string $p) => e(asset_url($p));
$base     = e(base_path());
?>
<!doctype html>
<html lang="de-CH">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#f5f5f7" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0e0e10" media="(prefers-color-scheme: dark)">
    <meta name="robots" content="noindex">
    <title><?= e(cfg('event.title')) ?> | <?= e(cfg('club.short')) ?></title>
    <link rel="icon" href="<?= $asset('assets/img/favicon.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= $asset('assets/css/base.css') ?>">
    <?php if ($theme && is_file(__DIR__ . "/assets/css/themes/$theme.css")): ?>
        <link rel="stylesheet" href="<?= $asset("assets/css/themes/$theme.css") ?>">
    <?php endif; ?>
</head>
<body class="<?= $theme ? 'theme-' . e($theme) : '' ?>">
<div class="backdrop" aria-hidden="true"><span class="blob b1"></span><span class="blob b2"></span><span class="blob b3"></span></div>

<header class="topbar">
    <div class="wrap topbar-inner">
        <a class="brand" id="brand" href="<?= $base ?>" data-admin="<?= $base ?>admin/">
            <?php if (cfg('club.logo')): ?>
                <img src="<?= $asset((string) cfg('club.logo')) ?>" alt="<?= e(cfg('club.name')) ?>" class="brand-logo">
            <?php else: ?>
                <span class="brand-mark" aria-hidden="true">DOC</span>
                <span class="brand-text"><?= e(cfg('club.name')) ?></span>
            <?php endif; ?>
        </a>
    </div>
</header>

<main class="wrap">

    <!-- Hero -->
    <section class="glass hero">
        <p class="eyebrow"><?= e(cfg('club.short')) ?> lädt ein</p>
        <h1><?= e(cfg('event.title')) ?></h1>
        <?php if (cfg('event.subtitle')): ?><p class="lead"><?= e(cfg('event.subtitle')) ?></p><?php endif; ?>
        <ul class="facts">
            <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 2v3M17 2v3M3.5 9h17M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/></svg><?= e(format_date_long((string) cfg('event.date'))) ?></li>
            <li><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><?= e($timeText) ?></li>
            <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/></svg><?= e(cfg('event.location')) ?><?= cfg('event.address') ? ', ' . e(cfg('event.address')) : '' ?></li>
        </ul>
        <?php if (cfg('event.description')): ?><p class="desc"><?= nl2br(e(cfg('event.description'))) ?></p><?php endif; ?>
        <div class="hero-actions">
            <a href="#anmeldung" class="btn btn-primary">Jetzt anmelden</a>
        </div>
    </section>

    <!-- Belegung -->
    <section class="glass capacity" id="capacity" aria-live="polite">
        <div class="capacity-head">
            <h2>Verfügbare Plätze</h2>
            <p class="capacity-numbers"><strong data-taken><?= (int) $status['taken'] ?></strong> von <span data-capacity><?= (int) $status['capacity'] ?></span> Plätzen belegt</p>
        </div>
        <div class="meter" role="meter" aria-valuemin="0" aria-valuemax="<?= (int) $status['capacity'] ?>" aria-valuenow="<?= (int) $status['taken'] ?>" aria-label="Belegte Plätze">
            <div class="meter-fill" style="width: <?= $pct ?>%"></div>
        </div>
        <p class="capacity-foot"><span data-remaining><?= (int) $status['remaining'] ?></span> Plätze frei</p>
    </section>

    <?php if ($program): ?>
    <!-- Ablauf -->
    <section class="glass">
        <h2>Ablauf</h2>
        <ol class="timeline">
            <?php foreach ($program as $item): ?>
                <li>
                    <span class="tl-time"><?= e($item['time'] ?? '') ?></span>
                    <span class="tl-body">
                        <strong><?= e($item['title'] ?? '') ?></strong>
                        <?php if (!empty($item['text'])): ?><span><?= e($item['text']) ?></span><?php endif; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>
    <?php endif; ?>

    <?php if ($hasMenu): ?>
    <!-- Menü -->
    <section class="glass">
        <h2>Menü</h2>
        <div class="menus <?= ($f['alt_menu'] && !empty($menuAlt['courses'])) ? 'menus-2' : '' ?>">
            <?php foreach (['standard' => $menuStd, 'alternative' => $menuAlt] as $key => $menu): ?>
                <?php if (empty($menu['courses']) || ($key === 'alternative' && !$f['alt_menu'])) continue; ?>
                <article class="menu-card">
                    <h3><?= e($menu['label'] ?? '') ?></h3>
                    <?php if (!empty($menu['hint'])): ?><p class="menu-hint"><?= e($menu['hint']) ?></p><?php endif; ?>
                    <ul>
                        <?php foreach ($menu['courses'] as $course): ?><li><?= e($course) ?></li><?php endforeach; ?>
                    </ul>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($f['payment']): ?>
    <!-- Kosten -->
    <section class="glass" id="kosten">
        <h2>Kosten und Bezahlung</h2>
        <div class="payment">
            <div>
                <ul class="prices">
                    <li><span>Erwachsene</span><strong><?= e(format_chf((float) cfg('prices.adult'))) ?></strong></li>
                    <?php if ($f['children']): ?>
                        <li><span>Kinder</span><strong><?= e(format_chf((float) cfg('prices.child'))) ?></strong></li>
                    <?php endif; ?>
                </ul>
                <p class="muted">Bezahlung bequem per TWINT: QR Code in der TWINT App scannen und den Betrag für alle angemeldeten Personen überweisen.</p>
                <?php if (cfg('twint.note')): ?><p class="muted"><?= e(cfg('twint.note')) ?></p><?php endif; ?>
            </div>
            <?php if ($twintQr): ?>
                <figure class="qr">
                    <img src="<?= $asset($twintQr) ?>" alt="TWINT QR Code für die Bezahlung" width="220" height="220">
                    <figcaption>TWINT</figcaption>
                </figure>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Anmeldung -->
    <section class="glass" id="anmeldung">
        <div id="closed-box" class="state-box" <?= ($status['status'] === 'open' || $done) ? 'hidden' : '' ?>>
            <div class="state-icon" aria-hidden="true"><?= $status['status'] === 'paused' ? '⏳' : '🏁' ?></div>
            <h2 data-closed-title><?= $status['status'] === 'paused' ? 'Gleich zurück' : 'Anmeldung geschlossen' ?></h2>
            <p data-closed-message><?= e($status['message']) ?></p>
        </div>

        <form id="reg-form" method="post" action="<?= $base ?>#anmeldung" novalidate <?= ($status['status'] !== 'open' || $done) ? 'hidden' : '' ?>>
            <h2>Anmeldung</h2>

            <div class="alert alert-error js-warning" id="js-warning" role="alert">
                <strong>Die Seite ist nicht vollständig geladen.</strong>
                Weitere Personen und Kinder können gerade nicht hinzugefügt werden. Bitte lade die Seite neu. Deine eigene Anmeldung funktioniert trotzdem.
            </div>

            <fieldset class="person-block">
                <legend>Deine Angaben</legend>
                <div class="grid-2">
                    <label class="field">
                        <span>Vorname</span>
                        <input type="text" name="main.first" autocomplete="given-name" required maxlength="60" value="<?= e($old['main_first'] ?? '') ?>">
                    </label>
                    <label class="field">
                        <span>Nachname</span>
                        <input type="text" name="main.last" autocomplete="family-name" required maxlength="60" value="<?= e($old['main_last'] ?? '') ?>">
                    </label>
                </div>
                <label class="field">
                    <span>E-Mail</span>
                    <input type="email" name="email" autocomplete="email" inputmode="email" required maxlength="120" value="<?= e($old['email'] ?? '') ?>">
                </label>
                <?php if ($f['alt_menu']): ?>
                    <div class="menu-choice" data-menu-for="main"></div>
                <?php endif; ?>
            </fieldset>

            <?php if ($f['companion']): ?>
            <div id="companion-wrap"></div>
            <?php endif; ?>

            <?php if ($f['children']): ?>
            <div id="children-wrap"></div>
            <?php endif; ?>

            <?php if ($f['companion'] || $f['children']): ?>
            <div class="add-row">
                <?php if ($f['companion']): ?>
                    <button type="button" class="btn btn-glass" id="add-companion">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> Weitere Person
                    </button>
                <?php endif; ?>
                <?php if ($f['children']): ?>
                    <button type="button" class="btn btn-glass" id="add-child">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> Kind hinzufügen
                    </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="summary" id="summary" aria-live="polite"></div>
            <div class="alert alert-error" id="capacity-error" role="alert" hidden></div>

            <label class="check">
                <input type="checkbox" name="consent" required>
                <span>Ich bin einverstanden, dass meine Angaben ausschliesslich zur Organisation dieses Anlasses gespeichert werden.</span>
            </label>

            <!-- Spamschutz, bleibt für Menschen unsichtbar -->
            <label class="hp" aria-hidden="true">Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>

            <div class="alert alert-error" id="form-error" role="alert" <?= ($post && !$post['ok']) ? '' : 'hidden' ?>><?= e($post['error'] ?? '') ?><?php if (($post['code'] ?? 0) === 422): foreach ($fields as $msg): ?><br><?= e($msg) ?><?php endforeach; endif; ?></div>

            <button type="submit" class="btn btn-primary btn-block" id="submit-btn">Verbindlich anmelden</button>

            <p class="muted small center-text">Bereits angemeldet? Änderungen oder Abmeldungen bitte mit dem Vorstand besprechen<?php if ($contact): ?>: <a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a><?php else: ?>.<?php endif; ?></p>
        </form>

        <div id="success" class="success" <?= $done ? '' : 'hidden' ?> tabindex="-1">
            <div class="success-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
            </div>
            <h2>Vielen Dank für deine Anmeldung!</h2>
            <p class="muted" id="mail-info" <?= !empty($post['mailSent']) ? '' : 'hidden' ?>><?php if (!empty($post['mailSent'])): ?>Eine Bestätigung wurde an <?= e($post['summary']['email']) ?> gesendet. Bitte prüfe allenfalls auch den Spamordner.<?php endif; ?></p>
            <p class="muted">Folgende Personen sind angemeldet:</p>
            <ul class="success-list" id="success-list"><?php if ($done): foreach ($post['summary']['persons'] as $p): ?>
                <li><span><?= e($p['name']) ?><?= $p['type'] === 'child' ? ' <em>(Kind)</em>' : '' ?></span><?php if ($f['alt_menu']): ?><span class="tag"><?= e(cfg('menu.' . $p['menu'] . '.label', $p['menu'])) ?></span><?php endif; ?><?php if ($f['payment']): ?><strong><?= e(format_chf((float) $p['price'])) ?></strong><?php endif; ?></li>
            <?php endforeach; endif; ?></ul>
            <?php if ($f['payment']): ?>
                <div class="success-pay">
                    <p>Zu bezahlen: <strong id="success-total"><?= $done ? e(format_chf((float) $post['summary']['total'])) : '' ?></strong></p>
                    <?php if ($twintQr): ?>
                        <img src="<?= $asset($twintQr) ?>" alt="TWINT QR Code für die Bezahlung" width="200" height="200">
                    <?php endif; ?>
                    <p class="muted small">Der QR Code bleibt oben unter «Kosten und Bezahlung» jederzeit verfügbar.</p>
                </div>
            <?php endif; ?>

            <?php $n = cfg('notice_after_registration', []); if (!empty($n['title']) || !empty($n['text'])): ?>
                <div class="alert alert-notice">
                    <?php if (!empty($n['title'])): ?><strong><?= e($n['title']) ?></strong><?php endif; ?>
                    <?php if (!empty($n['text'])): ?><p><?= e($n['text']) ?></p><?php endif; ?>
                    <?php if (!empty($n['link'])): ?>
                        <a class="btn btn-primary" href="<?= e($n['link']) ?>"><?= e($n['link_label'] ?: 'Zur Anmeldung') ?></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <p class="muted small">Änderungen oder Abmeldungen bitte mit dem Vorstand besprechen<?php if ($contact): ?>: <a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a><?php else: ?>.<?php endif; ?></p>

            <div class="hero-actions center">
                <?php if ($f['calendar']): ?>
                    <a href="<?= $base ?>calendar.php" class="btn btn-primary" download>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11m0 0-4-4m4 4 4-4M5 20h14"/></svg>
                        Kalendereintrag herunterladen
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<footer class="wrap footer">
    <p>© <?= date('Y') ?> <?= e(cfg('club.name')) ?><?php if (cfg('club.contact_email')): ?> · <a href="mailto:<?= e(cfg('club.contact_email')) ?>"><?= e(cfg('club.contact_email')) ?></a><?php endif; ?></p>
</footer>

<script type="application/json" id="app-config"><?= json_encode(public_config() + ['status' => $status, 'base' => base_path()], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= $asset('assets/js/app.js') ?>"></script>
</body>
</html>
