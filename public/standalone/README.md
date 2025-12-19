# Standalone Kalender (JSON-basiert)

Diese Variante arbeitet komplett ohne Datenbank und speichert alle Termine in einer JSON-Datei (`data/calendar.json`).

## Starten

```bash
php -S 0.0.0.0:8000 -t public
```

Danach im Browser `http://localhost:8000/standalone/` öffnen.

## Speicherformat

```json
{
  "schemaVersion": 2,
  "year": 2026,
  "createdAt": "2025-12-15T12:15:02.266188+00:00",
  "source": "judo_kalender_2026-05.pdf",
  "groups": ["…"],
  "events": [
    { "id": "…", "date": "2026-01-10", "group": "Frauen", "title": "…", "notes": "…", "color": "#8b5cf6" }
  ]
}
```

Die API (`standalone/api/calendar.php`) liefert die JSON-Struktur komplett aus und nimmt neue/aktualisierte Events per `POST` entgegen. Löschen erfolgt via `DELETE ?id=…`.

## Ansichten

- **Monatsmatrix:** Spalten = Tage, Zeilen = Gruppen
- **Kalenderblatt:** klassische Monatsübersicht
- **Woche:** 7-Tage-Ansicht mit Terminen
- **Gruppenliste:** alle Termine nach Gruppen sortiert
- **Jahresübersicht:** pro Monat eine kompakte Zusammenfassung

Neue oder bestehende Termine können direkt in den Ansichten angeklickt und in einem Overlay bearbeitet werden.
