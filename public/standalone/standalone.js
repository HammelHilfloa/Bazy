const state = {
  calendar: null,
  activeView: 'matrix',
  currentMonth: null,
  currentWeekStart: null,
  loading: false,
  saving: false,
  editingId: null,
};

document.addEventListener('DOMContentLoaded', () => {
  bindUi();
  loadCalendar();
});

function bindUi() {
  document.querySelectorAll('[data-view]').forEach((btn) => {
    btn.addEventListener('click', () => setView(btn.dataset.view));
  });

  document.getElementById('prevBtn').addEventListener('click', () => shiftPeriod(-1));
  document.getElementById('nextBtn').addEventListener('click', () => shiftPeriod(1));
  document.getElementById('addEventBtn').addEventListener('click', () => openOverlay());

  document.getElementById('closeOverlay').addEventListener('click', closeOverlay);
  document.getElementById('cancelOverlay').addEventListener('click', closeOverlay);
  document.getElementById('eventForm').addEventListener('submit', handleFormSubmit);
  document.getElementById('deleteEventBtn').addEventListener('click', handleDeleteEvent);
}

async function loadCalendar() {
  state.loading = true;
  setStatus('Lade Kalender …');
  try {
    const res = await fetch('./api/calendar.php');
    const data = await res.json();
    if (!res.ok || data.error) {
      throw new Error(data.error || `HTTP ${res.status}`);
    }
    hydrateCalendar(data);
    setStatus('Kalender geladen', 'success');
  } catch (err) {
    console.error(err);
    setStatus('Kalender konnte nicht geladen werden.', 'error');
  } finally {
    state.loading = false;
    render();
  }
}

function hydrateCalendar(data) {
  state.calendar = data;
  if (!state.currentMonth) {
    state.currentMonth = makeDate(`${data.year}-01-01`);
  }
  if (!state.currentWeekStart) {
    state.currentWeekStart = startOfWeek(state.currentMonth);
  }
  render();
}

function setView(view) {
  state.activeView = view;
  updateViewTabs();
  render();
}

function updateViewTabs() {
  document.querySelectorAll('[data-view]').forEach((btn) => {
    const active = btn.dataset.view === state.activeView;
    btn.classList.toggle('active', active);
    btn.setAttribute('aria-selected', active ? 'true' : 'false');
  });
}

function shiftPeriod(delta) {
  if (state.activeView === 'week') {
    state.currentWeekStart = addDays(state.currentWeekStart, delta * 7);
    state.currentMonth = state.currentWeekStart;
  } else {
    state.currentMonth = addMonths(state.currentMonth, delta);
    state.currentWeekStart = startOfWeek(state.currentMonth);
  }
  render();
}

function render() {
  updateViewTabs();
  updateMeta();
  updateNavLabel();
  toggleViews();

  if (!state.calendar) return;

  renderMatrix();
  renderCalendarGrid();
  renderWeek();
  renderGroupList();
  renderYear();
}

function updateMeta() {
  const meta = document.getElementById('datasetMeta');
  if (!state.calendar) {
    meta.textContent = 'Lade Daten …';
    return;
  }
  const year = state.calendar.year;
  const source = state.calendar.source || 'Quelle unbekannt';
  meta.textContent = `${year} · ${state.calendar.events.length} Termine · ${state.calendar.groups.length} Gruppen · Quelle: ${source}`;
}

function updateNavLabel() {
  const label = document.getElementById('monthLabel');
  if (!state.calendar) {
    label.textContent = '–';
    return;
  }
  if (state.activeView === 'week') {
    const end = addDays(state.currentWeekStart, 6);
    label.textContent = `${formatDate(state.currentWeekStart)} – ${formatDate(end)}`;
    return;
  }
  label.textContent = state.currentMonth.toLocaleDateString('de-DE', { month: 'long', year: 'numeric' });
}

function toggleViews() {
  const target = state.activeView === 'groups' ? 'groupView' : `${state.activeView}View`;
  document.querySelectorAll('.view').forEach((view) => {
    view.classList.toggle('active', view.id === target);
  });
}

