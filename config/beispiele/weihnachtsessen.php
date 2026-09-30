<?php
/**
 * Konfiguration des Anlasses.
 *
 * BEISPIEL: Weihnachtsessen. Für den Branch 'weihnachtsessen' nach config/event.php kopieren.
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
        'id'          => 'doc-bern-weihnachtsessen',
        'title'       => 'Weihnachtsessen 2026',
        'subtitle'    => 'Der festliche Jahresabschluss des DOC Bern',
        'date'        => '2026-12-05',                // TODO: effektives Datum eintragen
        'start'       => '19:30',
        'end'         => '24:00',
        'location'    => 'Restaurant folgt',          // TODO
        'address'     => '',                          // TODO
        'description' => 'Wir lassen das Töffjahr gemeinsam ausklingen und freuen uns auf einen gemütlichen Abend mit dir, deiner Begleitung und deinen Kindern.',
    ],

    // Maximale Anzahl Personen (Erwachsene und Kinder zählen je als 1 Platz)
    'capacity' => 80,

    // Anmeldeschluss (leer = kein Anmeldeschluss). Format: 'JJJJ-MM-TT HH:MM'
    'deadline' => '',

    // ------------------------------------------------------------------
    // Funktionen
    // ------------------------------------------------------------------
    'features' => [
        'companion'    => true,
        'children'     => true,
        'max_children' => 8,
        'alt_menu'     => true,
        'payment'      => true,
        'calendar'     => true,
    ],

    // ------------------------------------------------------------------
    // Inhalte (werden nur angezeigt, wenn ausgefüllt bzw. aktiviert)
    // ------------------------------------------------------------------

    // Ablauf des Abends: ['time' => '17:00 bis 19:00', 'title' => '...', 'text' => '...']
    'program' => [
        ['time' => '17:00 bis 19:00', 'title' => 'Generalversammlung DOC Bern', 'text' => 'Separate Anmeldung erforderlich'],
        ['time' => '19:30 bis 24:00', 'title' => 'DOC Weihnachtsessen',          'text' => 'Apéro, Essen und gemütliches Beisammensein'],
    ],

    // Menü (nur relevant, wenn 'alt_menu' aktiv ist oder ein Menü angezeigt werden soll)
    'menu' => [
        'standard' => [
            'label'   => 'Weihnachtsmenü',
            'courses' => [                              // TODO: Menü eintragen
                'Vorspeise folgt',
                'Hauptgang folgt',
                'Dessert folgt',
            ],
        ],
        'alternative' => [
            'label'   => 'Vegetarisches Menü',
            'hint'    => 'Bitte bei der Anmeldung pro Person auswählen.',
            'courses' => [                              // TODO: Alternativmenü eintragen
                'Vorspeise folgt',
                'Hauptgang folgt',
                'Dessert folgt',
            ],
        ],
    ],

    // Preise (nur relevant, wenn 'payment' aktiv ist)
    'prices' => [
        'currency' => 'CHF',
        'adult'    => 51,
        'child'    => 25,
    ],

    'twint' => [
        'qr'   => 'assets/img/twint-qr.svg',          // eigenes Bild hier ablegen (PNG, JPG oder SVG)
        'note' => 'Bitte bei der Zahlung Vor und Nachname angeben.',
    ],

    // Hinweis nach erfolgreicher Anmeldung (z.B. Verweis auf zweite Anmeldung)
    'notice_after_registration' => [
        'title'      => 'Nicht vergessen: Anmeldung zur Generalversammlung',
        'text'       => 'Die Generalversammlung (17:00 bis 19:00 Uhr) hat eine separate Anmeldung. Bitte melde dich dort ebenfalls an, falls du teilnimmst.',
        'link'       => '',                             // TODO: URL der GV Anmeldung, z.B. https://gv.doc-bern.ch
        'link_label' => 'Zur Anmeldung Generalversammlung',
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
    'theme' => 'weihnachtsessen',

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
