/** =========================
 * Vereinshelfer – Trainer Webapp (PIN Login)
 * Dateien: Code.gs, index.html, style.html
 * ========================= */

const CFG = {
  // Wenn das Script an das Sheet gebunden ist: leave null
  SPREADSHEET_ID: "1xDcT9tJyY5ENaxZTn2qEUHu3rcJxZwGdZROLcxEOtfs", // z.B. "1AbC..." falls Standalone Script
  SHEETS: {
    TRAINER: "TRAINER",
    TRAININGS: "TRAININGS",
    EINTEILUNGEN: "EINTEILUNGEN",
    ABMELDUNGEN: "ABMELDUNGEN",
  },
  SESSION_TTL_SECONDS: 60 * 60 * 8, // 8h
  TIMEZONE: Session.getScriptTimeZone(),
};

/** ====== UI ====== */
function doGet() {
  return HtmlService.createTemplateFromFile("index")
    .evaluate()
    .setTitle("Vereinshelfer – Trainer")
    .setXFrameOptionsMode(HtmlService.XFrameOptionsMode.ALLOWALL);
}

function include(filename) {
  return HtmlService.createHtmlOutputFromFile(filename).getContent();
}

/** ====== API ====== */
function apiListActiveTrainers() {
  const ss = getSS_();
  const trainers = readTable_(ss.getSheetByName(CFG.SHEETS.TRAINER));
  return {
    ok: true,
    items: trainers
      .filter(t => truthy_(t.aktiv))
      .map(r => ({
        trainer_id: String(r.trainer_id || ""),
        name: String(r.name || ""),
        email: String(r.email || ""),
      }))
      .sort((a,b) => a.name.localeCompare(b.name, "de"))
  };
}

function apiLogin(trainerId, pin) {
  const ss = getSS_();
  const trainers = readTable_(ss.getSheetByName(CFG.SHEETS.TRAINER));
  const t = trainers.find(r => String(r.trainer_id) === String(trainerId));

  if (!t) return { ok: false, error: "Trainer nicht gefunden." };
  if (!truthy_(t.aktiv)) return { ok: false, error: "Trainer ist nicht aktiv." };

  const storedPin = String(t.pin || "").trim();
  const got = String(pin || "").trim();
  if (!verifyPin_(got, storedPin)) return { ok: false, error: "PIN falsch." };

  const token = createToken_();
  saveSession_(token, {
    trainer_id: String(t.trainer_id),
    email: String(t.email || ""),
    name: String(t.name || ""),
    is_admin: truthy_(t.is_admin),
    rolle_standard: String(t.rolle_standard || "Trainer"),
    stundensatz: Number(t.stundensatz_eur ?? t.stundensatz ?? 0),
  });

  return { ok: true, token, user: getSession_(token) };
}

function apiLogout(token) {
  clearSession_(token);
  return { ok: true };
}

function apiBootstrap(token) {
  try {
    const user = requireSession_(token);
    const ss = getSS_();

    const shT = ss.getSheetByName(CFG.SHEETS.TRAININGS);
    const shE = ss.getSheetByName(CFG.SHEETS.EINTEILUNGEN);
    if (!shT) return { ok:false, error:`Sheet fehlt: ${CFG.SHEETS.TRAININGS}` };
    if (!shE) return { ok:false, error:`Sheet fehlt: ${CFG.SHEETS.EINTEILUNGEN}` };

    const trainings = readTable_(shT);
    const einteilungen = readTable_(shE);

    const abmeldungen = readTableSafe_(ss.getSheetByName(CFG.SHEETS.ABMELDUNGEN));
    const myAbm = abmeldungen.filter(a =>
      String(a.trainer_id) === String(user.trainer_id) &&
      isBlank_(a.deleted_at)
    );
    const unavailSet = new Set(myAbm.map(a => String(a.training_id)));

    // Upcoming planned trainings (incl. today)
    const today = startOfDay_(new Date());
    const upcoming = trainings
      .filter(tr => String(tr.status) === "geplant" && toDate_(tr.datum) && startOfDay_(toDate_(tr.datum)).getTime() >= today.getTime())
      .map(tr => {
        const t = enrichTraining_(tr, einteilungen);
        t.is_unavailable = unavailSet.has(String(t.training_id));
        return t;
      })
      .sort((a,b) => a.datumTs - b.datumTs);

    const mineActive = einteilungen
      .filter(e => String(e.trainer_id) === user.trainer_id && isBlank_(e.ausgetragen_am))
      .map(e => enrichEinteilung_(e, trainings))
      .sort((a,b) => a.trainingDatumTs - b.trainingDatumTs);

    const myUnavailable = myAbm.map(a => {
      const tr = trainings.find(t => String(t.training_id) === String(a.training_id));
      if (!tr) return { training_id: String(a.training_id), label: String(a.training_id) };
      const d = toDate_(tr.datum);
      return {
        training_id: String(a.training_id),
        label: `${formatDate_(d)} · ${fmtTime_(tr.start)}–${fmtTime_(tr.ende)} · ${String(tr.gruppe||"")}`
      };
    });

    return {
      ok: true,
      user,
      upcoming,
      mineActive,
      myUnavailable,
    };
  } catch (e) {
    return { ok:false, error: (e && e.message) ? e.message : String(e) };
  }
}

