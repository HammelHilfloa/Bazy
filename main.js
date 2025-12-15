const categories = [
  'U11',
  'U13',
  'U15',
  'U18',
  'U21',
  'Senioren',
  'Trainingsschwerpunkt Jugend',
  'Trainingsschwerpunkt Senioren',
];

const months = [
  'Januar',
  'Februar',
  'März',
  'April',
  'Mai',
  'Juni',
  'Juli',
  'August',
  'September',
  'Oktober',
  'November',
  'Dezember',
];

const STORAGE_KEY = 'kalender-2026-events';
let events = [];
let activeMonth = 0; // 0-based
let editingId = null;

const monthSelect = document.getElementById('month-select');
const filterSelect = document.getElementById('filter-category');
const calendarTable = document.getElementById('calendar-table');
const monthTitle = document.getElementById('month-title');
const form = document.getElementById('event-form');
const formTitle = document.getElementById('form-title');
const deleteBtn = document.getElementById('btn-delete');
const resetBtn = document.getElementById('btn-reset');
const addFreshBtn = document.getElementById('btn-add-fresh');
const printMonthBtn = document.getElementById('btn-print-month');
const printYearBtn = document.getElementById('btn-print-year');
const downloadBtn = document.getElementById('btn-download');
const eventList = document.getElementById('event-list');
const legend = document.querySelector('.legend');
const yearPrint = document.getElementById('year-print');

function parseDateString(value) {
  const [year, month, day] = value.split('-').map(Number);
  return new Date(year, month - 1, day);
}

function formatDate(date) {
  const year = date.getFullYear();
  const month = `${date.getMonth() + 1}`.padStart(2, '0');
  const day = `${date.getDate()}`.padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function daysInMonth(year, monthIndex) {
  return new Date(year, monthIndex + 1, 0).getDate();
}

function weekdayLabel(year, monthIndex, day) {
  const date = new Date(year, monthIndex, day);
  return date
    .toLocaleDateString('de-DE', { weekday: 'short' })
    .replace('.', '');
}

function renderMonthSelect() {
  months.forEach((name, index) => {
    const option = document.createElement('option');
    option.value = index;
    option.textContent = `${name} 2026`;
    monthSelect.appendChild(option);
  });
  monthSelect.value = activeMonth.toString();
}

function renderCategorySelects() {
  categories.forEach((cat) => {
    const option = document.createElement('option');
    option.value = cat;
    option.textContent = cat;
    filterSelect.appendChild(option.cloneNode(true));
    document.getElementById('category').appendChild(option);
  });
}

function getColorPalette() {
  const palette = {
    U11: '#1D4ED8',
    U13: '#0EA5E9',
    U15: '#16A34A',
    U18: '#F97316',
    U21: '#2563EB',
    Senioren: '#0F4C81',
    'Trainingsschwerpunkt Jugend': '#14B8A6',
    'Trainingsschwerpunkt Senioren': '#8B5CF6',
  };
  return palette;
}

function renderLegend() {
  legend.innerHTML = '';
  const palette = getColorPalette();
  Object.entries(palette).forEach(([cat, color]) => {
    const item = document.createElement('span');
    item.className = 'legend__item';
    const dot = document.createElement('span');
    dot.className = 'legend__dot';
    dot.style.backgroundColor = color;
    item.appendChild(dot);
    item.appendChild(document.createTextNode(cat));
    legend.appendChild(item);
  });
}

function saveEvents() {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(events));
  renderEventList();
}

async function loadEvents() {
  let jsonEvents = [];
  try {
    const response = await fetch('events-2026.json');
    jsonEvents = await response.json();
  } catch (error) {
    console.error('JSON konnte nicht geladen werden', error);
  }

  const stored = localStorage.getItem(STORAGE_KEY);
  if (stored) {
    try {
      events = JSON.parse(stored);
    } catch (error) {
      console.error('Lokale Daten defekt, nutze JSON-Datei', error);
      events = jsonEvents;
    }
  } else {
    events = jsonEvents;
    saveEvents();
  }
}

function createEventElement(event, dateText) {
  const el = document.createElement('div');
  el.className = 'calendar-event';
  el.setAttribute('draggable', 'true');
  el.dataset.id = event.id;
  el.dataset.start = event.startDate;
  el.dataset.end = event.endDate;
  el.dataset.date = dateText;
  el.style.backgroundColor = event.color;
  el.innerHTML = `<strong>${event.title}</strong><span>${event.category}</span>`;

  el.addEventListener('click', () => startEditing(event.id));
  el.addEventListener('dragstart', (ev) => {
    ev.dataTransfer.setData('text/plain', event.id);
    ev.dataTransfer.effectAllowed = 'move';
  });

  return el;
}

