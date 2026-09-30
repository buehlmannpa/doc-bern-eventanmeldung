<?php
/**
 * Konfiguration des Anlasses.
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
        'id'          => 'doc-bern-anlass',           // eindeutige Kennung (für Kalender)
        'title'       => 'Clubanlass DOC Bern',
        'subtitle'    => 'Melde dich jetzt an',
        'date'        => '2026-12-05',                // JJJJ-MM-TT
        'start'       => '18:00',
        'end'         => '23:00',
        'location'    => 'Ort folgt',
        'address'     => '',
        'description' => 'Wir freuen uns auf einen gemütlichen Abend mit dir.',
    ],

    // Maximale Anzahl Personen (Erwachsene und Kinder zählen je als 1 Platz)
    'capacity' => 80,

    // Anmeldeschluss (leer = kein Anmeldeschluss). Format: 'JJJJ-MM-TT HH:MM'
    'deadline' => '',

    // ------------------------------------------------------------------
    // Funktionen
    // ------------------------------------------------------------------
    'features' => [
        'companion'    => true,   // weitere erwachsene Person (max. 1, ohne E-Mail)
        'children'     => true,   // Kinder hinzufügen
        'max_children' => 8,
        'alt_menu'     => false,  // Menüwahl pro Person (Standard / Alternativ)
        'payment'      => false,  // Kosten und TWINT QR Code anzeigen
        'calendar'     => true,   // Kalendereintrag (.ics) anbieten
    ],

    // ------------------------------------------------------------------
    // Inhalte (werden nur angezeigt, wenn ausgefüllt bzw. aktiviert)
    // ------------------------------------------------------------------

    // Ablauf des Abends: ['time' => '17:00 bis 19:00', 'title' => '...', 'text' => '...']
    'program' => [],

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
        'title'      => '',
        'text'       => '',
        'link'       => '',
        'link_label' => '',
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
    'theme' => '',

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