function apiGetMyProfile(token) {
  const user = requireSession_(token);
  const ss = getSS_();
  const trainers = readTable_(ss.getSheetByName(CFG.SHEETS.TRAINER));
  const t = trainers.find(r => String(r.trainer_id) === String(user.trainer_id));
  if (!t) return { ok:false, error:"Trainer nicht gefunden." };
  return {
    ok: true,
    trainer: {
      trainer_id: String(t.trainer_id),
      name: String(t.name || ""),
      email: String(t.email || ""),
      rolle_standard: String(t.rolle_standard || "Trainer"),
      stundensatz: Number(t.stundensatz_eur ?? t.stundensatz ?? 0),
      is_admin: truthy_(t.is_admin),
    }
  };
}

function apiChangeMyPin(token, oldPin, newPin) {
  const user = requireSession_(token);
  const ss = getSS_();
  const sh = ss.getSheetByName(CFG.SHEETS.TRAINER);
  const { header, rowIndexByKey } = readTableWithMeta_(sh, "trainer_id");
  const idx = rowIndexByKey[String(user.trainer_id)];
  if (!idx) return { ok:false, error:"Trainer nicht gefunden." };

  const cur = String(getCell_(sh, header, idx, "pin") || "").trim();
  if (!verifyPin_(oldPin, cur)) return { ok:false, error:"Aktuelle PIN ist falsch." };

  const np = String(newPin || "").trim();
  if (!/^\d{4,8}$/.test(np)) return { ok:false, error:"Neue PIN muss 4–8 Ziffern haben." };

  setCell_(sh, header, idx, "pin", hashPin_(np));
  return { ok:true };
}

function apiTrainingDetails(token, trainingId) {
  requireSession_(token);
  const ss = getSS_();

  const shT = ss.getSheetByName(CFG.SHEETS.TRAININGS);
  const shE = ss.getSheetByName(CFG.SHEETS.EINTEILUNGEN);
  const shR = ss.getSheetByName(CFG.SHEETS.TRAINER);

  const trainings = readTable_(shT);
  const einteilungen = readTable_(shE);
  const trainers = readTable_(shR);

  const tr = trainings.find(t => String(t.training_id) === String(trainingId));
  if (!tr) return { ok:false, error:"Training nicht gefunden." };

  const enriched = enrichTraining_(tr, einteilungen);

  // Eingetragene Trainer (aktive Einteilungen)
  const signups = einteilungen
    .filter(e => String(e.training_id) === String(trainingId) && isBlank_(e.ausgetragen_am))
    .map(e => {
      const tt = trainers.find(x => String(x.trainer_id) === String(e.trainer_id)) || {};
      return {
        trainer_id: String(e.trainer_id),
        name: String(tt.name || e.trainer_id),
        rolle: String(e.rolle || tt.rolle_standard || "Trainer"),
        checkin_am: e.checkin_am ? formatDateTime_(toDate_(e.checkin_am)) : "",
        attendance: String(e.attendance || ""),
      };
    })
    .sort((a,b)=> a.name.localeCompare(b.name, "de"));

  // Nicht verfügbar (ABMELDUNGEN) – aktiv, wenn deleted_at leer
  const abm = readTableSafe_(ss.getSheetByName(CFG.SHEETS.ABMELDUNGEN));
  const unavailable = abm
    .filter(a =>
      String(a.training_id) === String(trainingId) &&
      isBlank_(a.deleted_at)
    )
    .map(a => {
      const tt = trainers.find(x => String(x.trainer_id) === String(a.trainer_id)) || {};
      return {
        trainer_id: String(a.trainer_id || ""),
        name: String(tt.name || a.trainer_id || ""),
        grund: String(a.grund || ""),
        created_at: a.created_at ? formatDateTime_(toDate_(a.created_at)) : "",
      };
    })
    .sort((a,b)=> a.name.localeCompare(b.name, "de"));

  return { ok:true, training: enriched, signups, unavailable };
}


