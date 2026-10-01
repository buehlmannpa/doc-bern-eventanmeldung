# Checkliste für die Produktion

Diese Anleitung beschreibt alle Schritte, um die Anmeldeseite (z.B. Weihnachtsessen) produktiv auf Hostpoint zu betreiben.
Alle Einstellungen stehen in **einer einzigen Datei**: `config/event.php`.

Stellen, die noch angepasst werden müssen, sind in der Datei mit `TODO` markiert.

---

## 1. Richtige Version herunterladen

1. Auf GitHub das Repository öffnen und oben links den Branch wählen, z.B. `weihnachtsessen`.
2. **Code → Download ZIP** klicken und das ZIP entpacken.
3. Die folgenden Schritte in diesen entpackten Dateien erledigen und am Schluss alles hochladen.

## 2. Club Logo (oben links)

1. Logo als **PNG mit transparentem Hintergrund** oder als **SVG** speichern.
   * Empfohlen: mindestens 80 Pixel hoch (wird mit 40 Pixel Höhe angezeigt, doppelte Auflösung für scharfe Darstellung auf Smartphones).
   * Das Logo sollte auf hellem **und** dunklem Hintergrund gut aussehen (die Seite wechselt je nach Geräteeinstellung).
2. Datei in den Ordner `assets/img/` legen, z.B. `assets/img/logo.png`.
3. In `config/event.php` eintragen:
   ```php
   'club' => [
       ...
       'logo' => 'assets/img/logo.png',
   ```
4. Das Logo erscheint oben links auf der Anmeldeseite und im CMS.
5. **Easter Egg:** 5x schnell hintereinander auf das Logo klicken (bzw. tippen) öffnet das Login zum Admin Portal.

Das Logo wird automatisch auch als Symbol im Browser Tab (Favicon) und auf dem Smartphone Startbildschirm verwendet. Am besten wirkt dafür ein quadratisches Logo.

## 3. TWINT QR Code

1. Den QR Code, den ihr für euer TWINT Konto erhalten habt, als Bild speichern.
   * **Format PNG oder JPG** verwenden. Ein SVG wird auf der Webseite angezeigt, in der Bestätigungsmail aber von vielen Mailprogrammen nicht.
   * Quadratisch, mindestens 400 × 400 Pixel.
2. Datei nach `assets/img/twint-qr.png` hochladen.
3. In `config/event.php` eintragen:
   ```php
   'twint' => [
       'qr'   => 'assets/img/twint-qr.png',
       'note' => 'Bitte bei der Zahlung Vor und Nachname angeben.',
   ],
   ```
4. Den Hinweistext (`note`) nach Wunsch anpassen.
5. Den Platzhalter `assets/img/twint-qr.svg` kann man danach löschen.

## 4. Inhalte des Anlasses

In `config/event.php` im Bereich `event`:

| Feld | Beispiel | Bemerkung |
|---|---|---|
| `title` | `Weihnachtsessen 2026` | Titel auf der Seite und in der Mail |
| `date` | `2026-12-05` | Format JJJJ-MM-TT |
| `start` / `end` | `19:30` / `24:00` | für Anzeige und Kalendereintrag |
| `location` | `Restaurant Bären` | Name des Lokals |
| `address` | `Bahnhofstrasse 1, 3000 Bern` | erscheint auch im Kalendereintrag |
| `description` | Einladungstext | frei wählbar |

Weitere Punkte:

* **Menü:** unter `menu` die Gänge für `standard` und `alternative` eintragen (je eine Zeile pro Gang). Die Bezeichnungen (`label`) sind frei wählbar, z.B. «Vegetarisches Menü».
* **Ablauf:** unter `program` die Zeiten und Programmpunkte prüfen.
* **Preise:** unter `prices` prüfen (aktuell Erwachsene 51, Kinder 25 CHF).
* **Limit:** `capacity` (aktuell 80 Personen). Kann im CMS jederzeit übersteuert werden.
* **Anmeldeschluss (optional):** `deadline`, z.B. `'2026-11-28 23:59'`. Danach schliesst die Anmeldung automatisch.

