# rules.md — Vereinshelfer / Trainerabrechnung (Google Apps Script)

Dieses Repo ist eine Google Apps Script WebApp (HtmlService) + Google Sheets als Datenbank.
Codex soll **nur** innerhalb dieser Rahmenbedingungen arbeiten und Änderungen so liefern,
dass sie ohne „Such mal sowas wie …“ zuverlässig copy/pastebar sind.

---

## 1) Projektstruktur & Dateien

### Server (Apps Script)
- `Code.gs` (oder `code.gs`): Serverlogik, Sheet-IO, APIs, Session, Business-Logik.
- sheetid ist bereits in der code.gs eingetragen. diese beibehalten
- Keine Node/Composer/NPM. Kein Build-Step. Kein Import externer Pakete.

### Client (HtmlService)
- `index.html`: UI + Client-JS (mobile first).
- `style.html`: CSS (Branding/Theme).
- Weitere HTML-Partials nur, wenn bereits im Projekt genutzt (include()).

**Regel:** Wenn eine Änderung Logik/Zahlen/Filter betrifft → primär `Code.gs`.
Wenn eine Änderung Darstellung/UX betrifft → primär `index.html`/`style.html`.
Meistens: Backend liefert strukturierte Daten, Frontend rendert.

Änderungen in der "DB-STruktur" in einer .md datei ausgeben, damit dies in googlesheets angepasst werden kann

---



## 3) Google Sheets als Datenbank (Schema: `Vereinshelfer Google-2.xlsx`)

Im Ordner liegt `Vereinshelfer Google-2.xlsx` als Abbild der Tabellenstruktur:
- Jede Sheet-Tab = “Tabelle”
- Row 1 = Header (Spaltennamen)
- Daten ab Row 2
- Headernamen sind **API-Verträge**: nicht umbenennen ohne Migration.

### Grundregeln
- Immer über Headernamen arbeiten (nicht über feste Spaltennummern).
- Neue Spalten nur hinzufügen, wenn wirklich nötig.
- Soft-Delete (z.B. `deleted_at`) bevorzugen statt Zeilen löschen.

### IDs
- Jede Tabelle hat i.d.R. eine ID-Spalte (z.B. `trainer_id`, `training_id`, `einteilung_id`, `abmeldung_id`).
- IDs werden als String behandelt.
- Neue IDs werden serverseitig erzeugt (UUID/ShortId) und geschrieben.

### Formeln / “Leere 5000 Zeilen”
In manchen Tabs existieren Formeln in späteren Spalten über viele Zeilen.
**Regel:**
- Niemals ganze Zeile als Array schreiben, wenn dadurch Formelspalten überschrieben werden.
- Neue Einträge in die **erste wirklich freie Zeile der Key-Spalte** schreiben (z.B. `einteilung_id` leer).
- Beim Insert **nur** Spalten setzen, die im Objekt vorhanden sind; Rest bleibt (Formeln bleiben erhalten).

---

## 4) Zeit/Datum: Apps Script & Sheets Besonderheiten

In Sheets können Zeitwerte ankommen als:
- `Date` (mit Basisjahr 1899/1899)
- `number` (Bruchteil eines Tages)
- `string` ("17:00")

**Regel:**
- UI darf nie “Sun Dec 31 1899 …” anzeigen.
- Server muss Zeiten mit einer robusten Funktion normalisieren, z.B. `fmtTime_(v) => "HH:mm"`.
- Dauerberechnung über `parseHM_` + Minuten-Differenz (Rundung auf 2 Dezimalstellen).
- Script-Zeitzone nutzen (`Session.getScriptTimeZone()`), keine harte Timezone.

---

## 5) API-Design & Fehlerbehandlung

### API Response Standard
- Jede API gibt ein Objekt zurück:
  - `{ ok: true, ... }` oder `{ ok: false, error: "..." }`
- Server wirft möglichst keine unhandled exceptions in WebApp-Calls.
  - Falls Exceptions: catch und in `{ok:false,error}` umwandeln.

### Client Call Wrapper
- `google.script.run` muss immer `.withFailureHandler` haben.
- Failure darf den UI-Flow nicht “silent” killen.
- Client-Wrapper soll **immer** resolve liefern (`{ok:false,error}`), nicht reject.

### Session
- Session über PropertiesService (UserProperties oder ScriptProperties) / token-basiert.
- `requireSession_()` liefert User-Daten oder Fehler “Session abgelaufen…”.
- Keine sensiblen Daten im Client speichern außer Token.

---

## 6) UI/UX Regeln (mobile first)

- Layout: mobile first (iPhone/Android), einfache Buttons, klare States.
- Keine High-Skill UI-Frameworks, keine externen CDNs (CSP/Offline/Netcup-Setup).
- Tab-Steuerung:
  - Haupttabs: nur Elemente mit `data-tab`
  - Subtabs: nur Elemente mit `data-subtab`
  - Verhindern, dass ein Click-Handler “alle Tabs” kaputt bindet.

- Admin-Funktionen:
  - Admin-Buttons nur dort sichtbar, wo verlangt (z.B. nur Admin->Trainings).
  - Nicht mischen: normale Nutzeraktionen (Übernehme/Nicht verfügbar) vs. Adminaktionen (Status setzen).

---

## 7) Rollen & Rechte

- `user.is_admin` bestimmt Admin-Zugriff.
- Admin APIs serverseitig checken (keine reine UI-Sperre).
- Nutzer dürfen nur eigene Einteilungen ändern, außer Admin.

---

## 8) Bestehende Features nicht brechen

Codex darf bestehende Funktionsnamen/IDs nicht unbemerkt ändern.
Vor Änderungen immer prüfen:
- Welche HTML IDs werden in JS referenziert?
- Welche API-Namen ruft der Client auf?
- Welche Sheet-Header werden erwartet?

**Regel:** Wenn etwas umbenannt wird, muss Codex Migration/Kompatibilität liefern.

---

## 9) Testing & Debugging

Wenn Codex Änderungen macht, soll es:
- eine kurze “Smoke Test” Liste liefern:
  1) Login
  2) Trainingsliste rendert
  3) Details-Modal öffnet
  4) Übernehme / Austragen / Nicht verfügbar
  5) Admin->Trainings Status setzen
  6) Abrechnung berechnen

- Optional: eine `TEST_*` Funktion in `Code.gs` hinzufügen, aber nur wenn gewünscht.

---

## 10) Branding

KSV Homberg Style:
- Farben/Logo werden in `style.html` bzw. im Header der WebApp genutzt. Alternativ in der .xlsx / googlesheets datei unter settings.
- Keine externen Assets per URL ohne Rücksprache.
- Logo: wenn als Datei vorhanden, dann über bestehende Einbindungsmethode arbeiten (nicht neu erfinden).

---

## 11) Regeln für Änderungen (Arbeitsweise)

- Jede Änderung klein halten und klar abgrenzen.
- Keine “komplette Neuimplementierung”.
- Keine Änderungen an Datenmodell ohne Hinweis + Update `Sheets.csv`/Migration.
- Wenn an mehreren Stellen nötig: lieber eine saubere Hilfsfunktion hinzufügen statt Copy/Paste.

---

## 12) Wenn Informationen fehlen

Wenn `Vereinshelfer Google-2.xlsx` oder aktuelle Dateien nicht zur Implementierung passen:
- Codex soll NICHT raten.
- Stattdessen eine kurze Liste an konkreten fehlenden Informationen ausgeben (max 5 Punkte),
  z.B. exakter Sheet-Tab-Name, Headername, oder existierender Funktionsname.

Ende.