function apiEnroll(token, trainingId) {
  const user = requireSession_(token);
  const ss = getSS_();

  const shT = ss.getSheetByName(CFG.SHEETS.TRAININGS);
  const shE = ss.getSheetByName(CFG.SHEETS.EINTEILUNGEN);

  const trainings = readTable_(shT);
  const einteilungen = readTable_(shE);

  const tr = trainings.find(x => String(x.training_id) === String(trainingId));
  if (!tr) return { ok: false, error: "Training nicht gefunden." };

  if (String(tr.status) !== "geplant") return { ok: false, error: "Training ist nicht geplant." };

  // block if trainer set unavailable
  const shA = ss.getSheetByName(CFG.SHEETS.ABMELDUNGEN);
  if (shA) {
    const abm = readTableSafe_(shA);
    const blocked = abm.some(a =>
      String(a.training_id) === String(trainingId) &&
      String(a.trainer_id) === String(user.trainer_id) &&
      isBlank_(a.deleted_at)
    );
    if (blocked) return { ok:false, error:"Du hast dich für dieses Training als nicht verfügbar gemeldet." };
  }

  // already assigned?
  const already = einteilungen.find(e =>
    String(e.training_id) === String(trainingId) &&
    String(e.trainer_id) === String(user.trainer_id) &&
    isBlank_(e.ausgetragen_am)
  );
  if (already) return { ok: true };

  // capacity check
  const needed = Number(tr.benoetigt_trainer || 0);
  const activeCount = einteilungen.filter(e =>
    String(e.training_id) === String(trainingId) &&
    isBlank_(e.ausgetragen_am)
  ).length;
  if (needed > 0 && activeCount >= needed) {
    return { ok: false, error: "Keine freien Plätze mehr." };
  }

  appendRow_(shE, {
    einteilung_id: "E_" + createShortId_(),
    training_id: String(trainingId),
    trainer_id: String(user.trainer_id),
    rolle: String(user.rolle_standard || "Trainer"),
    attendance: "OFFEN",
    checkin_am: "",
    checkin_nachgetragen: "",
    ausgetragen_am: "",
    created_at: new Date(),
  });

  return { ok: true };
}

function apiWithdraw(token, einteilungId) {
  const user = requireSession_(token);
  const ss = getSS_();
  const shE = ss.getSheetByName(CFG.SHEETS.EINTEILUNGEN);
  const { header, rowIndexByKey } = readTableWithMeta_(shE, "einteilung_id");

  const idx = rowIndexByKey[String(einteilungId)];
  if (!idx) return { ok: false, error: "Einteilung nicht gefunden." };

  // check ownership or admin
  const trainerId = String(shE.getRange(idx, header.indexOf("trainer_id") + 1).getValue() || "");
  if (trainerId !== String(user.trainer_id) && !user.is_admin) {
    return { ok: false, error: "Nicht berechtigt." };
  }

  setCell_(shE, header, idx, "ausgetragen_am", new Date());
  return { ok: true };
}

function apiCheckin(token, einteilungId, mode) {
  const user = requireSession_(token);
  const ss = getSS_();
  const shE = ss.getSheetByName(CFG.SHEETS.EINTEILUNGEN);
  const { header, rowIndexByKey } = readTableWithMeta_(shE, "einteilung_id");

  const idx = rowIndexByKey[String(einteilungId)];
  if (!idx) return { ok:false, error:"Einteilung nicht gefunden." };

  // ownership or admin
  const trainerId = String(shE.getRange(idx, header.indexOf("trainer_id") + 1).getValue() || "");
  if (trainerId !== String(user.trainer_id) && !user.is_admin) {
    return { ok:false, error:"Nicht berechtigt." };
  }

  const now = new Date();
  setCell_(shE, header, idx, "checkin_am", now);
  setCell_(shE, header, idx, "attendance", "JA");

  // Berechne und schreibe betrag_eur in der Einteilung (hours * stundensatz)
  try {
    const trId = String(shE.getRange(idx, header.indexOf("training_id") + 1).getValue() || "");
    const shT = ss.getSheetByName(CFG.SHEETS.TRAININGS);
    if (shT && trId) {
      const { header: th, rowIndexByKey: tIdx } = readTableWithMeta_(shT, "training_id");
      const tRow = tIdx[String(trId)];
      if (tRow) {
        const start = shT.getRange(tRow, th.indexOf("start") + 1).getValue();
        const ende = shT.getRange(tRow, th.indexOf("ende") + 1).getValue();
        const hours = hoursBetween_(start, ende);
        const rate = Number(user.stundensatz || 0);
        const amount = round2_(hours * rate);
        // schreibe betrag_eur in Einteilungen
        if (header.indexOf("betrag_eur") !== -1) {
          setCell_(shE, header, idx, "betrag_eur", amount);
        }
      }
    }
  } catch (e) {
    // fail silently but don't block checkin
  }
  return { ok:true };
}

