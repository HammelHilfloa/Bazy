# Vereinskalender 2026

Kleines, datenbankloses Kalender-Projekt für Netcup Webhosting. Frontend: statisches HTML/CSS/JS, Backend: PHP-APIs mit JSON-Datei als Speicher.

## Projektstruktur
```
public/          # Statisches Frontend (index.html, app.js, styles.css, router.php, .htaccess)
api/             # PHP-Endpunkte (events, holidays, csrf, health)
data/            # JSON-Daten + Backups (events_2026.json, backups/)
cache/           # Feiertags-/Ferien-Cache (holidays_2026_DE-NW.json)
public/api/      # Schlanke Proxy-Skripte, damit /api auch bei reinem /public-Document-Root erreichbar ist
```

## Lokal testen
1. PHP Built-in Server starten (root des Repos):
   ```bash
   php -S localhost:8000 -t public public/router.php
   ```
   Der Front-Controller in `public/router.php` sorgt dafür, dass `/public` als Webroot genutzt wird und die `/api`-Skripte
   parallel dazu erreichbar bleiben.
2. Browser öffnen: http://localhost:8000/
3. Änderungen an Terminen per Drag & Drop oder Formular vornehmen. Speichern erfolgt automatisch via `api/events.php`.

## Deployment auf Netcup/Plesk
1. Dokumentenstamm (Document Root) auf das Verzeichnis `public/` zeigen lassen.
2. `.htaccess` in `public/` benötigt aktiviertes `mod_rewrite` (Standard bei Plesk) und leitet alle Requests an `public/router.php`.
3. Ordnerrechte setzen, sodass PHP in `data/`, `data/backups/` und `cache/` schreiben darf (z.B. 775 bzw. Webserver-User als Besitzer).
4. Die `/api`-Skripte liegen parallel zu `public/` und werden vom Router per Whitelist eingebunden – es erfolgt keine direkte Auslieferung aus dem Document Root.
5. Beispiel-URLs nach Deployment:
   - https://calender.familie-bazynski.de/api/health.php
   - https://calender.familie-bazynski.de/api/holidays.php?year=2026
   - https://calender.familie-bazynski.de/api/events.php

## Sicherheit & CSRF
- CSRF-Token wird von `api/csrf.php` erzeugt und muss als Header `X-CSRF-Token` bei POST auf `api/events.php` mitgeschickt werden.
- Der Passwortschutz (Plesk) schützt den gesamten Kalender-Ordner zusätzlich per HTTP-Auth.

## Smoke-Test
Ein kleines Skript prüft die wichtigsten Funktionen. Voraussetzung: PHP-Server läuft lokal auf `http://localhost:8000`.

```bash
php smoke-test.php
```
Der Test verifiziert:
- GET `api/events.php` liefert Daten
- POST `api/events.php` speichert und legt ein Backup in `data/backups/` an (Originaldaten werden danach wiederhergestellt)
- `api/holidays.php` legt den Cache an und liefert bei erneutem Aufruf die gecachten Daten
```
