const state = {
  today: new Date(),
  current: new Date(),
  holidaysByYear: {},
  loading: false,
  error: null,
  groups: [],
  groupsError: null,
  eventsByMonth: {},
  eventsLoading: false,
  eventsError: null,
  savingEvent: false,
};

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('prevBtn').addEventListener('click', () => moveMonth(-1));
  document.getElementById('nextBtn').addEventListener('click', () => moveMonth(1));
  document.getElementById('todayBtn').addEventListener('click', () => {
    state.current = new Date();
    render();
  });

  document.getElementById('eventForm').addEventListener('submit', handleEventSubmit);
  document.getElementById('monthEvents').addEventListener('click', handleEventListClick);

  initEventDefaults();
  loadGroups();
  render();
});

function moveMonth(delta) {
  const d = new Date(state.current);
  d.setMonth(d.getMonth() + delta);
  state.current = d;
  render();
}

async function ensureHolidays(year) {
  if (state.holidaysByYear[year]) return state.holidaysByYear[year];
  state.loading = true;
  state.error = null;
  updateStatus();
  try {
    const res = await fetch(`./api/holidays.php?year=${year}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    if (data.error) throw new Error(data.error);
    state.holidaysByYear[year] = data.map(normalizeHoliday);
  } catch (err) {
    console.error(err);
    state.error = 'Feiertage/Ferien konnten nicht geladen werden.';
  } finally {
    state.loading = false;
    updateStatus();
  }
  return state.holidaysByYear[year] || [];
}

function normalizeHoliday(entry) {
  return {
    ...entry,
    start: entry.start,
    end: entry.end,
  };
}

async function loadGroups() {
  if (state.groups.length) return state.groups;
  state.groupsError = null;
  updateGroupStatus();
  try {
    const res = await fetch('./api/groups.php');
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    if (data.error) throw new Error(data.error);
    state.groups = data;
    renderGroups();
    populateGroupSelect();
  } catch (err) {
    console.error(err);
    state.groupsError = 'Gruppen konnten nicht geladen werden.';
    updateGroupStatus();
  }
  return state.groups;
}

function renderGroups() {
  const list = document.getElementById('groupsList');
  list.innerHTML = '';
  if (!state.groups.length) {
    list.textContent = 'Keine Gruppen gefunden.';
    return;
  }
  state.groups.forEach((g) => {
    const pill = document.createElement('span');
    pill.className = 'pill';
    pill.textContent = g.name;
    list.appendChild(pill);
  });
}

function populateGroupSelect() {
  const select = document.getElementById('groupSelect');
  select.innerHTML = '';
  state.groups.forEach((g) => {
    const opt = document.createElement('option');
    opt.value = g.id;
    opt.textContent = g.name;
    select.appendChild(opt);
  });
}

async function ensureEvents(monthStart, monthEnd) {
  const cacheKey = `${monthStart.getUTCFullYear()}-${monthStart.getUTCMonth()}`;
  if (state.eventsByMonth[cacheKey]) return state.eventsByMonth[cacheKey];
  state.eventsLoading = true;
  state.eventsError = null;
  updateEventStatus();
  try {
    const params = new URLSearchParams({
      start: `${toISO(monthStart)} 00:00:00`,
      end: `${toISO(monthEnd)} 23:59:59`,
    });
    const res = await fetch(`./api/events.php?${params.toString()}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    if (data.error) throw new Error(data.error);
    state.eventsByMonth[cacheKey] = data.map(normalizeEvent);
  } catch (err) {
    console.error(err);
    state.eventsError = 'Termine konnten nicht geladen werden.';
  } finally {
    state.eventsLoading = false;
    updateEventStatus();
  }
  return state.eventsByMonth[cacheKey] || [];
}

function normalizeEvent(entry) {
  return {
    ...entry,
    startDate: parseIsoDate(entry.start_at.split(' ')[0]),
    endDate: parseIsoDate(entry.end_at.split(' ')[0]),
  };
}

function updateStatus() {
  const el = document.getElementById('status');
  if (state.loading) {
    el.textContent = 'Lade Feiertage & Ferien …';
    el.classList.remove('error');
  } else if (state.error) {
    el.textContent = state.error;
    el.classList.add('error');
  } else {
    el.textContent = '';
    el.classList.remove('error');
  }
}

function updateGroupStatus() {
  const el = document.getElementById('groupsStatus');
  if (state.groupsError) {
    el.textContent = state.groupsError;
    el.classList.add('error');
  } else {
    el.textContent = '';
    el.classList.remove('error');
  }
}

function updateEventStatus(message) {
  const el = document.getElementById('eventStatus');
  if (message) {
    el.textContent = message;
    el.classList.remove('error');
    return;
  }
  if (state.eventsLoading) {
    el.textContent = 'Lade Termine …';
    el.classList.remove('error');
  } else if (state.eventsError) {
    el.textContent = state.eventsError;
    el.classList.add('error');
  } else {
    el.textContent = '';
    el.classList.remove('error');
  }
}

async function render() {
  const monthLabel = document.getElementById('monthLabel');
  const weeksEl = document.getElementById('weeks');
  const monthStart = new Date(Date.UTC(state.current.getFullYear(), state.current.getMonth(), 1));
  const monthEnd = new Date(Date.UTC(state.current.getFullYear(), state.current.getMonth() + 1, 0));
  monthLabel.textContent = monthStart.toLocaleDateString('de-DE', { month: 'long', year: 'numeric' });

  const weeks = buildWeeks(monthStart);
  weeksEl.innerHTML = '';

  await loadGroups();
  const [holidays, events] = await Promise.all([
    ensureHolidays(monthStart.getUTCFullYear()),
    ensureEvents(monthStart, monthEnd),
  ]);
  const monthHolidays = holidays.filter((h) => rangesOverlap(h.start, h.end, monthStart, monthEnd));
  const monthEvents = events.slice().sort((a, b) => new Date(a.start_at) - new Date(b.start_at));

  weeks.forEach((week) => {
    const row = document.createElement('div');
    row.className = 'week-row';
    week.days.forEach((day) => {
      const cell = document.createElement('div');
      cell.className = 'day-cell';
      const header = document.createElement('div');
      header.className = 'day-header';

      const num = document.createElement('span');
      num.className = 'day-number';
      num.textContent = day.getUTCDate();
      header.appendChild(num);

      const badgesWrap = document.createElement('div');
      badgesWrap.className = 'badges';

      monthHolidays.forEach((h) => {
        if (dateWithinRange(day, h.start, h.end)) {
          const badge = document.createElement('span');
          badge.className = 'badge ' + (h.kind === 'public_holiday' ? 'holiday' : 'ferien');
          badge.textContent = h.kind === 'public_holiday' ? 'Feiertag' : 'Ferien';
          badge.title = h.name;
          badgesWrap.appendChild(badge);
        }
      });

      const dayEvents = monthEvents.filter((ev) => eventSpansDay(ev, day));
      const eventWrap = document.createElement('div');
      eventWrap.className = 'day-events';
      dayEvents.forEach((ev) => {
        const row = document.createElement('div');
        row.className = 'day-event-row';
        const dot = document.createElement('span');
        dot.className = 'event-dot';
        const time = document.createElement('span');
        time.textContent = formatTime(ev.start_at);
        const title = document.createElement('span');
        title.textContent = `${ev.group_name}: ${ev.title}`;
        row.appendChild(dot);
        row.appendChild(time);
        row.appendChild(title);
        eventWrap.appendChild(row);
      });

      cell.appendChild(header);
      cell.appendChild(badgesWrap);
      cell.appendChild(eventWrap);
      row.appendChild(cell);
    });

    const barLayer = document.createElement('div');
    barLayer.className = 'bar-layer';

    const bars = buildBarsForWeek(week, monthStart, monthEnd, monthHolidays.filter((h) => h.kind === 'school_holiday'));
    bars.forEach((bar) => {
      const barEl = document.createElement('div');
      barEl.className = 'bar';
      barEl.textContent = bar.label;
      barEl.style.gridColumn = `${bar.start} / span ${bar.span}`;
      barLayer.appendChild(barEl);
    });

    row.appendChild(barLayer);
    weeksEl.appendChild(row);
  });

  renderMonthEventsList(monthEvents, monthStart);
}

function buildWeeks(monthStart) {
  const start = startOfWeek(monthStart);
  const end = endOfWeek(new Date(Date.UTC(monthStart.getUTCFullYear(), monthStart.getUTCMonth() + 1, 0)));
  const weeks = [];
  let cursor = new Date(start);
  while (cursor <= end) {
    const days = [];
    for (let i = 0; i < 7; i++) {
      days.push(new Date(cursor));
      cursor = addDays(cursor, 1);
    }
    weeks.push({ days, start: days[0] });
  }
  return weeks;
}

function buildBarsForWeek(week, monthStart, monthEnd, holidays) {
  const segments = [];
  const weekStart = week.start;
  const weekEnd = addDays(weekStart, 6);
  holidays.forEach((h) => {
    if (!rangesOverlap(h.start, h.end, weekStart, weekEnd)) return;
    const segStart = maxDate([h.start, weekStart, monthStart]);
    const segEnd = minDate([h.end, weekEnd, monthEnd]);
    const startIdx = diffDays(weekStart, segStart) + 1; // grid columns start at 1
    const span = diffDays(segStart, segEnd) + 1;
    segments.push({ start: startIdx, span, label: h.name });
  });
  return segments;
}

function startOfWeek(date) {
  const d = new Date(date);
  const day = d.getUTCDay() || 7;
  const diff = day - 1;
  return addDays(d, -diff);
}

function endOfWeek(date) {
  const d = new Date(date);
  const day = d.getUTCDay() || 7;
  const diff = 7 - day;
  return addDays(d, diff);
}

function addDays(date, days) {
  const d = new Date(date);
  d.setUTCDate(d.getUTCDate() + days);
  return d;
}

function diffDays(a, b) {
  const ms = Date.UTC(b.getUTCFullYear(), b.getUTCMonth(), b.getUTCDate()) - Date.UTC(a.getUTCFullYear(), a.getUTCMonth(), a.getUTCDate());
  return Math.round(ms / 86400000);
}

function dateWithinRange(date, startIso, endIso) {
  const d = asUtcDate(date);
  const s = parseIsoDate(startIso);
  const e = parseIsoDate(endIso);
  return d >= s && d <= e;
}

function rangesOverlap(startA, endA, startB, endB) {
  const a1 = parseIsoDate(startA instanceof Date ? toISO(startA) : startA);
  const a2 = parseIsoDate(endA instanceof Date ? toISO(endA) : endA);
  const b1 = parseIsoDate(startB instanceof Date ? toISO(startB) : startB);
  const b2 = parseIsoDate(endB instanceof Date ? toISO(endB) : endB);
  return a1 <= b2 && b1 <= a2;
}

function parseIsoDate(iso) {
  if (iso instanceof Date) return asUtcDate(iso);
  const parts = iso.split('-').map(Number);
  return new Date(Date.UTC(parts[0], parts[1] - 1, parts[2], 12));
}

function asUtcDate(date) {
  return new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate(), 12));
}