function apiAdminSetTrainingStatus(token, trainingId, status, reason) {
  const user = requireSession_(token);
  if (!user.is_admin) return { ok: false, error: "Nur Admin." };

  const ss = getSS_();
  const shT = ss.getSheetByName(CFG.SHEETS.TRAININGS);
  const { header, rowIndexByKey } = readTableWithMeta_(shT, "training_id");

  const idx = rowIndexByKey[String(trainingId)];
  if (!idx) return { ok: false, error: "Training nicht gefunden." };

  const s = String(status || "");
  if (!["geplant", "stattgefunden", "ausgefallen"].includes(s)) {
    return { ok: false, error: "Ungültiger Status." };
  }

  setCell_(shT, header, idx, "status", s);
  if (s === "ausgefallen") {
    setCell_(shT, header, idx, "ausfall_grund", String(reason || "Admin"));
  } else {
    setCell_(shT, header, idx, "ausfall_grund", "");
  }
  return { ok: true };
}

function apiBillingHalfyear(token, year, half) {
  const user = requireSession_(token);
  const ss = getSS_();
  const shT = ss.getSheetByName(CFG.SHEETS.TRAININGS);
  const shE = ss.getSheetByName(CFG.SHEETS.EINTEILUNGEN);

  const trainings = readTable_(shT);
  const einteilungen = readTable_(shE);

  const y = Number(year);
  const h = String(half); // "H1"|"H2"
  if (!y || !["H1","H2"].includes(h)) return { ok:false, error:"Ungültiges Halbjahr/Jahr." };

  const start = (h === "H1") ? new Date(y,0,1) : new Date(y,6,1);
  const end   = (h === "H1") ? new Date(y,6,1) : new Date(y+1,0,1);

  // Alle Trainings im Halbjahr (egal ob geplant oder stattgefunden)
  const tMapAll = new Map();
  trainings.forEach(t => {
    const d = toDate_(t.datum);
    if (!d) return;
    const sd = startOfDay_(d);
    if (sd < start || sd >= end) return;
    tMapAll.set(String(t.training_id), t);
  });

  // Map für abgerechnete Trainings (stattgefunden)
  const tMapAbgerechnet = new Map();
  trainings.forEach(t => {
    const d = toDate_(t.datum);
    if (!d) return;
    const sd = startOfDay_(d);
    if (String(t.status) !== "stattgefunden") return;
    if (sd < start || sd >= end) return;
    tMapAbgerechnet.set(String(t.training_id), t);
  });

  const items = [];
  const pending = [];
  let totalHours = 0;
  let totalAmount = 0;
  let pendingTotalHours = 0;
  let pendingTotalAmount = 0;

  einteilungen.forEach(e => {
    if (String(e.trainer_id) !== user.trainer_id) return;
    if (isBlank_(e.ausgetragen_am)) {
      // Noch nicht ausgetragen
      const trainingId = String(e.training_id);

      // Abgerechnete Items
        if (tMapAbgerechnet.has(trainingId) && String(e.attendance || "") === "JA") {
        const tr = tMapAbgerechnet.get(trainingId);
        const hours = hoursBetween_(tr.start, tr.ende);
        const rate = Number(user.stundensatz || 0);

        // Prefer explicit betrag_eur from Einteilungen sheet, then training, then compute
        let amount = 0;
        if (e && e.betrag_eur !== undefined && e.betrag_eur !== "") {
          amount = Number(e.betrag_eur) || 0;
        } else if (tr && tr.betrag_eur !== undefined && tr.betrag_eur !== "") {
          amount = Number(tr.betrag_eur) || 0;
        } else {
          amount = round2_(hours * rate);
        }

        items.push({
          training_id: trainingId,
          datum: formatDate_(toDate_(tr.datum)),
          gruppe: String(tr.gruppe || ""),
          ort: String(tr.ort || ""),
          start: fmtTime_(tr.start),
          ende: fmtTime_(tr.ende),
          hours,
          rate,
          amount,
          checkin_am: e.checkin_am ? formatDateTime_(toDate_(e.checkin_am)) : "",
          status: "abgerechnet",
        });

        totalHours += hours;
        totalAmount += amount;
      }
      // Ausstehende Items (noch nicht abgerechnet)
        else if (tMapAll.has(trainingId)) {
        const tr = tMapAll.get(trainingId);
        const d = toDate_(tr.datum);
        const hours = hoursBetween_(tr.start, tr.ende);
        const rate = Number(user.stundensatz || 0);

        // Prefer satz_eur from Einteilungen, then training, then compute
        let amount = 0;
        if (e && e.satz_eur !== undefined && e.satz_eur !== "") {
          amount = Number(e.satz_eur) || 0;
        } else if (tr && tr.satz_eur !== undefined && tr.satz_eur !== "") {
          amount = Number(tr.satz_eur) || 0;
        } else {
          amount = round2_(hours * rate);
        }

        let status_text = "";
        if (String(tr.status) !== "stattgefunden") {
          status_text = `Training noch ${tr.status}`;
        } else if (String(e.attendance || "") !== "JA") {
          status_text = "Check-in erforderlich";
        }

        pending.push({
          training_id: trainingId,
          datum: formatDate_(d),
          gruppe: String(tr.gruppe || ""),
          ort: String(tr.ort || ""),
          start: fmtTime_(tr.start),
          ende: fmtTime_(tr.ende),
          hours,
          rate,
          amount,
          checkin_am: e.checkin_am ? formatDateTime_(toDate_(e.checkin_am)) : "",
          status: status_text,
          trainingstatus: String(tr.status || ""),
          attendance: String(e.attendance || ""),
        });

        // accumulate pending totals
        pendingTotalHours += hours;
        pendingTotalAmount += amount;
      }
    }
  });

  totalHours = round2_(totalHours);
  totalAmount = round2_(totalAmount);
  pendingTotalHours = round2_(pendingTotalHours);
  pendingTotalAmount = round2_(pendingTotalAmount);

  items.sort((a,b) => a.datum.localeCompare(b.datum, "de"));
  pending.sort((a,b) => a.datum.localeCompare(b.datum, "de"));

  return { ok:true, items, pending, totalHours, totalAmount, pendingTotalHours, pendingTotalAmount };
}

