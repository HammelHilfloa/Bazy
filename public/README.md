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

## Setup
1. Datenbank anlegen (z. B. `CREATE DATABASE vereinskalender CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`).
2. `public/config/config.example.php` nach `public/config/config.php` kopieren und DB-Zugangsdaten, `base_url`, Zeitzone sowie optional `cron_token` (für Token-geschützte Cron-Calls) pflegen.
3. Migration ausführen: `php public/database/migrate.php` (CLI) oder im Browser aufrufen.
4. Seeds einspielen (optionales Demo-Set): `mysql -u <user> -p <db> < public/database/seed.sql`.
5. Schreibrechte auf `public/logs/` sicherstellen (z. B. `chown www-data:www-data public/logs && chmod 775 public/logs`).
6. Falls benötigt, App-URL für Login/Logout/Form-Actions in `config.php` per `base_url` korrekt setzen.

## Browser-Installation
1. Sicherstellen, dass `public/config/config.php` noch nicht existiert (oder temporär `INSTALLER_FORCE=true` in der Datei setzen).
2. Schreibrechte für `public/config/` und `public/logs/` setzen.
3. Im Browser `https://<host>/installer.php` öffnen und DB-Daten sowie Admin-Zugang ausfüllen (optional Editor-User hinzufügen).
4. Mit „Test DB Verbindung“ prüfen und anschließend „Installieren“ ausführen. Der Installer spielt `database/schema.sql` ein, legt Kategorien, Admin (und optional Editor) an, schreibt einen Audit-Log-Eintrag (`entity_type=import`, `action=create`) und generiert `config/config.php` (mit autodetect `base_url`, Zeitzone `Europe/Berlin`, optional zufälligem `cron_token`).
5. Nach Erfolg „Installer löschen“ klicken oder `public/installer.php` manuell entfernen. Der Installer blockiert automatisch, sobald `config.php` existiert.

## Timezone
Die globale Zeitzone wird beim Laden von `public/lib/bootstrap.php` aus der Konfiguration gesetzt (Fallback: `Europe/Berlin`).

## Hosting-Hinweise (z. B. Netcup)
- PHP >= 8.1 verwenden (PDO MySQL, cURL, JSON aktiviert).
- Webserver-Benutzer braucht Schreibrechte auf `public/logs/` (für `app.log` und Cache).
- Falls `cron_token` genutzt wird, Token nur serverseitig verteilen (z. B. per Cron/Monitoring), nicht clientseitig.

## Backup-Empfehlung
Regelmäßige Dumps per mysqldump, z. B.: `mysqldump -u <user> -p <db> > vereinskalender_$(date +%F).sql`.

## Cron-Alternative (OpenHolidays Sync)
- Optionaler Token-Call ohne Session: `https://<host>/api/sync/openholidays.php?cron_token=<TOKEN>&year_from=2024&year_to=2025`.
- Aktivieren durch Setzen von `cron_token` in `config.php`.
- Server prüft nur den Token; Audit-Log vermerkt den Trigger (`cron` vs. Session).
