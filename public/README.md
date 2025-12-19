# Vereinskalender

Ein schlankes PHP/MySQL-Projekt ohne Framework oder Composer. Ziel ist ein Login-geschützter Vereinskalender mit JSON-API, Kategorien, Serienterminen, Druckansichten, OpenHolidays-Import, Benutzerverwaltung und Audit-Log.

## Struktur
- `public/index.php`: Redirect zur Kalender-App.
- `public/app/`: Platz für UI/Logik (Start mit `calendar.php`).
- `public/api/`: Endpunkte für JSON-API.
- `public/lib/`: Gemeinsame Bibliotheken (`Db`, `Response`, `Util`, `bootstrap`).
- `public/config/`: Konfiguration (siehe Beispiel).
- `public/database/`: SQL-Skripte und Migrationsdateien.
- `public/assets/`: Statische Dateien.
- `public/logs/`: Schreibbares Log-Verzeichnis (per `.htaccess` geschützt).

## Konfiguration
1. Kopiere `public/config/config.example.php` nach `public/config/config.php` und passe Zugangsdaten, `base_url`, Zeitzone und optional `cron_token` an.
2. Stelle sicher, dass `public/logs/` für den Webserver beschreibbar ist.
3. Datenbankverbindung nutzt PDO mit `utf8mb4`.

## Timezone
Die globale Zeitzone wird beim Laden von `public/lib/bootstrap.php` aus der Konfiguration gesetzt (Fallback: `Europe/Berlin`).