## 5. Verweis auf die andere Anmeldung

Nach der Anmeldung zum Weihnachtsessen erscheint ein Hinweis auf die GV (und umgekehrt). Damit der Knopf dort funktioniert, die Adresse der jeweils anderen Anmeldeseite eintragen:

```php
'notice_after_registration' => [
    ...
    'link' => 'https://www.doc-bern.ch/gv/',
```

## 6. Kontakt und Bestätigungsmail

* `club.contact_email`: Kontaktadresse für Änderungen und Abmeldungen (aktuell `info@doc-bern.ch`).
* `mail.from`: Absender der Bestätigung. Die Adresse muss als Mailbox **bei Hostpoint** existieren, sonst landen die Mails im Spam.
* `mail.bcc` (optional): Adresse, die eine Kopie jeder Bestätigung erhält, z.B. `info@doc-bern.ch`.
* Der Versand läuft über den Webserver von Hostpoint, es wird **kein Passwort** hinterlegt.

## 7. Sicherheit

1. **Admin Passwort:** Nach dem Hochladen `https://…/admin/` aufrufen, ein Passwort wählen (mindestens 12 Zeichen) und den angezeigten Hash in `config/event.php` bei `admin_password_hash` eintragen. Datei erneut hochladen.
2. **SSL:** Im Hostpoint Control Panel das kostenlose SSL Zertifikat (Let's Encrypt) für die Domain aktivieren. Die Seite leitet automatisch auf `https://` um.
3. **Datenordner ausserhalb des Web Verzeichnisses (empfohlen):**
   ```php
   'data_dir' => dirname(__DIR__, 2) . '/doc-daten/weihnachtsessen',
   ```
   Der Ordner wird beim ersten Aufruf automatisch erstellt. Für jede Anmeldeseite (Weihnachtsessen, GV) einen eigenen Ordnernamen verwenden.

## 8. Hochladen

1. Pro Anlass einen eigenen Ordner auf dem Webserver verwenden, z.B. `/weihnachtsessen/` und `/gv/`.
2. Alle Dateien und Ordner hochladen, **inklusive** der unsichtbaren `.htaccess` Dateien (im FTP Programm «versteckte Dateien anzeigen» aktivieren).
3. Den Ordner `data/` **nicht** vom Testserver übernehmen, sonst sind die Testanmeldungen im Produktivsystem.

## 9. Testdaten entfernen

Falls auf dem gleichen Server getestet wurde:

* im CMS alle Testanmeldungen löschen, **oder**
* per FTP die Dateien `registrations.json.php` und `state.json.php` im Datenordner löschen.

## 10. Schlusstest vor der Freigabe

- [ ] Seite auf dem Smartphone und am Laptop öffnen (hell und dunkel)
- [ ] Logo oben links sichtbar, 5 Klicks öffnen das Admin Login
- [ ] Datum, Ort, Ablauf, Menü und Preise stimmen
- [ ] TWINT QR Code wird angezeigt und lässt sich mit der TWINT App scannen
- [ ] Testanmeldung mit eigener Adresse inklusive weiterer Person und Kind
- [ ] Bestätigungsmail kommt an (auch Spamordner prüfen), QR Code und Kalender Anhang sind enthalten
- [ ] Kalendereintrag lässt sich nach der Anmeldung herunterladen
- [ ] Link auf die andere Anmeldung (GV bzw. Weihnachtsessen) funktioniert
- [ ] CMS: Anmeldung sichtbar, Rolle setzen, Bezahlt markieren, Excel Export öffnen
- [ ] CMS: Status «Ausgebucht» testen (roter Hinweis oben, kein Anmeldeknopf) und «Wir sind gleich zurück» (nur die Meldung), danach wieder auf «Offen» stellen
- [ ] Testanmeldung im CMS löschen

## 11. Während und nach dem Anlass

* **Backup:** regelmässig im CMS den **Excel Export** herunterladen.
* **Datenschutz:** Nach dem Anlass (und nach Abschluss der Zahlungen) die Anmeldedaten löschen: im CMS oder per FTP den Datenordner leeren.
