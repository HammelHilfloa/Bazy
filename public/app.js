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

const API_BASE = '../api';
const YEAR = 2026;

let events = [];
let holidays = [];
let activeMonth = 0;
let editingId = null;
let csrfToken = null;

const monthSelect = document.getElementById('month-select');
const prevMonthBtn = document.getElementById('btn-prev-month');
const nextMonthBtn = document.getElementById('btn-next-month');
const filterSelect = document.getElementById('filter-category');
const calendarTable = document.getElementById('calendar-table');
const monthTitle = document.getElementById('month-title');
const layout = document.getElementById('layout');
const sidePanel = document.getElementById('side-panel');
const detailsPanel = document.getElementById('event-details');
const formPanel = document.getElementById('form-panel');
const form = document.getElementById('event-form');
const formTitle = document.getElementById('form-title');
const deleteBtn = document.getElementById('btn-delete');
const resetBtn = document.getElementById('btn-reset');
const addFreshBtn = document.getElementById('btn-add-fresh');
const closeFormBtn = document.getElementById('btn-close-form');
const closeDetailsBtn = document.getElementById('btn-close-details');
const detailsTitle = document.getElementById('details-title');
const detailsDates = document.getElementById('details-dates');
const detailsCategory = document.getElementById('details-category');
const detailsRange = document.getElementById('details-range');
const detailsDescription = document.getElementById('details-description');
const detailsEditBtn = document.getElementById('btn-details-edit');
const printMonthBtn = document.getElementById('btn-print-month');
const printYearBtn = document.getElementById('btn-print-year');
const downloadBtn = document.getElementById('btn-download');
const eventList = document.getElementById('event-list');
const legend = document.querySelector('.legend');
const yearPrint = document.getElementById('year-print');
const toast = document.getElementById('toast');

function showToast(message, variant = 'info') {
  toast.textContent = message;
  toast.dataset.variant = variant;
  toast.classList.add('toast--visible');
  setTimeout(() => toast.classList.remove('toast--visible'), 3000);
}

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

function shortenText(text, maxLength = 80) {
  const safeText = text || '';
  if (safeText.length <= maxLength) return safeText;
  return `${safeText.slice(0, maxLength - 1)}…`;
}

function daysInMonth(year, monthIndex) {
  return new Date(year, monthIndex + 1, 0).getDate();
}

function weekdayLabel(year, monthIndex, day) {
  const date = new Date(year, monthIndex, day);
  return date.toLocaleDateString('de-DE', { weekday: 'short' }).replace('.', '');
}

