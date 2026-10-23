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
        'logo'          => '',                        // TODO: z.B. 'assets/img/logo.png' (oben links, 5x klicken = Admin Login; leer = Textlogo «DOC»)
        'contact_email' => 'info@doc-bern.ch',        // Kontakt für Fragen und Änderungen
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
        'location_url' => '',                         // Website des Lokals (öffnet beim Klick auf den Namen in neuem Tab)
        'description' => 'Wir lassen das Töffjahr gemeinsam ausklingen und freuen uns auf einen gemütlichen Abend mit dir, deiner Begleitung und deinen Kindern.',
    ],

    // Maximale Anzahl Personen (Erwachsene und Kinder zählen je als 1 Platz)
    'capacity' => 80,

    // Anmeldeschluss (leer = kein Anmeldeschluss). Format: 'JJJJ-MM-TT HH:MM'
    // Gilt auch als Zahlungsfrist: Wer bis dann nicht bezahlt hat, wird im CMS als «Zahlung überfällig» markiert.
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
        'waitlist'     => true,   // Warteliste, sobald ausgebucht oder geschlossen
    ],

    // ------------------------------------------------------------------
    // Inhalte (werden nur angezeigt, wenn ausgefüllt bzw. aktiviert)
    // ------------------------------------------------------------------

    // Ablauf des Abends: ['time' => '17:00 bis 19:00', 'title' => '...', 'text' => '...']
    'program' => [
        ['time' => '17:00 bis 19:00', 'title' => 'Generalversammlung DOC Bern', 'text' => 'Separate Anmeldung erforderlich'],
        ['time' => '19:30 bis 24:00', 'title' => 'DOC Weihnachtsessen',          'text' => 'Apéro, Essen und gemütliches Beisammensein'],
    ],

    // Menüs (Auswahl pro Person nur, wenn 'alt_menu' aktiv ist)
    // Pro Menü: Bezeichnung, Preis und optional die Gänge (werden auf der Seite angezeigt).
    // Die Schlüssel (z.B. 'standard', 'burger') nach dem Start der Anmeldung nicht mehr ändern,
    // sonst passen bestehende Anmeldungen nicht mehr zum Menü.
    'menus' => [
        'adult' => [
            'standard' => [
                'label'   => 'Weihnachtsmenü',
                'price'   => 52,
                'courses' => [                          // TODO: Menü eintragen
                    'Vorspeise folgt',
                    'Hauptgang folgt',
                    'Dessert folgt',
                ],
            ],
            'alternative' => [
                'label'   => 'Vegetarisches Menü',
                'price'   => 49,
                'courses' => [                          // TODO: Vegi Menü eintragen
                    'Vorspeise folgt',
                    'Hauptgang folgt',
                    'Dessert folgt',
                ],
            ],
        ],
        'child' => [
            'burger'  => ['label' => 'Burger und Pommes',          'price' => 16],
            'nuggets' => ['label' => 'Chicken Nuggets und Pommes', 'price' => 16],
            'pasta'   => ['label' => 'Teigwaren mit Tomatensauce', 'price' => 11],
        ],
    ],

    // Preise (nur relevant, wenn 'payment' aktiv ist)
    // Preise stehen pro Menü (siehe 'menus'). Diese Werte gelten nur, wenn bei einem Menü kein Preis steht.
    'prices' => [
        'currency' => 'CHF',
        'adult'    => 52,
        'child'    => 16,
    ],

    'twint' => [
        'qr'   => 'assets/img/twint_code_big.PNG',   // TODO: QR Code mit genau diesem Namen hochladen (Gross/Kleinschreibung beachten). Fehlt die Datei, erscheint ein Platzhalter.
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
    // Bestätigungsmail
    // ------------------------------------------------------------------
    // Versand über den Webserver (PHP mail()), bei Hostpoint ohne Einrichtung möglich.
    // Absender muss eine Adresse der eigenen Domain sein, die bei Hostpoint läuft.
    'mail' => [
        'enabled'   => true,
        'from'      => 'info@doc-bern.ch',
        'from_name' => 'Ducati Owners Club Bern',
        'reply_to'  => 'info@doc-bern.ch',            // Antworten auf die Bestätigung landen hier
        'bcc'       => '',                            // optional Kopie jeder Bestätigung, z.B. 'info@doc-bern.ch'
    ],

    // Öffentliche Adresse der Anmeldeseite (leer = automatisch erkennen)
    'site_url' => '',

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