function renderMonth(monthIndex = activeMonth) {
  activeMonth = monthIndex;
  monthTitle.textContent = `${months[monthIndex]} 2026`;
  monthSelect.value = monthIndex.toString();

  const daysCount = daysInMonth(2026, monthIndex);
  calendarTable.innerHTML = '';

  const thead = document.createElement('thead');
  const headerRow = document.createElement('tr');
  headerRow.appendChild(document.createElement('th')).textContent = 'Kategorie';

  for (let day = 1; day <= daysCount; day++) {
    const th = document.createElement('th');
    th.textContent = `${day} ${weekdayLabel(2026, monthIndex, day)}`;
    headerRow.appendChild(th);
  }
  thead.appendChild(headerRow);
  calendarTable.appendChild(thead);

  const tbody = document.createElement('tbody');
  const filter = filterSelect.value;
  const visibleCategories = filter === 'all' ? categories : [filter];

  visibleCategories.forEach((cat) => {
    const row = document.createElement('tr');
    const header = document.createElement('th');
    header.textContent = cat;
    row.appendChild(header);

    for (let day = 1; day <= daysCount; day++) {
      const dateText = formatDate(new Date(2026, monthIndex, day));
      const cell = document.createElement('td');
      cell.dataset.date = dateText;
      cell.dataset.category = cat;
      cell.addEventListener('dragover', (ev) => ev.preventDefault());
      cell.addEventListener('drop', onDropOnCell);
      row.appendChild(cell);
    }
    tbody.appendChild(row);
  });
  calendarTable.appendChild(tbody);

  placeEventsInMonth(monthIndex);
}

function placeEventsInMonth(monthIndex) {
  const filter = filterSelect.value;
  events.forEach((evt) => {
    const start = parseDateString(evt.startDate);
    const end = parseDateString(evt.endDate);
    const duration = Math.round((end - start) / (1000 * 60 * 60 * 24));

    for (let offset = 0; offset <= duration; offset++) {
      const current = new Date(start);
      current.setDate(start.getDate() + offset);
      if (current.getFullYear() !== 2026 || current.getMonth() !== monthIndex) continue;
      if (filter !== 'all' && evt.category !== filter) continue;
      const dateText = formatDate(current);
      const selector = `td[data-date="${dateText}"][data-category="${evt.category}"]`;
      const cell = calendarTable.querySelector(selector);
      if (cell) {
        const el = createEventElement(evt, dateText);
        cell.appendChild(el);
      }
    }
  });
}

function renderEventList() {
  eventList.innerHTML = '';
  const sorted = [...events].sort((a, b) => a.startDate.localeCompare(b.startDate));
  sorted.forEach((evt) => {
    const li = document.createElement('li');
    li.className = 'event-list__item';
    li.innerHTML = `
      <div>
        <strong>${evt.title}</strong>
        <p class="th-text-muted">${evt.category} • ${evt.startDate} – ${evt.endDate}</p>
      </div>
      <button class="th-btn th-btn-secondary" data-edit="${evt.id}">Bearbeiten</button>`;
    li.querySelector('button').addEventListener('click', () => startEditing(evt.id));
    eventList.appendChild(li);
  });
}

function resetForm() {
  form.reset();
  editingId = null;
  formTitle.textContent = 'Termin hinzufügen';
  deleteBtn.disabled = true;
}

function startEditing(id) {
  const evt = events.find((item) => item.id === id);
  if (!evt) return;
  editingId = id;
  formTitle.textContent = 'Termin bearbeiten';
  deleteBtn.disabled = false;

  form.title.value = evt.title;
  form.description.value = evt.description;
  form['start-date'].value = evt.startDate;
  form['end-date'].value = evt.endDate;
  form.category.value = evt.category;
  form.color.value = evt.color;
}

function onDropOnCell(ev) {
  ev.preventDefault();
  const eventId = ev.dataTransfer.getData('text/plain');
  const evt = events.find((item) => item.id === eventId);
  if (!evt) return;

  const newDate = ev.currentTarget.dataset.date;
  const oldStart = parseDateString(evt.startDate);
  const oldEnd = parseDateString(evt.endDate);
  const dropDate = parseDateString(newDate);
  const offset = Math.round((dropDate - oldStart) / (1000 * 60 * 60 * 24));

  const newStart = new Date(oldStart);
  newStart.setDate(oldStart.getDate() + offset);
  const newEnd = new Date(oldEnd);
  newEnd.setDate(oldEnd.getDate() + offset);

  evt.startDate = formatDate(newStart);
  evt.endDate = formatDate(newEnd);
  renderMonth(activeMonth);
  saveEvents();
}

