# DOC Bern Eventanmeldung

Schlanke Anmeldeseite mit eigenem Mini CMS für Anlässe des Ducati Owners Club Bern.
Läuft auf jedem Standard Webhosting mit PHP (z.B. Hostpoint), **ohne Datenbank und ohne Serverkonfiguration**.

## Funktionen

**Anmeldeseite**
* Responsive Design für Smartphone und Laptop (Touch und Maus), Liquid Glass Look, Ducati Farben
* Helles Design, automatisch dunkel gemäss Systemeinstellung des Geräts
* Belegungsbalken mit Live Aktualisierung (alle 30 Sekunden)
* Hauptperson mit Vor und Nachname sowie E-Mail
* Optional eine weitere Person (ohne E-Mail) und beliebig viele Kinder
* Optional Menüwahl pro Person (Standard oder Alternativ)
* Limit (Standard 80 Personen, Erwachsene und Kinder zählen je als 1 Platz). Wer mehr Personen anmelden will als Plätze frei sind, erhält sofort eine Meldung und kann nicht absenden. Der Server prüft das zusätzlich unter Sperre, damit auch gleichzeitige Anmeldungen nie überbuchen.
* Automatisches Schliessen, sobald das Limit erreicht ist
* Kalendereintrag (.ics) zum Herunterladen nach erfolgreicher Anmeldung
* Anmeldung funktioniert auch, wenn JavaScript blockiert ist (dann nur für die Hauptperson). Ein Hinweis erscheint in diesem Fall automatisch.
* Optional Kosten und TWINT QR Code (bleibt immer sichtbar)
* Optional Hinweis nach der Anmeldung (z.B. «Bitte auch für die GV anmelden»)
* Bestätigungsmail von info@doc-bern.ch mit Personenliste, Betrag, TWINT QR Code und Kalendereintrag im Anhang
* Mehrfachanmeldung mit derselben E-Mail ist gesperrt: «Du bist bereits angemeldet. Änderungen an deiner Anmeldung bitte mit dem Vorstand besprechen.»

**CMS** (`/admin`)
* Passwortgeschützt, Sperre nach 5 Fehlversuchen, CSRF Schutz
* Übersicht mit Kennzahlen und Auswertungen (Belegung, Erwachsene und Kinder, Menüwahl, Rollen, Bezahlstatus, Anmeldungen pro Tag)
* Anmeldungen ansehen, suchen, filtern, bearbeiten, löschen und manuell hinzufügen
* Rollen pro Person (Vorstand, Ehrenmitglied) mit eigener Farbe, diese Personen bezahlen nichts
* Bezahlstatus pro Anmeldung
* Anmeldung manuell schliessen («ausgebucht») oder pausieren («Wir sind gleich zurück»), mit eigenem Text
* Limit im CMS übersteuerbar
* CSV Export für Excel
* Bestätigungsmail beim manuellen Hinzufügen senden oder jederzeit erneut senden

## Aufbau

```
index.php              Anmeldeseite
api.php                Öffentliche API (Status, Anmeldung)
calendar.php           Kalendereintrag
admin/                 CMS
config/event.php       <<< EINZIGE Datei, die pro Anlass angepasst wird
config/beispiele/      Fertige Konfigurationen für Weihnachtsessen und GV
assets/css/base.css    Grunddesign
assets/css/themes/     Zusatzdesign pro Anlass (z.B. weihnachtsessen.css)
assets/img/            Logo, Favicon, TWINT QR Code
data/                  Anmeldedaten (wird automatisch erstellt, nicht im Git)
```

## Varianten (Branches)

Die Anwendung ist modular: Alle Unterschiede zwischen den Anlässen stecken in `config/event.php` und im Theme.

| Branch | Konfiguration | Besonderheiten |
|---|---|---|
| `main` | `config/event.php` | Standardversion |
| `weihnachtsessen` | `config/beispiele/weihnachtsessen.php` | Menü und Alternativmenü, Ablauf des Abends, Kosten (CHF 51 und CHF 25), TWINT QR Code, Hinweis auf GV Anmeldung |
| `generalversammlung` | `config/beispiele/generalversammlung.php` | Nur Mitglied (keine weitere Person, keine Kinder), Hinweis auf Anmeldung Weihnachtsessen |