/** ====== Nicht verfügbar ====== */
function apiSetUnavailable(token, trainingId, grund) {
  const user = requireSession_(token);
  const ss = getSS_();

  const shA = ss.getSheetByName(CFG.SHEETS.ABMELDUNGEN);
  if (!shA) return { ok:false, error:"Sheet ABMELDUNGEN fehlt." };

  const tid = String(trainingId || "").trim();
  if (!tid) return { ok:false, error:"training_id fehlt." };

  // not allowed if already assigned
  const einteilungen = readTable_(ss.getSheetByName(CFG.SHEETS.EINTEILUNGEN));
  const alreadyAssigned = einteilungen.some(e =>
    String(e.training_id) === tid &&
    String(e.trainer_id) === String(user.trainer_id) &&
    isBlank_(e.ausgetragen_am)
  );
  if (alreadyAssigned) return { ok:false, error:"Du bist bereits eingeteilt. Bitte erst austragen." };

  const abm = readTableSafe_(shA);
  const already = abm.some(a =>
    String(a.training_id) === tid &&
    String(a.trainer_id) === String(user.trainer_id) &&
    isBlank_(a.deleted_at)
  );
  if (already) return { ok:true };

  appendRow_(shA, {
    abmeldung_id: "A_" + createShortId_(),
    training_id: tid,
    trainer_id: String(user.trainer_id),
    grund: String(grund || ""),
    created_at: new Date(),
    deleted_at: "",
  });

  return { ok:true };
}

function apiUnsetUnavailable(token, trainingId) {
  const user = requireSession_(token);
  const ss = getSS_();

  const shA = ss.getSheetByName(CFG.SHEETS.ABMELDUNGEN);
  if (!shA) return { ok:false, error:"Sheet ABMELDUNGEN fehlt." };

  const tid = String(trainingId || "").trim();
  if (!tid) return { ok:false, error:"training_id fehlt." };

  const values = shA.getDataRange().getValues();
  if (values.length < 2) return { ok:true };

  const header = values[0].map(h => String(h).trim());
  const cTid = header.indexOf("training_id");
  const cUid = header.indexOf("trainer_id");
  const cDel = header.indexOf("deleted_at");
  if (cTid < 0 || cUid < 0 || cDel < 0) return { ok:false, error:"ABMELDUNGEN Header unvollständig." };

  for (let r = values.length - 1; r >= 1; r--) {
    const rt = String(values[r][cTid] || "");
    const ru = String(values[r][cUid] || "");
    const del = values[r][cDel];
    if (rt === tid && ru === String(user.trainer_id) && isBlank_(del)) {
      shA.getRange(r+1, cDel+1).setValue(new Date());
      return { ok:true };
    }
  }
  return { ok:true };
}

/** ====== Helpers (Sheets) ====== */
function getSS_() {
  if (CFG.SPREADSHEET_ID) return SpreadsheetApp.openById(CFG.SPREADSHEET_ID);
  return SpreadsheetApp.getActiveSpreadsheet();
}

