const state = {
  today: new Date(),
  current: new Date(),
  holidaysByYear: {},
  loading: false,
  error: null,
};

const dowLabels = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('prevBtn').addEventListener('click', () => moveMonth(-1));
  document.getElementById('nextBtn').addEventListener('click', () => moveMonth(1));
  document.getElementById('todayBtn').addEventListener('click', () => { state.current = new Date(); render(); });
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
    const res = await fetch(`/api/holidays.php?year=${year}`);
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

async function render() {
  const monthLabel = document.getElementById('monthLabel');
  const weeksEl = document.getElementById('weeks');
  const monthStart = new Date(Date.UTC(state.current.getFullYear(), state.current.getMonth(), 1));
  const monthEnd = new Date(Date.UTC(state.current.getFullYear(), state.current.getMonth() + 1, 0));
  monthLabel.textContent = monthStart.toLocaleDateString('de-DE', { month: 'long', year: 'numeric' });

  const weeks = buildWeeks(monthStart);
  weeksEl.innerHTML = '';

  const holidays = await ensureHolidays(monthStart.getUTCFullYear());
  const monthHolidays = holidays.filter((h) => rangesOverlap(h.start, h.end, monthStart, monthEnd));

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

      cell.appendChild(header);
      cell.appendChild(badgesWrap);
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