function maxDate(list) {
  return list.map((d) => (d instanceof Date ? d : parseIsoDate(d))).reduce((a, b) => (a > b ? a : b));
}

function minDate(list) {
  return list.map((d) => (d instanceof Date ? d : parseIsoDate(d))).reduce((a, b) => (a < b ? a : b));
}

function toISO(date) {
  return date.toISOString().slice(0, 10);
}

function formatTime(iso) {
  const time = iso.split(' ')[1] || '';
  return time.slice(0, 5);
}

function renderMonthEventsList(events, monthStart) {
  const list = document.getElementById('monthEvents');
  list.innerHTML = '';
  if (!events.length) {
    list.textContent = 'Keine Termine in diesem Monat.';
    return;
  }
  events.forEach((ev) => {
    const item = document.createElement('div');
    item.className = 'event-item';
    const header = document.createElement('header');
    const title = document.createElement('div');
    title.textContent = ev.title;
    const actions = document.createElement('div');
    actions.className = 'event-actions';
    const delBtn = document.createElement('button');
    delBtn.className = 'btn small';
    delBtn.textContent = 'Löschen';
    delBtn.dataset.eventId = ev.id;
    actions.appendChild(delBtn);
    header.appendChild(title);
    header.appendChild(actions);

    const meta = document.createElement('div');
    meta.className = 'event-meta';
    const tag = document.createElement('span');
    tag.className = 'tag';
    tag.textContent = ev.group_name;
    const dateText = document.createElement('span');
    dateText.textContent = `${formatDateTime(ev.start_at)} – ${formatDateTime(ev.end_at)}`;
    meta.appendChild(tag);
    meta.appendChild(dateText);

    item.appendChild(header);
    item.appendChild(meta);

    if (ev.location) {
      const loc = document.createElement('div');
      loc.className = 'event-meta';
      loc.textContent = ev.location;
      item.appendChild(loc);
    }

    if (ev.notes) {
      const notes = document.createElement('div');
      notes.className = 'event-notes';
      notes.textContent = ev.notes;
      item.appendChild(notes);
    }

    list.appendChild(item);
  });
}