function readTable_(sheet) {
  const values = sheet.getDataRange().getValues();
  if (!values || values.length < 2) return [];
  const header = values[0].map(h => String(h).trim());
  const rows = [];
  for (let r = 1; r < values.length; r++) {
    const obj = {};
    for (let c = 0; c < header.length; c++) obj[header[c]] = values[r][c];
    rows.push(obj);
  }
  return rows;
}

function readTableSafe_(sheet) {
  try {
    if (!sheet) return [];
    return readTable_(sheet);
  } catch (e) {
    return [];
  }
}

function readTableWithMeta_(sheet, keyCol) {
  const values = sheet.getDataRange().getValues();
  const header = values[0].map(h => String(h).trim());
  const keyIdx = header.indexOf(keyCol);
  if (keyIdx === -1) throw new Error(`Key column not found: ${keyCol}`);

  const rowIndexByKey = {};
  for (let r = 1; r < values.length; r++) {
    const key = String(values[r][keyIdx] || "").trim();
    if (key) rowIndexByKey[key] = r + 1; // 1-based row
  }
  return { header, values, rowIndexByKey };
}

function appendRow_(sheet, obj) {
  // Header lesen
  const header = sheet
    .getRange(1, 1, 1, sheet.getLastColumn())
    .getValues()[0]
    .map(h => String(h).trim());

  if (!header.length) throw new Error("appendRow_: Keine Header-Zeile gefunden.");

  // Wir nutzen die 1. Spalte als "Key"-Spalte (z.B. einteilung_id / abmeldung_id)
  // Wichtig: In deinen Tabellen steht die ID-Spalte üblicherweise ganz links.
  const keyColIndex = 1; // 1-based
  const maxRows = sheet.getMaxRows();

  // Werte der Key-Spalte ab Zeile 2 laden (Formeln in anderen Spalten sind egal)
  const keyVals = sheet.getRange(2, keyColIndex, maxRows - 1, 1).getValues();

  // Erste wirklich freie Zeile finden (Key-Zelle leer)
  let targetRow = -1;
  for (let i = 0; i < keyVals.length; i++) {
    const v = keyVals[i][0];
    if (v === null || v === undefined || String(v).trim() === "") {
      targetRow = i + 2; // +2 wegen Start bei Zeile 2
      break;
    }
  }

  // Falls keine freie Zeile gefunden wurde: ans Ende anhängen
  if (targetRow === -1) {
    targetRow = sheet.getLastRow() + 1;
    if (targetRow > maxRows) sheet.insertRowAfter(maxRows);
  }

  // ✅ Nur Spalten schreiben, die im obj vorhanden sind
  // Dadurch bleiben Formeln in anderen Spalten erhalten (werden nicht überschrieben).
  for (let c = 0; c < header.length; c++) {
    const colName = header[c];
    if (Object.prototype.hasOwnProperty.call(obj, colName)) {
      sheet.getRange(targetRow, c + 1).setValue(obj[colName]);
    }
  }
}


function setCell_(sheet, header, rowIndex, colName, value) {
  const colIndex = header.indexOf(colName);
  if (colIndex === -1) throw new Error(`Column not found: ${colName}`);
  sheet.getRange(rowIndex, colIndex + 1).setValue(value);
}

function getCell_(sheet, header, rowIndex, colName) {
  const colIndex = header.indexOf(colName);
  if (colIndex === -1) throw new Error(`Column not found: ${colName}`);
  return sheet.getRange(rowIndex, colIndex + 1).getValue();
}

/** ====== Helpers (Time/Format) ====== */
function toDate_(v) {
  if (!v) return null;
  if (v instanceof Date) return v;
  const d = new Date(v);
  return isNaN(d.getTime()) ? null : d;
}

function startOfDay_(d) {
  const x = new Date(d);
  x.setHours(0,0,0,0);
  return x;
}

function formatDate_(d) {
  if (!d) return "";
  return Utilities.formatDate(d, CFG.TIMEZONE, "dd.MM.yyyy");
}

function formatDateTime_(d) {
  if (!d) return "";
  return Utilities.formatDate(d, CFG.TIMEZONE, "dd.MM.yyyy HH:mm");
}

function fmtTime_(v) {
  if (v === null || v === undefined) return "";
  const raw = String(v).trim();
  if (raw === "") return "";

  if (v instanceof Date) {
    return Utilities.formatDate(v, CFG.TIMEZONE, "HH:mm");
  }

  if (typeof v === "number") {
    const minutes = Math.round((v % 1) * 24 * 60);
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return String(h).padStart(2, "0") + ":" + String(m).padStart(2, "0");
  }

  const hm = raw.match(/^(\d{1,2}):(\d{2})/);
  if (hm) return String(Number(hm[1])).padStart(2, "0") + ":" + hm[2];

  const d = new Date(raw);
  if (!isNaN(d.getTime())) return Utilities.formatDate(d, CFG.TIMEZONE, "HH:mm");

  return raw;
}