function renderMatrix() {
  const container = document.getElementById('matrixView');
  if (!state.calendar || state.activeView !== 'matrix') return;

  const monthStart = firstOfMonth(state.currentMonth);
  const days = getDaysOfMonth(monthStart);
  container.innerHTML = '';

  const grid = document.createElement('div');
  grid.className = 'matrix-grid';
  grid.style.setProperty('--days', days.length);

  const header = document.createElement('div');
  header.className = 'matrix-header';
  header.appendChild(createCell('Gruppe', 'matrix-label'));
  days.forEach((d) => {
    const label = `${d.getUTCDate()}`;
    header.appendChild(createCell(label, 'matrix-cell'));
  });
  grid.appendChild(header);

  state.calendar.groups.forEach((group) => {
    const row = document.createElement('div');
    row.className = 'matrix-row';
    row.appendChild(createCell(group, 'matrix-label'));

    days.forEach((day) => {
      const cell = createCell('', 'matrix-cell');
      const iso = toISO(day);
      const events = getEventsForDay(iso).filter((ev) => ev.group === group);
      if (events.length === 0) {
        const add = document.createElement('button');
        add.className = 'linkish';
        add.textContent = 'Hinzufügen';
        add.addEventListener('click', (e) => {
          e.stopPropagation();
          openOverlay({ date: iso, group });
        });
        cell.appendChild(add);
      } else {
        events.forEach((ev) => cell.appendChild(renderEventChip(ev)));
      }
      cell.addEventListener('click', () => openOverlay({ date: iso, group }));
      row.appendChild(cell);
    });

    grid.appendChild(row);
  });

  container.appendChild(grid);
}

function renderCalendarGrid() {
  const container = document.getElementById('calendarView');
  if (!state.calendar || state.activeView !== 'calendar') return;
  container.innerHTML = '';

  const monthStart = firstOfMonth(state.currentMonth);
  const start = startOfWeek(monthStart);
  const end = endOfWeek(endOfMonth(monthStart));

  const wrap = document.createElement('div');
  wrap.className = 'calendar-grid';

  let cursor = new Date(start);
  while (cursor <= end) {
    const iso = toISO(cursor);
    const dayCard = document.createElement('div');
    dayCard.className = 'calendar-day';
    dayCard.addEventListener('click', () => openOverlay({ date: iso }));

    const number = document.createElement('div');
    number.className = 'day-number';
    number.textContent = cursor.getUTCDate();
    dayCard.appendChild(number);

    const eventsWrap = document.createElement('div');
    eventsWrap.className = 'events';
    getEventsForDay(iso).forEach((ev) => {
      const chip = renderEventChip(ev);
      chip.addEventListener('click', (e) => {
        e.stopPropagation();
        openOverlay(ev);
      });
      eventsWrap.appendChild(chip);
    });
    if (!eventsWrap.childElementCount) {
      const hint = document.createElement('div');
      hint.className = 'empty';
      hint.textContent = 'Kein Termin';
      eventsWrap.appendChild(hint);
    }

    dayCard.appendChild(eventsWrap);
    wrap.appendChild(dayCard);
    cursor = addDays(cursor, 1);
  }

  container.appendChild(wrap);
}

function renderWeek() {
  const container = document.getElementById('weekView');
  if (!state.calendar || state.activeView !== 'week') return;
  container.innerHTML = '';

  const wrap = document.createElement('div');
  wrap.className = 'week-grid';

  for (let i = 0; i < 7; i++) {
    const day = addDays(state.currentWeekStart, i);
    const iso = toISO(day);
    const card = document.createElement('div');
    card.className = 'week-card';

    const title = document.createElement('div');
    title.className = 'day-number';
    title.textContent = `${formatWeekday(day)}, ${day.getUTCDate()}.`;
    card.appendChild(title);

    const list = document.createElement('div');
    list.className = 'events';
    const events = getEventsForDay(iso);
    if (!events.length) {
      const empty = document.createElement('div');
      empty.className = 'empty';
      empty.textContent = 'Keine Termine';
      list.appendChild(empty);
    } else {
      events.forEach((ev) => {
        const chip = renderEventChip(ev);
        chip.addEventListener('click', () => openOverlay(ev));
        list.appendChild(chip);
      });
    }

    const add = document.createElement('button');
    add.className = 'ghost';
    add.textContent = 'Termin hinzufügen';
    add.addEventListener('click', () => openOverlay({ date: iso }));

    card.appendChild(list);
    card.appendChild(add);
    wrap.appendChild(card);
  }

  container.appendChild(wrap);
}