function formatDateTime(iso) {
  const date = iso.split(' ')[0];
  const time = formatTime(iso);
  return `${date} ${time}`;
}

function initEventDefaults() {
  const start = document.querySelector('input[name="start_at"]');
  const end = document.querySelector('input[name="end_at"]');
  const now = new Date();
  const inOneHour = new Date(now.getTime() + 60 * 60 * 1000);
  start.value = toLocalInput(now);
  end.value = toLocalInput(inOneHour);
}

function toLocalInput(date) {
  const pad = (n) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

async function handleEventSubmit(e) {
  e.preventDefault();
  const form = e.target;
  const payload = Object.fromEntries(new FormData(form).entries());
  payload.group_id = Number(payload.group_id);
  payload.start_at = payload.start_at.replace('T', ' ');
  payload.end_at = payload.end_at.replace('T', ' ');
  state.savingEvent = true;
  updateEventStatus('Speichere Termin …');
  try {
    const res = await fetch('./api/events.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    if (!res.ok || data.error) {
      const msg = data.error || `HTTP ${res.status}`;
      throw new Error(msg);
    }
    clearEventCacheForCurrentMonth();
    form.reset();
    initEventDefaults();
    updateEventStatus('Termin gespeichert.');
    render();
  } catch (err) {
    console.error(err);
    updateEventStatus('Termin konnte nicht gespeichert werden.');
  } finally {
    state.savingEvent = false;
  }
}

function clearEventCacheForCurrentMonth() {
  const monthStart = new Date(Date.UTC(state.current.getFullYear(), state.current.getMonth(), 1));
  const cacheKey = `${monthStart.getUTCFullYear()}-${monthStart.getUTCMonth()}`;
  delete state.eventsByMonth[cacheKey];
}

async function handleEventListClick(e) {
  const btn = e.target.closest('button[data-event-id]');
  if (!btn) return;
  const id = Number(btn.dataset.eventId);
  if (!id) return;
  btn.disabled = true;
  updateEventStatus('Lösche Termin …');
  try {
    const res = await fetch(`./api/events.php?id=${id}`, { method: 'DELETE' });
    const data = await res.json();
    if (!res.ok || data.error) throw new Error(data.error || `HTTP ${res.status}`);
    clearEventCacheForCurrentMonth();
    render();
    updateEventStatus('Termin gelöscht.');
  } catch (err) {
    console.error(err);
    updateEventStatus('Termin konnte nicht gelöscht werden.');
  } finally {
    btn.disabled = false;
  }
}

function eventSpansDay(event, dayDate) {
  const start = parseIsoDate(toISO(dayDate));
  const end = parseIsoDate(toISO(dayDate));
  return event.startDate <= end && event.endDate >= start;
}