function parseHM_(v) {
  if (v === null || v === undefined) return [null, null];
  const raw = String(v).trim();
  if (raw === "") return [null, null];

  if (v instanceof Date) return [v.getHours(), v.getMinutes()];

  if (typeof v === "number") {
    const minutes = Math.round((v % 1) * 24 * 60);
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return [h, m];
  }

  const m = raw.match(/^(\d{1,2}):(\d{2})/);
  if (!m) return [null, null];
  return [Number(m[1]), Number(m[2])];
}

function hoursBetween_(start, end) {
  const [sh, sm] = parseHM_(start);
  const [eh, em] = parseHM_(end);
  if (sh === null || eh === null) return 0;
  const s = sh * 60 + sm;
  const e = eh * 60 + em;
  const diff = Math.max(0, e - s);
  return round2_(diff / 60);
}

function round2_(n) {
  return Math.round(Number(n || 0) * 100) / 100;
}

function truthy_(v) {
  if (v === true) return true;
  const s = String(v || "").toUpperCase().trim();
  return ["TRUE","1","JA","YES","X"].includes(s);
}

function isBlank_(v) {
  return v === null || v === undefined || String(v).trim() === "";
}

/** ====== PIN Hashing ====== */
function hashPin_(pin) {
  const normalized = String(pin || "").trim();
  if (!normalized) return "";
  const digest = Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, normalized, Utilities.Charset.UTF_8);
  return `sha256:${Utilities.base64Encode(digest)}`;
}

function isHashedPin_(value) {
  return typeof value === "string" && value.startsWith("sha256:");
}

function verifyPin_(input, stored) {
  const candidate = String(input || "").trim();
  const expected = String(stored || "").trim();
  if (!candidate || !expected) return false;
  if (isHashedPin_(expected)) return hashPin_(candidate) === expected;
  // Fallback für Legacy-Daten vor der Migration
  return candidate === expected;
}

/** ====== Session ====== */
function createToken_() {
  return Utilities.getUuid();
}

function saveSession_(token, data) {
  const props = PropertiesService.getUserProperties();
  props.setProperty(token, JSON.stringify({
    ...data,
    exp: Date.now() + CFG.SESSION_TTL_SECONDS * 1000
  }));
}

function getSession_(token) {
  if (!token) return null;
  const props = PropertiesService.getUserProperties();
  const raw = props.getProperty(token);
  if (!raw) return null;
  try {
    const obj = JSON.parse(raw);
    if (Date.now() > Number(obj.exp || 0)) return null;
    return obj;
  } catch (e) {
    return null;
  }
}

function clearSession_(token) {
  if (!token) return;
  PropertiesService.getUserProperties().deleteProperty(token);
}

function requireSession_(token) {
  const s = getSession_(token);
  if (!s) throw new Error("Session abgelaufen. Bitte neu einloggen.");
  return s;
}

function createShortId_() {
  return Math.random().toString(16).slice(2, 10);
}

/** ====== Enrichment ====== */
function enrichTraining_(tr, einteilungen) {
  const trainingId = String(tr.training_id);
  const activeCount = einteilungen.filter(e =>
    String(e.training_id) === trainingId &&
    isBlank_(e.ausgetragen_am)
  ).length;

  const needed = Number(tr.benoetigt_trainer || 0);
  const offen = Math.max(0, needed - activeCount);

  const d = toDate_(tr.datum);
  return {
    training_id: trainingId,
    datum: d ? formatDate_(d) : "",
    datumTs: d ? startOfDay_(d).getTime() : 0,
    start: fmtTime_(tr.start),
    ende: fmtTime_(tr.ende),
    gruppe: String(tr.gruppe || ""),
    ort: String(tr.ort || ""),
    status: String(tr.status || ""),
    benoetigt_trainer: needed,
    eingeteilt: activeCount,
    offen,
    offen_text: `Noch ${offen} Trainer`,
    ausfall_grund: String(tr.ausfall_grund || ""),
  };
}