Neuen Branch vorbereiten: Beispieldatei nach `config/event.php` kopieren und die mit `TODO` markierten Stellen ausfüllen (Datum, Ort, Menü, Link zur jeweils anderen Anmeldung).

## Installation auf Hostpoint

1. Pro Anlass einen eigenen Ordner bzw. eine Subdomain anlegen, z.B. `weihnachtsessen.doc-bern.ch` und `gv.doc-bern.ch`. Jede Installation hat eigene Daten.
2. Alle Dateien per FTP (oder Hostpoint Dateimanager) in den Ordner hochladen.
3. SSL Zertifikat (Let's Encrypt, kostenlos) im Hostpoint Control Panel aktivieren. Die `.htaccess` leitet danach automatisch auf HTTPS um.
4. `https://…/admin` aufrufen, ein Passwort wählen (mind. 12 Zeichen) und den angezeigten Hash in `config/event.php` bei `admin_password_hash` eintragen. Datei erneut hochladen.
5. Eigenen TWINT QR Code als Bild nach `assets/img/` hochladen und den Pfad bei `twint.qr` eintragen.
6. Optional ein Logo nach `assets/img/` hochladen und bei `club.logo` eintragen.

PHP Version: 8.0 oder neuer (bei Hostpoint Standard).

## Bestätigungsmail

Der Versand läuft über die Mailfunktion des Hostpoint Webservers. Es muss **kein Passwort** hinterlegt werden.

* Absender ist `info@doc-bern.ch` (Einstellung `mail` in `config/event.php`). Die Adresse muss als Mailbox bei Hostpoint existieren und die Domain doc-bern.ch muss bei Hostpoint liegen. Nur so erkennen die Empfänger die Mail als echt (SPF) und sie landet nicht im Spam.
* Antworten auf die Bestätigung gehen an `reply_to` (Standard info@doc-bern.ch).
* Mit `bcc` erhält der Vorstand eine Kopie jeder Bestätigung.
* Der TWINT QR Code wird nur als PNG oder JPG in die Mail eingebettet (SVG zeigen viele Mailprogramme nicht an).
* Schlägt der Versand fehl, bleibt die Anmeldung trotzdem gültig. Im CMS ist sichtbar, ob und wann eine Bestätigung gesendet wurde.
* Ausschalten: `'enabled' => false`.
* **Tipp für den ersten Test:** eine Anmeldung mit der eigenen Adresse machen und prüfen, ob die Mail ankommt. Danach im CMS löschen.

## Datenspeicherung und Sicherheit

Die Anmeldungen werden als JSON in `data/registrations.json.php` gespeichert.

* Jede Datendatei beginnt mit einem PHP Abbruch. Selbst wenn der Webserver die `.htaccess` ignorieren würde, liefert er nur einen leeren 404 Fehler aus.
* Zusätzlich sperren `.htaccess` Dateien die Ordner `data/`, `config/` und `lib/`.
* **Empfehlung:** Den Datenordner ausserhalb des Web Verzeichnisses ablegen. Bei Hostpoint liegt die Website z.B. in `~/www/weihnachtsessen.doc-bern.ch/`. In `config/event.php`:
  ```php
  'data_dir' => dirname(__DIR__, 2) . '/doc-daten/weihnachtsessen',
  ```
  Der Ordner wird automatisch erstellt.
* Schreibzugriffe laufen unter exklusiver Dateisperre (keine Überbuchung, keine beschädigten Daten).
* Spamschutz über ein unsichtbares Feld und ein Limit von 10 Anmeldungen pro Stunde und IP Adresse.
* Das Admin Passwort wird nur als Hash gespeichert.
* Keine externen Schriften oder Skripte (keine Datenübermittlung an Dritte, nDSG freundlich).

**Backup:** Im CMS regelmässig den CSV Export herunterladen oder den Datenordner per FTP sichern.

## Lokal testen

```bash
php -S 127.0.0.1:8080
```

Danach `http://127.0.0.1:8080` bzw. `http://127.0.0.1:8080/admin` öffnen.
Hinweis: Der PHP Testserver beachtet keine `.htaccess` Dateien.