function renderGroupList() {
  const container = document.getElementById('groupView');
  if (!state.calendar || state.activeView !== 'groups') return;
  container.innerHTML = '';

  const columns = document.createElement('div');
  columns.className = 'group-columns';

  state.calendar.groups.forEach((group) => {
    const card = document.createElement('div');
    card.className = 'group-card';
    const title = document.createElement('h3');
    title.textContent = group;
    card.appendChild(title);

    const list = document.createElement('div');
    list.className = 'event-list';
    const events = getEventsByGroup(group);
    if (!events.length) {
      const empty = document.createElement('div');
      empty.className = 'empty';
      empty.textContent = 'Keine Termine für diese Gruppe';
      list.appendChild(empty);
    } else {
      events.forEach((ev) => {
        const chip = renderEventChip(ev);
        chip.addEventListener('click', () => openOverlay(ev));
        list.appendChild(chip);
      });
    }

    card.appendChild(list);
    columns.appendChild(card);
  });

  container.appendChild(columns);
}

function renderYear() {
  const container = document.getElementById('yearView');
  if (!state.calendar || state.activeView !== 'year') return;
  container.innerHTML = '';

  const grid = document.createElement('div');
  grid.className = 'year-grid';

  for (let m = 0; m < 12; m++) {
    const monthDate = makeDate(`${state.calendar.year}-${String(m + 1).padStart(2, '0')}-01`);
    const card = document.createElement('div');
    card.className = 'month-card';

    const header = document.createElement('header');
    const name = document.createElement('div');
    name.textContent = monthDate.toLocaleDateString('de-DE', { month: 'long' });
    const count = document.createElement('div');
    count.className = 'badge';
    const events = getEventsForMonth(monthDate).sort((a, b) => a.date.localeCompare(b.date));
    count.textContent = `${events.length} Termin${events.length === 1 ? '' : 'e'}`;
    header.appendChild(name);
    header.appendChild(count);
    card.appendChild(header);

    const list = document.createElement('div');
    list.className = 'event-list';
    const preview = events.slice(0, 4);
    if (!preview.length) {
      const empty = document.createElement('div');
      empty.className = 'empty';
      empty.textContent = 'Keine Einträge';
      list.appendChild(empty);
    } else {
      preview.forEach((ev) => {
        const chip = renderEventChip(ev);
        chip.addEventListener('click', () => {
          state.currentMonth = monthDate;
          setView('calendar');
          openOverlay(ev);
        });
        list.appendChild(chip);
      });
    }

    card.appendChild(list);
    grid.appendChild(card);
  }

  container.appendChild(grid);
}

function renderEventChip(ev) {
  const chip = document.createElement('div');
  chip.className = 'event-chip';
  chip.dataset.eventId = ev.id;

  const dot = document.createElement('span');
  dot.className = 'dot';
  dot.style.background = ev.color || 'var(--primary)';
  chip.appendChild(dot);

  const title = document.createElement('span');
  title.className = 'title';
  title.textContent = ev.title;
  chip.appendChild(title);

  const note = document.createElement('span');
  note.className = 'note';
  note.textContent = `${ev.group} · ${formatDate(ev.date)}`;
  chip.appendChild(note);

  chip.addEventListener('click', (e) => {
    e.stopPropagation();
    openOverlay(ev);
  });

  return chip;
}

function openOverlay(ev = {}) {
  if (!state.calendar) return;
  const overlay = document.getElementById('eventOverlay');
  const form = document.getElementById('eventForm');
  const title = document.getElementById('overlayTitle');
  const deleteBtn = document.getElementById('deleteEventBtn');
  const status = document.getElementById('overlayStatus');

  status.textContent = '';
  state.editingId = ev.id || null;
  title.textContent = ev.id ? 'Termin bearbeiten' : 'Neuer Termin';
  deleteBtn.style.visibility = ev.id ? 'visible' : 'hidden';

  form.title.value = ev.title || '';
  form.date.value = ev.date || toISO(state.currentMonth);
  renderGroupOptions(ev.group);
  form.color.value = ev.color || '';
  form.notes.value = ev.notes || '';

  overlay.classList.remove('hidden');
}

function closeOverlay() {
  document.getElementById('eventOverlay').classList.add('hidden');
  document.getElementById('overlayStatus').textContent = '';
  state.editingId = null;
}

function renderGroupOptions(selected) {
  const input = document.querySelector('input[name=\"group\"]');
  const list = document.getElementById('groupOptions');
  list.innerHTML = '';
  state.calendar.groups.forEach((group) => {
    const opt = document.createElement('option');
    opt.value = group;
    list.appendChild(opt);
  });
  if (selected) {
    input.value = selected;
  } else if (!input.value && state.calendar.groups.length) {
    input.value = state.calendar.groups[0];
  }
}