function renderMonthSelect() {
  months.forEach((name, index) => {
    const option = document.createElement('option');
    option.value = index;
    option.textContent = `${name} ${YEAR}`;
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
  return {
    U11: '#1D4ED8',
    U13: '#0EA5E9',
    U15: '#16A34A',
    U18: '#F97316',
    U21: '#2563EB',
    Senioren: '#0F4C81',
    'Trainingsschwerpunkt Jugend': '#14B8A6',
    'Trainingsschwerpunkt Senioren': '#8B5CF6',
  };
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

function setPanelVisibility(mode = 'hidden') {
  const isVisible = mode !== 'hidden';
  sidePanel.classList.toggle('is-visible', isVisible);
  layout.classList.toggle('layout--with-panel', isVisible);
  detailsPanel.classList.toggle('is-hidden', mode !== 'details');
  formPanel.classList.toggle('is-hidden', mode !== 'form');
}

function hideSidePanel() {
  setPanelVisibility('hidden');
}

async function fetchCsrfToken() {
  try {
    const response = await fetch(`${API_BASE}/csrf.php`, { credentials: 'same-origin' });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const data = await response.json();
    csrfToken = data.token;
  } catch (error) {
    showToast('CSRF Token konnte nicht geladen werden.', 'error');
    console.error(error);
  }
}

async function loadEvents() {
  try {
    const response = await fetch(`${API_BASE}/events.php`, { credentials: 'same-origin' });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    events = await response.json();
    renderEventList();
    renderMonth(activeMonth);
  } catch (error) {
    showToast('Events konnten nicht geladen werden.', 'error');
    console.error(error);
  }
}

async function loadHolidays() {
  try {
    const response = await fetch(`${API_BASE}/holidays.php`, { credentials: 'same-origin' });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    holidays = await response.json();
    renderMonth(activeMonth);
  } catch (error) {
    showToast('Feiertage/Ferien konnten nicht geladen werden.', 'error');
    console.error(error);
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
  const infoText = event.description ? shortenText(event.description, 70) : 'Keine Beschreibung';
  el.innerHTML = `<strong>${event.title}</strong><span>${infoText}</span>`;

  el.addEventListener('click', () => openEventDetails(event.id));
  el.addEventListener('dragstart', (ev) => {
    ev.dataTransfer.setData('text/plain', event.id);
    ev.dataTransfer.effectAllowed = 'move';
  });

  return el;
}

function buildHolidayRow(daysCount, monthIndex, forPrint = false) {
  const row = document.createElement('tr');
  row.className = 'holiday-row';
  const header = document.createElement('th');
  header.textContent = 'Ferien / Feiertage';
  row.appendChild(header);

  for (let day = 1; day <= daysCount; day++) {
    const dateText = formatDate(new Date(YEAR, monthIndex, day));
    const cell = document.createElement('td');
    cell.dataset.date = dateText;
    cell.className = 'holiday-cell';

    const relevant = holidays.filter((item) => {
      const start = parseDateString(item.start);
      const end = parseDateString(item.end);
      const current = parseDateString(dateText);
      return current >= start && current <= end;
    });

    relevant.forEach((item) => {
      const badge = document.createElement('span');
      badge.className = `holiday-chip holiday-chip--${item.type}`;
      badge.textContent = item.name;
      if (forPrint) badge.classList.add('holiday-chip--print');
      cell.appendChild(badge);
    });

    row.appendChild(cell);
  }

  return row;
}

function renderMonth(monthIndex = activeMonth) {
  activeMonth = monthIndex;
  monthTitle.textContent = `${months[monthIndex]} ${YEAR}`;
  monthSelect.value = monthIndex.toString();

  const daysCount = daysInMonth(YEAR, monthIndex);
  calendarTable.innerHTML = '';

  const thead = document.createElement('thead');
  const headerRow = document.createElement('tr');
  headerRow.appendChild(document.createElement('th')).textContent = 'Kategorie';

  for (let day = 1; day <= daysCount; day++) {
    const th = document.createElement('th');
    th.textContent = `${day} ${weekdayLabel(YEAR, monthIndex, day)}`;
    headerRow.appendChild(th);
  }
  thead.appendChild(headerRow);
  calendarTable.appendChild(thead);

  const tbody = document.createElement('tbody');
  tbody.appendChild(buildHolidayRow(daysCount, monthIndex));

  const filter = filterSelect.value;
  const visibleCategories = filter === 'all' ? categories : [filter];

  visibleCategories.forEach((cat) => {
    const row = document.createElement('tr');
    const header = document.createElement('th');
    header.textContent = cat;
    row.appendChild(header);

    for (let day = 1; day <= daysCount; day++) {
      const dateText = formatDate(new Date(YEAR, monthIndex, day));
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

function changeMonth(step) {
  const next = (activeMonth + step + 12) % 12;
  renderMonth(next);
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
      if (current.getFullYear() !== YEAR || current.getMonth() !== monthIndex) continue;
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

function formatRangeText(evt) {
  return evt.startDate === evt.endDate ? evt.startDate : `${evt.startDate} – ${evt.endDate}`;
}

function openEventDetails(id) {
  const evt = events.find((item) => item.id === id);
  if (!evt) return;

  const start = parseDateString(evt.startDate);
  const end = parseDateString(evt.endDate);
  const duration = Math.round((end - start) / (1000 * 60 * 60 * 24)) + 1;

  detailsTitle.textContent = evt.title;
  detailsDates.textContent = formatRangeText(evt);
  detailsCategory.textContent = evt.category;
  detailsCategory.style.backgroundColor = evt.color;
  detailsCategory.style.color = '#fff';
  detailsRange.textContent = duration === 1 ? '1 Tag' : `${duration} Tage`;
  detailsDescription.textContent = evt.description || 'Keine Beschreibung hinterlegt.';

  detailsEditBtn.onclick = () => startEditing(id);

  setPanelVisibility('details');
}

function resetForm() {
  form.reset();
  editingId = null;
  formTitle.textContent = 'Termin hinzufügen';
  deleteBtn.disabled = true;
}

function openCreateForm() {
  resetForm();
  setPanelVisibility('form');
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

  setPanelVisibility('form');
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
  persistEvents();
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
  persistEvents();
  resetForm();
  hideSidePanel();
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
  title.textContent = `${months[monthIndex]} ${YEAR}`;
  container.appendChild(title);

  const table = document.createElement('table');
  table.className = 'calendar-table calendar-table--print';

  const thead = document.createElement('thead');
  const headerRow = document.createElement('tr');
  headerRow.appendChild(document.createElement('th')).textContent = 'Kategorie';
  const days = daysInMonth(YEAR, monthIndex);
  for (let day = 1; day <= days; day++) {
    const th = document.createElement('th');
    th.textContent = `${day} ${weekdayLabel(YEAR, monthIndex, day)}`;
    headerRow.appendChild(th);
  }
  thead.appendChild(headerRow);
  table.appendChild(thead);

  const tbody = document.createElement('tbody');
  tbody.appendChild(buildHolidayRow(days, monthIndex, true));

  categories.forEach((cat) => {
    const row = document.createElement('tr');
    const header = document.createElement('th');
    header.textContent = cat;
    row.appendChild(header);

    for (let day = 1; day <= days; day++) {
      const dateText = formatDate(new Date(YEAR, monthIndex, day));
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
      if (current.getFullYear() !== YEAR || current.getMonth() !== monthIndex) continue;
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

async function persistEvents() {
  renderEventList();
  try {
    if (!csrfToken) await fetchCsrfToken();
    const response = await fetch(`${API_BASE}/events.php`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken || '',
      },
      credentials: 'same-origin',
      body: JSON.stringify(events),
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    showToast('Änderungen gespeichert.', 'success');
  } catch (error) {
    showToast('Speichern fehlgeschlagen. Bitte erneut versuchen.', 'error');
    console.error(error);
  }
}

function initEventHandlers() {
  monthSelect.addEventListener('change', (e) => renderMonth(Number(e.target.value)));
  prevMonthBtn.addEventListener('click', () => changeMonth(-1));
  nextMonthBtn.addEventListener('click', () => changeMonth(1));
  filterSelect.addEventListener('change', () => renderMonth(activeMonth));
  form.addEventListener('submit', handleFormSubmit);
  resetBtn.addEventListener('click', () => {
    resetForm();
    hideSidePanel();
  });
  addFreshBtn.addEventListener('click', openCreateForm);
  closeFormBtn.addEventListener('click', hideSidePanel);
  closeDetailsBtn.addEventListener('click', hideSidePanel);
  deleteBtn.addEventListener('click', () => {
    if (!editingId) return;
    events = events.filter((evt) => evt.id !== editingId);
    resetForm();
    persistEvents();
    renderMonth(activeMonth);
    hideSidePanel();
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
  hideSidePanel();
  await fetchCsrfToken();
  await Promise.all([loadEvents(), loadHolidays()]);
  initEventHandlers();
}

document.addEventListener('DOMContentLoaded', bootstrap);
