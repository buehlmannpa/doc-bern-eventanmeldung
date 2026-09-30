<?php
/**
 * Konfiguration des Anlasses.
 *
 * BEISPIEL: Generalversammlung. Für den Branch 'generalversammlung' nach config/event.php kopieren.
 *
 * Dies ist die EINZIGE Datei, die pro Anlass (bzw. pro Branch) angepasst werden muss.
 * Beispiele für Weihnachtsessen und Generalversammlung liegen in config/beispiele/.
 */
return [

    // ------------------------------------------------------------------
    // Club
    // ------------------------------------------------------------------
    'club' => [
        'name'          => 'Ducati Owners Club Bern',
        'short'         => 'DOC Bern',
        'logo'          => '',                        // z.B. 'assets/img/logo.svg' (leer = Textlogo)
        'contact_email' => 'info@example.ch',         // wird bei Fehlern und im Footer angezeigt
        'website'       => '',
    ],

    // ------------------------------------------------------------------
    // Anlass
    // ------------------------------------------------------------------
    'event' => [
        'id'          => 'doc-bern-generalversammlung',
        'title'       => 'Generalversammlung 2026',
        'subtitle'    => 'Ordentliche Generalversammlung des DOC Bern',
        'date'        => '2026-12-05',                // TODO: effektives Datum eintragen
        'start'       => '17:00',
        'end'         => '19:00',
        'location'    => 'Ort folgt',                 // TODO
        'address'     => '',                          // TODO
        'description' => 'Die Einladung mit Traktandenliste wird allen Mitgliedern separat zugestellt.',
    ],

    // Maximale Anzahl Personen (Erwachsene und Kinder zählen je als 1 Platz)
    'capacity' => 80,

    // Anmeldeschluss (leer = kein Anmeldeschluss). Format: 'JJJJ-MM-TT HH:MM'
    'deadline' => '',

    // ------------------------------------------------------------------
    // Funktionen
    // ------------------------------------------------------------------
    'features' => [
        'companion'    => false,  // an der GV nur Mitglieder
        'children'     => false,
        'max_children' => 0,
        'alt_menu'     => false,
        'payment'      => false,
        'calendar'     => true,
    ],

    // ------------------------------------------------------------------
    // Inhalte (werden nur angezeigt, wenn ausgefüllt bzw. aktiviert)
    // ------------------------------------------------------------------

    // Ablauf des Abends: ['time' => '17:00 bis 19:00', 'title' => '...', 'text' => '...']
    'program' => [
        ['time' => '17:00 bis 19:00', 'title' => 'Generalversammlung DOC Bern'],
        ['time' => 'ab 19:30',        'title' => 'DOC Weihnachtsessen', 'text' => 'Separate Anmeldung erforderlich'],
    ],

    // Menü (nur relevant, wenn 'alt_menu' aktiv ist oder ein Menü angezeigt werden soll)
    'menu' => [
        'standard'    => ['label' => 'Menü',              'courses' => []],
        'alternative' => ['label' => 'Alternatives Menü', 'courses' => []],
    ],

    // Preise (nur relevant, wenn 'payment' aktiv ist)
    'prices' => [
        'currency' => 'CHF',
        'adult'    => 0,
        'child'    => 0,
    ],

    'twint' => [
        'qr'   => 'assets/img/twint-qr.svg',          // eigenes Bild hier ablegen (PNG, JPG oder SVG)
        'note' => 'Bitte bei der Zahlung Vor und Nachname angeben.',
    ],

    // Hinweis nach erfolgreicher Anmeldung (z.B. Verweis auf zweite Anmeldung)
    'notice_after_registration' => [
        'title'      => 'Nicht vergessen: Anmeldung zum Weihnachtsessen',
        'text'       => 'Das Weihnachtsessen im Anschluss an die GV hat eine separate Anmeldung. Bitte melde dich dort ebenfalls an, falls du teilnimmst.',
        'link'       => '',                             // TODO: URL der Anmeldung Weihnachtsessen
        'link_label' => 'Zur Anmeldung Weihnachtsessen',
    ],

    // ------------------------------------------------------------------
    // Rollen im CMS (farbliche Kennzeichnung, 'free' = bezahlt nichts)
    // ------------------------------------------------------------------
    'roles' => [
        'vorstand'      => ['label' => 'Vorstand',      'color' => '#3987e5', 'free' => true],
        'ehrenmitglied' => ['label' => 'Ehrenmitglied', 'color' => '#d4a017', 'free' => true],
    ],

    // ------------------------------------------------------------------
    // Darstellung
    // ------------------------------------------------------------------
    // Lädt zusätzlich assets/css/themes/<theme>.css (leer = Standarddesign)
    'theme' => 'generalversammlung',

    // ------------------------------------------------------------------
    // Technik
    // ------------------------------------------------------------------

    // Speicherort der Anmeldedaten. Empfohlen: ausserhalb des Web Verzeichnisses,
    // z.B. dirname(__DIR__, 2) . '/doc-daten/weihnachtsessen'
    'data_dir' => dirname(__DIR__) . '/data',

    // Passwort Hash für das CMS. Leer lassen und /admin aufrufen:
    // dort kann der Hash erzeugt und hier eingefügt werden.
    'admin_password_hash' => '',

    'timezone' => 'Europe/Zurich',
];
