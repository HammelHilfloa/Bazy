# Coaching Management Plattform

Eine leichtgewichtige PHP-8.4-Anwendung zur Verwaltung von Coaching-Terminen, Angeboten und Rechnungen.

## Voraussetzungen

- PHP >= 8.4 mit aktivierten Erweiterungen `pdo_mysql` und `mbstring`
- MySQL 8 oder kompatibel
- Composer (optional für Autoload-Generierung)
- Webserver (Apache/Nginx) oder der eingebaute PHP-Server

## Installation

1. Repository klonen oder herunterladen.
2. Abhängigkeiten installieren (optional, da das Projekt keine externen Pakete benötigt):

   ```bash
   composer install
   ```

3. Eine Kopie der Umgebungsvariablen anlegen (z. B. `.env` oder Webserver-Konfiguration):

   ```bash
   export DB_HOST=127.0.0.1
   export DB_DATABASE=coaching
   export DB_USERNAME=root
   export DB_PASSWORD=secret
   export APP_URL=http://localhost:8000
   ```

4. Datenbank und Tabellen erstellen. Führe die SQL-Dateien in `database/migrations/` in numerischer Reihenfolge aus:

   ```bash
   mysql -u root -p coaching < database/migrations/20240101000000_create_users_table.sql
   mysql -u root -p coaching < database/migrations/20240101001000_create_appointments_table.sql
   mysql -u root -p coaching < database/migrations/20240101002000_create_offers_table.sql
   mysql -u root -p coaching < database/migrations/20240101003000_create_invoices_table.sql
   ```

5. Einen ersten Benutzer in der Datenbank anlegen. Erzeuge zunächst einen Passwort-Hash:

   ```bash
   php -r "echo password_hash('geheim', PASSWORD_DEFAULT), PHP_EOL;"
   ```

   Verwende den ausgegebenen Hash anschließend im Insert-Statement:

   ```sql
   INSERT INTO users (name, email, password, created_at, updated_at)
   VALUES ('Admin', 'coach@example.com', '$2y$...', NOW(), NOW());
   ```

6. Anwendung starten (lokal):

   ```bash
   php -S localhost:8000 -t public
   ```

7. Im Browser `http://localhost:8000/login` aufrufen und mit den Zugangsdaten anmelden.

## Nutzung

- **Login**: Gesicherter Zugriff mit Passwort-Hashing und CSRF-geschützten Formularen.
- **Kalender**: Termine anlegen, bearbeiten und löschen. Übersichtliche Liste mit Statusverwaltung.
- **Angebote**: Angebote erstellen und als E-Mail-Log versenden (`storage/emails/`).
- **Rechnungen**: Rechnungen erstellen, PDF-Datei erzeugen (`storage/documents/`) und Versand protokollieren.

Alle Aktionen erfordern eine authentifizierte Sitzung. Die Middleware `AuthMiddleware` und `CsrfMiddleware` sorgen für Zugriffsschutz und Formularsicherheit.

## Projektstruktur

```
app/
  Http/
    Controllers/
    Middleware/
  Models/
  Services/
config/
database/migrations/
public/
resources/views/
routes/
storage/
```

## Tests

In dieser Referenzimplementierung sind keine automatisierten Tests enthalten. Empfohlen wird die Anbindung eines Testframeworks (z. B. Pest oder PHPUnit) für produktive Einsätze.
