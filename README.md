# Vereinskalender 2026

Kleines, datenbankloses Kalender-Projekt für Netcup Webhosting. Frontend: statisches HTML/CSS/JS, Backend: PHP-APIs mit JSON-Datei als Speicher.

## Projektstruktur
```
public/          # Statisches Frontend (index.html, app.js, styles.css)
api/             # PHP-Endpunkte (events, holidays, csrf)
data/            # JSON-Daten + Backups (events_2026.json, backups/)
cache/           # Feiertags-/Ferien-Cache (holidays_2026.json)
```

## Lokal testen
1. PHP Built-in Server starten (root des Repos):
   ```bash
   php -S localhost:8000 router.php
   ```
   Der Router sorgt dafür, dass `/public` als Webroot genutzt und `/api` korrekt aufgelöst wird.
2. Browser öffnen: http://localhost:8000/public/
3. Änderungen an Terminen per Drag & Drop oder Formular vornehmen. Speichern erfolgt automatisch via `api/events.php`.

## Deployment auf Netcup
1. Ordner-Inhalt nach `httpdocs/kalender/` hochladen (Struktur beibehalten).
2. In Plesk für das Verzeichnis `kalender/` den Passwortschutz aktivieren ("Password protected directories"), damit der gesamte Ordner geschützt ist.
3. Dateirechte setzen, sodass PHP in `data/` und `cache/` schreiben darf (z.B. 775 bzw. Webserver-User als Besitzer).
4. Aufruf anschließend über `https://<your-domain>/kalender/public/`.

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
