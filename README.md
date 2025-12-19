# Bazy Kalender – Feiertage & Schulferien NRW

Vanilla-JS Monatskalender mit PHP-Backend. Lädt automatisch Feiertage und Schulferien für NRW (DE-NW) über OpenHolidays (mit Fallbacks) und cached sie in MySQL.

## Setup

1. **PHP & MySQL bereitstellen** (Shared Hosting kompatibel, PHP 8.x):
   - Lege eine `.env` im Projektwurzelverzeichnis an (siehe `.env.example`) oder setze Umgebungsvariablen `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` (optional `DATABASE_URL` / `DB_DSN`).
2. **Datenbank anlegen**:
   - Schema importieren: `mysql -uUSER -p DB_NAME < sql/schema.sql`
3. **Deployment**:
   - Lege `public/` als DocumentRoot fest.
   - Stelle sicher, dass `/api/holidays.php` via PHP läuft.

## API

`GET /api/holidays.php?year=2026`

Antwort-Beispiel:
```json
[
  { "kind":"public_holiday", "name":"Neujahr", "start":"2026-01-01", "end":"2026-01-01" },
  { "kind":"school_holiday", "name":"Sommerferien", "start":"2026-06-29", "end":"2026-08-11" }
]
```

Verhalten:
- Holt Daten aus `holiday_entries` (Region `DE-NW`).
- Wenn keine Daten oder letztes Sync älter als 7 Tage: lädt neu von OpenHolidays und schreibt in MySQL (mit Fallback auf ferien-api.de / Nager.Date bei Fehlern).
- Fehler werden im `error_log` protokolliert und als JSON `{"error":...}` zurückgegeben.

## Frontend

- `public/index.html` + `public/app.js` + `public/styles.css`
- Monatsnavigation über Pfeile/"Heute".
- Beim Monats-Rendern wird das Jahr geladen (`/api/holidays.php?year=YYYY`), lokal gecached und clientseitig gefiltert.
- Tageszellen zeigen Badges "Feiertag" / "Ferien". Mehrtägige Ferien erscheinen zusätzlich als Wochen-Balken (Multi-Day Event Darstellung).

## Tests

- Öffne im Browser: `http://<host>/index.html` (oder direkt `public/` im File-System, benötigt API für echte Daten).
- API: `curl "http://<host>/api/holidays.php?year=2026"`

## Hinweise

- Externe Requests nutzen cURL (Fallback auf `file_get_contents`) mit Timeout 15s.
- Kein Build-Tool / Node erforderlich – nur PHP + Vanilla JS.
- Wenn keine MySQL/MariaDB-Verbindung möglich ist **und keine explizite DSN gesetzt wurde**, fällt das Backend automatisch auf eine lokale SQLite-Datei `bazy.sqlite` zurück (Tabellen + Beispiel-Gruppen werden beim ersten Start angelegt).