function enrichEinteilung_(e, trainings) {
  const tr = trainings.find(x => String(x.training_id) === String(e.training_id));
  const d = tr ? toDate_(tr.datum) : null;

  const start = tr ? fmtTime_(tr.start) : "";
  const ende  = tr ? fmtTime_(tr.ende) : "";

  return {
    einteilung_id: String(e.einteilung_id || ""),
    training_id: String(e.training_id || ""),

    datum: d ? formatDate_(d) : "",
    training_datum: d ? formatDate_(d) : "",
    trainingDatumTs: d ? startOfDay_(d).getTime() : 0,

    start,
    ende,
    gruppe: tr ? String(tr.gruppe || "") : "",
    ort: tr ? String(tr.ort || "") : "",
    training_status: tr ? String(tr.status || "") : "",

    training_label: tr
      ? `${formatDate_(d)} · ${start}–${ende} · ${String(tr.gruppe || "")}`
      : String(e.training_id || ""),

    rolle: String(e.rolle || ""),
    attendance: String(e.attendance || ""),
    checkin_am: e.checkin_am ? formatDateTime_(toDate_(e.checkin_am)) : "",
    ausgetragen: !isBlank_(e.ausgetragen_am),
  };
}

/** ====== Admin Users (existiert bei dir schon) ====== */
function TEST_listTrainers(){
  const ss = getSS_();
  const rows = readTable_(ss.getSheetByName(CFG.SHEETS.TRAINER));
  Logger.log(rows);
}

function apiAdminListTrainers(token){
  const me = requireSession_(token);
  if (!me.is_admin) return { ok:false, error:"Keine Admin-Rechte." };

  const ss = getSS_();
  const rows = readTable_(ss.getSheetByName(CFG.SHEETS.TRAINER));
  return { ok:true, items: rows.map(r=>({
    trainer_id: String(r.trainer_id||""),
    name: String(r.name||""),
    email: String(r.email||""),
    aktiv: String(r.aktiv||"TRUE"),
    is_admin: String(r.is_admin||"FALSE"),
    stundensatz: String(r.stundensatz_eur ?? r.stundensatz ?? "0"),
    stundensatz_eur: Number(r.stundensatz_eur ?? r.stundensatz ?? 0),
    pin: "", // Hash wird nicht an den Client zurückgegeben
  })).sort((a,b)=>a.name.localeCompare(b.name,"de")) };
}

function apiAdminUpsertTrainer(token, payload){
  const me = requireSession_(token);
  if (!me.is_admin) return { ok:false, error:"Keine Admin-Rechte." };

  const ss = getSS_();
  const sh = ss.getSheetByName(CFG.SHEETS.TRAINER);

  const trainer_id = String(payload.trainer_id||"").trim();
  if (!trainer_id) return { ok:false, error:"trainer_id fehlt." };

  // Tabelle lesen
  const data = sh.getDataRange().getValues();
  const headers = data[0].map(String);
  const idx = (name)=> headers.indexOf(name);

  const iTrainer = idx("trainer_id");
  if (iTrainer < 0) return { ok:false, error:"Spalte trainer_id fehlt im TRAINER-Tab." };

  let rowIndex = -1;
  for (let i=1;i<data.length;i++){
    if (String(data[i][iTrainer]).trim() === trainer_id){ rowIndex=i+1; break; }
  }

  const set = (colName, value)=>{
    const c = idx(colName);
    if (c<0) return;
    const r = rowIndex>0 ? rowIndex : (data.length+1);
    sh.getRange(r, c+1).setValue(value);
  };

  set("trainer_id", trainer_id);
  set("name", String(payload.name||""));
  set("email", String(payload.email||""));
  const newPin = String(payload.pin||"").trim();
  if (newPin) {
    set("pin", hashPin_(newPin));
  }
  const rate = Number(payload.stundensatz||0);
  set("stundensatz", rate);
  set("stundensatz_eur", rate);
  set("aktiv", String(payload.aktiv||"TRUE"));
  set("is_admin", String(payload.is_admin||"FALSE"));

  return { ok:true };
}

// Einmaliger Helfer zum Migrieren der bestehenden PINs auf Hash-Werte.
// Manuell ausführen (z.B. im Apps Script Editor), nicht als öffentliche API gedacht.
function ADMIN_migrateTrainerPinsToHashes() {
  const ss = getSS_();
  const sh = ss.getSheetByName(CFG.SHEETS.TRAINER);
  if (!sh) throw new Error(`Sheet fehlt: ${CFG.SHEETS.TRAINER}`);

  const { header, values } = readTableWithMeta_(sh, "trainer_id");
  const pinIdx = header.indexOf("pin");
  if (pinIdx === -1) throw new Error("Spalte 'pin' nicht gefunden.");

  let updated = 0;
  for (let r = 1; r < values.length; r++) {
    const raw = String(values[r][pinIdx] || "").trim();
    if (!raw || isHashedPin_(raw)) continue;
    const hashed = hashPin_(raw);
    if (hashed) {
      sh.getRange(r + 1, pinIdx + 1).setValue(hashed);
      updated++;
    }
  }

  return { ok: true, updated };
}
