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
        'logo'          => '',                        // TODO: z.B. 'assets/img/logo.png' (oben links, 5x klicken = Admin Login; leer = Textlogo «DOC»)
        'contact_email' => 'info@doc-bern.ch',        // Kontakt für Fragen und Änderungen
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
        'location_url' => '',                         // Website des Lokals (öffnet beim Klick auf den Namen in neuem Tab)
        'description' => 'Wir freuen uns auf einen gemütlichen Abend mit dir.',
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
        'companion'    => true,   // weitere erwachsene Person (max. 1, ohne E-Mail)
        'children'     => true,   // Kinder hinzufügen
        'max_children' => 8,
        'alt_menu'     => false,  // Menüwahl pro Person (Standard / Alternativ)
        'payment'      => false,  // Kosten und TWINT QR Code anzeigen
        'calendar'     => true,   // Kalendereintrag (.ics) anbieten
        'waitlist'     => true,   // Warteliste, sobald ausgebucht oder geschlossen
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
        'qr'   => 'assets/img/twint_code_big.PNG',   // TODO: QR Code mit genau diesem Namen hochladen (Gross/Kleinschreibung beachten). Fehlt die Datei, erscheint ein Platzhalter.
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