async function handleFormSubmit(e) {
  e.preventDefault();
  if (!state.calendar) return;
  const form = e.target;
  const overlayStatus = document.getElementById('overlayStatus');
  const event = {
    id: state.editingId || undefined,
    title: form.title.value.trim(),
    date: form.date.value,
    group: form.group.value,
    color: form.color.value.trim(),
    notes: form.notes.value.trim(),
  };

  if (!event.title || !event.date || !event.group) {
    overlayStatus.textContent = 'Titel, Datum und Gruppe sind erforderlich.';
    return;
  }

  state.saving = true;
  overlayStatus.textContent = 'Speichere …';

  try {
    const res = await fetch('./api/calendar.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ event }),
    });
    const data = await res.json();
    if (!res.ok || data.error) {
      throw new Error(data.error || `HTTP ${res.status}`);
    }
    state.calendar = data.calendar;
    closeOverlay();
    setStatus('Termin gespeichert', 'success');
    render();
  } catch (err) {
    console.error(err);
    overlayStatus.textContent = 'Speichern fehlgeschlagen.';
    setStatus('Speichern fehlgeschlagen', 'error');
  } finally {
    state.saving = false;
  }
}

async function handleDeleteEvent() {
  if (!state.editingId) {
    closeOverlay();
    return;
  }
  const overlayStatus = document.getElementById('overlayStatus');
  overlayStatus.textContent = 'Lösche …';
  try {
    const res = await fetch(`./api/calendar.php?id=${encodeURIComponent(state.editingId)}`, { method: 'DELETE' });
    const data = await res.json();
    if (!res.ok || data.error) {
      throw new Error(data.error || `HTTP ${res.status}`);
    }
    state.calendar = data.calendar;
    setStatus('Termin gelöscht', 'success');
    closeOverlay();
    render();
  } catch (err) {
    console.error(err);
    overlayStatus.textContent = 'Löschen fehlgeschlagen.';
    setStatus('Löschen fehlgeschlagen', 'error');
  }
}

function getEventsForDay(isoDate) {
  return (state.calendar?.events || []).filter((ev) => ev.date === isoDate);
}

function getEventsForMonth(date) {
  const month = date.getUTCMonth();
  const year = date.getUTCFullYear();
  return (state.calendar?.events || []).filter((ev) => {
    const d = parseISO(ev.date);
    return d.getUTCFullYear() === year && d.getUTCMonth() === month;
  });
}

function getEventsByGroup(group) {
  return (state.calendar?.events || [])
    .filter((ev) => ev.group === group)
    .sort((a, b) => a.date.localeCompare(b.date));
}

function setStatus(message, type = 'neutral') {
  const bar = document.getElementById('statusBar');
  bar.textContent = message;
  bar.classList.remove('error', 'success');
  if (type === 'error') bar.classList.add('error');
  if (type === 'success') bar.classList.add('success');
}

function createCell(text, className) {
  const el = document.createElement('div');
  el.className = className;
  el.textContent = text;
  return el;
}

function makeDate(iso) {
  const [y, m, d] = iso.split('-').map(Number);
  return new Date(Date.UTC(y, m - 1, d));
}

function toISO(date) {
  return date.toISOString().slice(0, 10);
}

function addMonths(date, delta) {
  const d = new Date(date);
  d.setUTCMonth(d.getUTCMonth() + delta, 1);
  return d;
}

function addDays(date, delta) {
  const d = new Date(date);
  d.setUTCDate(d.getUTCDate() + delta);
  return d;
}

function startOfWeek(date) {
  const d = new Date(date);
  const day = (d.getUTCDay() + 6) % 7;
  return addDays(d, -day);
}

function endOfWeek(date) {
  return addDays(startOfWeek(date), 6);
}

function firstOfMonth(date) {
  return new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), 1));
}

function endOfMonth(date) {
  return new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + 1, 0));
}

function getDaysOfMonth(monthDate) {
  const days = [];
  const total = endOfMonth(monthDate).getUTCDate();
  for (let i = 1; i <= total; i++) {
    days.push(new Date(Date.UTC(monthDate.getUTCFullYear(), monthDate.getUTCMonth(), i)));
  }
  return days;
}

function parseISO(iso) {
  return makeDate(iso);
}

function formatDate(dateOrIso) {
  const d = typeof dateOrIso === 'string' ? parseISO(dateOrIso) : dateOrIso;
  return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function formatWeekday(date) {
  return date.toLocaleDateString('de-DE', { weekday: 'short' });
}