function handleFormSubmit(ev) {
  ev.preventDefault();
  const title = form.title.value.trim();
  const description = form.description.value.trim();
  const startDate = form['start-date'].value;
  const endDate = form['end-date'].value;
  const category = form.category.value;
  const color = form.color.value;

  if (!title || !startDate || !endDate) return;
  if (parseDateString(startDate) > parseDateString(endDate)) {
    alert('Das Enddatum darf nicht vor dem Start liegen.');
    return;
  }

  if (editingId) {
    const evt = events.find((item) => item.id === editingId);
    Object.assign(evt, { title, description, startDate, endDate, category, color });
  } else {
    events.push({
      id: `evt-${Date.now()}`,
      title,
      description,
      startDate,
      endDate,
      category,
      color,
    });
  }

  renderMonth(activeMonth);
  saveEvents();
  resetForm();
}

function downloadJson() {
  const blob = new Blob([JSON.stringify(events, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'events-2026-updated.json';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

function printMonth() {
  document.body.dataset.printMode = 'month';
  window.print();
}

function buildMonthTable(monthIndex) {
  const container = document.createElement('div');
  container.className = 'year-print__month';
  const title = document.createElement('h3');
  title.textContent = `${months[monthIndex]} 2026`;
  container.appendChild(title);

  const table = document.createElement('table');
  table.className = 'calendar-table calendar-table--print';

  const thead = document.createElement('thead');
  const headerRow = document.createElement('tr');
  headerRow.appendChild(document.createElement('th')).textContent = 'Kategorie';
  const days = daysInMonth(2026, monthIndex);
  for (let day = 1; day <= days; day++) {
    const th = document.createElement('th');
    th.textContent = `${day} ${weekdayLabel(2026, monthIndex, day)}`;
    headerRow.appendChild(th);
  }
  thead.appendChild(headerRow);
  table.appendChild(thead);

  const tbody = document.createElement('tbody');
  categories.forEach((cat) => {
    const row = document.createElement('tr');
    const header = document.createElement('th');
    header.textContent = cat;
    row.appendChild(header);

    for (let day = 1; day <= days; day++) {
      const dateText = formatDate(new Date(2026, monthIndex, day));
      const cell = document.createElement('td');
      cell.dataset.date = dateText;
      cell.dataset.category = cat;
      row.appendChild(cell);
    }
    tbody.appendChild(row);
  });
  table.appendChild(tbody);
  container.appendChild(table);

  return { container, table };
}

function fillEventsForTable(table, monthIndex) {
  events.forEach((evt) => {
    const start = parseDateString(evt.startDate);
    const end = parseDateString(evt.endDate);
    const duration = Math.round((end - start) / (1000 * 60 * 60 * 24));

    for (let offset = 0; offset <= duration; offset++) {
      const current = new Date(start);
      current.setDate(start.getDate() + offset);
      if (current.getFullYear() !== 2026 || current.getMonth() !== monthIndex) continue;
      const selector = `td[data-date="${formatDate(current)}"][data-category="${evt.category}"]`;
      const cell = table.querySelector(selector);
      if (cell) {
        const tag = document.createElement('div');
        tag.className = 'calendar-event calendar-event--print';
        tag.style.backgroundColor = evt.color;
        tag.textContent = evt.title;
        cell.appendChild(tag);
      }
    }
  });
}

function renderYearPrint() {
  yearPrint.innerHTML = '';
  for (let monthIndex = 0; monthIndex < 12; monthIndex++) {
    const { container, table } = buildMonthTable(monthIndex);
    fillEventsForTable(table, monthIndex);
    yearPrint.appendChild(container);
  }
}

function printYear() {
  document.body.dataset.printMode = 'year';
  renderYearPrint();
  window.print();
  document.body.dataset.printMode = 'month';
}

function initEventHandlers() {
  monthSelect.addEventListener('change', (e) => renderMonth(Number(e.target.value)));
  filterSelect.addEventListener('change', () => renderMonth(activeMonth));
  form.addEventListener('submit', handleFormSubmit);
  resetBtn.addEventListener('click', resetForm);
  addFreshBtn.addEventListener('click', resetForm);
  deleteBtn.addEventListener('click', () => {
    if (!editingId) return;
    events = events.filter((evt) => evt.id !== editingId);
    resetForm();
    saveEvents();
    renderMonth(activeMonth);
  });
  downloadBtn.addEventListener('click', downloadJson);
  printMonthBtn.addEventListener('click', printMonth);
  printYearBtn.addEventListener('click', printYear);
}

async function bootstrap() {
  renderMonthSelect();
  renderCategorySelects();
  renderLegend();
  resetForm();
  await loadEvents();
  renderEventList();
  renderMonth(activeMonth);
  initEventHandlers();
}

document.addEventListener('DOMContentLoaded', bootstrap);
