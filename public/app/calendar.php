<?php
$config = require __DIR__ . '/../lib/bootstrap.php';

require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Csrf.php';

$currentUser = Auth::requireLogin();
$csrfToken = Csrf::getToken();
$baseUrl = rtrim($config['base_url'] ?? '', '/');
$appName = htmlspecialchars($config['app_name'] ?? 'Vereinskalender', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kalender | <?php echo $appName; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css">
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background: #f7f7f7;
            color: #0f172a;
        }
        a { color: #1e88e5; text-decoration: none; }
        .page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 18px;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        .topbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        h1 {
            margin: 0;
            font-size: 1.4rem;
        }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            background: #e3f2fd;
            color: #1565c0;
            border-radius: 999px;
            font-size: 0.95rem;
        }
        .top-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        button {
            cursor: pointer;
            border: none;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.95rem;
            font-weight: 600;
            transition: background 0.15s ease, transform 0.15s ease;
        }
        .btn-primary { background: #1e88e5; color: #fff; }
        .btn-secondary { background: #e2e8f0; color: #1f2933; }
        .btn-danger { background: #ef4444; color: #fff; }
        button:hover { transform: translateY(-1px); }
        .layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 14px;
            padding: 14px 18px 24px;
            flex: 1;
        }
        .sidebar {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.08);
            padding: 14px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            min-height: 200px;
        }
        .content {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.08);
            padding: 12px;
        }
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
        }
        .section-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
        }
        .small-btn {
            padding: 6px 10px;
            font-size: 0.9rem;
        }
        .filter-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
        }
        .filter-group input[type="search"] {
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            font-size: 0.95rem;
        }
        .toggle-row {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 0.95rem;
        }
        .checkbox-list {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px;
            max-height: 260px;
            overflow: auto;
        }
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 6px;
            border-radius: 8px;
            transition: background 0.12s ease;
        }
        .checkbox-item:hover { background: #f8fafc; }
        .color-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 1px solid #cbd5e1;
        }
        .meta {
            color: #475569;
            font-size: 0.95rem;
            margin-top: 4px;
        }
        .tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            background: #f1f5f9;
            border-radius: 999px;
            font-size: 0.9rem;
            color: #0f172a;
        }
        .fc-theme-standard .fc-button-primary {
            background: #1e88e5;
            border-color: #1e88e5;
        }
        .fc-theme-standard .fc-button-primary:not(:disabled).fc-button-active,
        .fc-theme-standard .fc-button-primary:not(:disabled):active {
            background: #1565c0;
            border-color: #1565c0;
        }
        .fc .fc-toolbar-title { font-size: 1.2rem; }
        .fc .fc-daygrid-event { border-radius: 6px; padding: 2px 4px; }
        .fc .fc-list-event-dot { border-radius: 50%; }
        .fc .fc-col-header-cell-cushion { padding: 8px 4px; }
        .muted { color: #475569; }
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            z-index: 20;
        }
        .modal {
            background: #fff;
            border-radius: 12px;
            padding: 18px;
            width: 100%;
            max-width: 760px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .icon-button {
            background: transparent;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: #4b5563;
            padding: 6px;
        }
        form label {
            display: block;
            font-weight: 700;
            margin: 12px 0 6px;
        }
        form input[type="text"],
        form input[type="url"],
        form input[type="search"],
        form input[type="number"],
        form input[type="date"],
        form input[type="time"],
        form textarea,
        form select {
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            font-size: 0.95rem;
        }
        form textarea { min-height: 90px; resize: vertical; }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
        }
        .radio-row, .toggle-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .chip-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 10px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            cursor: pointer;
        }
        .chip input { margin: 0; }
        .form-footer {
            margin-top: 14px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .status {
            margin-top: 10px;
            padding: 10px 12px;
            border-radius: 8px;
            font-weight: 600;
            display: none;
        }
        .status.error { background: #fee2e2; color: #991b1b; }
        .status.success { background: #e0f2fe; color: #0369a1; }
        .pill-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .btn-ghost {
            background: transparent;
            color: #0f172a;
        }
        .section-divider {
            border: none;
            border-top: 1px solid #e2e8f0;
            margin: 14px 0;
        }
        .form-tabs {
            display: inline-flex;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            overflow: hidden;
        }
        .form-tab {
            padding: 10px 14px;
            background: #f8fafc;
            border-right: 1px solid #cbd5e1;
            cursor: pointer;
            font-weight: 700;
            color: #475569;
        }
        .form-tab:last-child { border-right: none; }
        .form-tab.active {
            background: #1e88e5;
            color: #fff;
            border-color: #1e88e5;
        }
        @media (max-width: 960px) {
            .layout { grid-template-columns: 1fr; }
            .sidebar { order: 2; }
            .content { order: 1; }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="topbar">
            <div class="topbar-left">
                <h1><?php echo $appName; ?></h1>
                <span class="pill">Kalender</span>
            </div>
            <div class="top-actions">
                <span class="muted">Angemeldet als <strong><?php echo htmlspecialchars($currentUser['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong> (<?php echo htmlspecialchars($currentUser['role'] ?? '', ENT_QUOTES, 'UTF-8'); ?>)</span>
                <?php if (in_array($currentUser['role'] ?? '', ['admin', 'editor'], true)) : ?>
                    <a class="btn-secondary" href="<?php echo htmlspecialchars($baseUrl . '/app/json_tools.php', ENT_QUOTES, 'UTF-8'); ?>">JSON-Tools</a>
                <?php endif; ?>
                <button id="btn-logout" class="btn-secondary" type="button">Abmelden</button>
                <button id="btn-new-event" class="btn-primary" type="button">Neuer Termin</button>
            </div>
        </div>

        <div class="layout">
            <aside class="sidebar">
                <div>
                    <div class="section-header">
                        <h3 class="section-title">Kategorien</h3>
                        <div>
                            <button id="btn-cat-all" class="small-btn btn-secondary">Alle</button>
                            <button id="btn-cat-none" class="small-btn btn-ghost">Keine</button>
                        </div>
                    </div>
                    <div id="category-list" class="checkbox-list">
                        <div class="muted">Lade Kategorien...</div>
                    </div>
                </div>
                <div class="filter-group">
                    <label for="search-input">Suche</label>
                    <input type="search" id="search-input" placeholder="Titel, Beschreibung, Ort">
                </div>
                <div class="filter-group">
                    <label class="toggle-row">
                        <input type="checkbox" id="toggle-system" checked>
                        Ferien/Feiertage anzeigen
                    </label>
                    <p class="muted" style="margin:6px 0 0;">Systemtermine (Ferien, Feiertage) ein- oder ausblenden.</p>
                </div>
            </aside>

            <main class="content">
                <div id="calendar"></div>
            </main>
        </div>
    </div>

    <div class="modal-backdrop" id="detail-modal" hidden>
        <div class="modal">
            <div class="modal-header">
                <div>
                    <h2 id="detail-title" style="margin:0;"></h2>
                    <p id="detail-time" class="meta" style="margin:4px 0 0;"></p>
                </div>
                <button class="icon-button" type="button" data-close-modal="detail-modal" aria-label="Schließen">✕</button>
            </div>
            <p id="detail-category" class="tag" style="margin:8px 0;"></p>
            <div id="detail-description" class="muted" style="white-space:pre-wrap;"></div>
            <div id="detail-location" class="muted" style="margin-top:6px;"></div>
            <div id="detail-series" class="muted" style="margin-top:6px; display:none;"></div>
            <div class="form-footer" style="margin-top:14px;">
                <button id="btn-detail-edit" class="btn-primary" type="button" hidden>Bearbeiten</button>
                <button id="btn-detail-delete" class="btn-danger" type="button" hidden>Löschen</button>
                <button id="btn-detail-occurrence-edit" class="btn-secondary" type="button" hidden>Nur dieses Vorkommen bearbeiten</button>
                <button id="btn-detail-series-edit" class="btn-secondary" type="button" hidden>Serie bearbeiten</button>
                <button id="btn-detail-occurrence-cancel" class="btn-secondary" type="button" hidden>Dieses Vorkommen absagen</button>
                <button class="btn-ghost" type="button" data-close-modal="detail-modal">Schließen</button>
            </div>
            <div id="detail-status" class="status"></div>
        </div>
    </div>

    <div class="modal-backdrop" id="form-modal" hidden>
        <div class="modal">
            <div class="modal-header">
                <div>
                    <h2 id="form-title" style="margin:0;">Neuer Termin</h2>
                    <p id="form-subtitle" class="muted" style="margin:4px 0 0;">Einzeltermin oder Serie erstellen.</p>
                </div>
                <button class="icon-button" type="button" data-close-modal="form-modal" aria-label="Schließen">✕</button>
            </div>
            <div style="margin-top:10px; margin-bottom:12px;">
                <div class="form-tabs" role="tablist">
                    <div class="form-tab active" data-form-tab="single">Einzeltermin</div>
                    <div class="form-tab" data-form-tab="series">Serie</div>
                </div>
            </div>
            <form id="single-form" novalidate>
                <div class="form-grid">
                    <div>
                        <label for="single-title">Titel *</label>
                        <input type="text" id="single-title" required>
                    </div>
                    <div>
                        <label for="single-category">Kategorie *</label>
                        <select id="single-category" required></select>
                    </div>
                </div>
                <label class="toggle-row" style="margin-top:10px;">
                    <input type="checkbox" id="single-all-day">
                    Ganztägig
                </label>
                <div class="form-grid">
                    <div>
                        <label for="single-start-date">Startdatum *</label>
                        <input type="date" id="single-start-date" required>
                    </div>
                    <div>
                        <label for="single-start-time">Startzeit</label>
                        <input type="time" id="single-start-time" step="60">
                    </div>
                </div>
                <div class="form-grid">
                    <div>
                        <label for="single-end-date">Enddatum</label>
                        <input type="date" id="single-end-date">
                    </div>
                    <div>
                        <label for="single-end-time">Endzeit</label>
                        <input type="time" id="single-end-time" step="60">
                    </div>
                </div>
                <label for="single-location">Ort (Text)</label>
                <input type="text" id="single-location" placeholder="z. B. Sporthalle">
                <label for="single-location-url">Ort-URL</label>
                <input type="url" id="single-location-url" placeholder="https://example.com">
                <label for="single-description">Beschreibung</label>
                <textarea id="single-description" placeholder="Details..."></textarea>
            </form>

            <form id="series-form" novalidate hidden>
                <div class="form-grid">
                    <div>
                        <label for="series-title">Titel *</label>
                        <input type="text" id="series-title" required>
                    </div>
                    <div>
                        <label for="series-category">Kategorie *</label>
                        <select id="series-category" required></select>
                    </div>
                </div>
                <label class="toggle-row" style="margin-top:10px;">
                    <input type="checkbox" id="series-all-day">
                    Ganztägig
                </label>
                <div class="form-grid">
                    <div>
                        <label for="series-start-date">Startdatum *</label>
                        <input type="date" id="series-start-date" required>
                    </div>
                    <div>
                        <label for="series-start-time">Startzeit</label>
                        <input type="time" id="series-start-time" step="60">
                    </div>
                </div>
                <div class="form-grid">
                    <div>
                        <label for="series-end-date">Enddatum (optional)</label>
                        <input type="date" id="series-end-date">
                    </div>
                    <div>
                        <label for="series-end-time">Endzeit</label>
                        <input type="time" id="series-end-time" step="60">
                    </div>
                </div>
                <label>Wochentage</label>
                <div class="chip-group" id="series-weekdays"></div>
                <div class="form-grid">
                    <div>
                        <label for="series-interval">Intervall (Wochen)</label>
                        <input type="number" id="series-interval" min="1" value="1">
                    </div>
                    <div>
                        <label for="series-count">Anzahl (optional)</label>
                        <input type="number" id="series-count" min="1" placeholder="z. B. 10">
                    </div>
                </div>
                <label for="series-until">Enddatum (optional)</label>
                <input type="date" id="series-until">
                <label class="toggle-row" style="margin-top:10px;">
                    <input type="checkbox" id="series-skip-holidays" checked>
                    außer Ferien/Feiertage
                </label>
                <label for="series-location">Ort (Text)</label>
                <input type="text" id="series-location" placeholder="z. B. Sporthalle">
                <label for="series-location-url">Ort-URL</label>
                <input type="url" id="series-location-url" placeholder="https://example.com">
                <label for="series-description">Beschreibung</label>
                <textarea id="series-description" placeholder="Details..."></textarea>
            </form>

            <div id="form-status" class="status"></div>
            <div class="form-footer">
                <button id="btn-form-save" class="btn-primary" type="button">Speichern</button>
                <button class="btn-secondary" type="button" data-close-modal="form-modal">Abbrechen</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <script>
        const baseUrl = <?php echo json_encode($baseUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const csrfToken = <?php echo json_encode($csrfToken, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const currentUser = <?php echo json_encode($currentUser, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const canEdit = ['admin', 'editor'].includes(currentUser.role || '');

        const endpoints = {
            eventsList: `${baseUrl}/api/events/list.php`,
            eventsCreate: `${baseUrl}/api/events/create.php`,
            eventsUpdate: `${baseUrl}/api/events/update.php`,
            eventsDelete: `${baseUrl}/api/events/delete.php`,
            seriesCreate: `${baseUrl}/api/series/create.php`,
            seriesUpdate: `${baseUrl}/api/series/update.php`,
            seriesDelete: `${baseUrl}/api/series/delete.php`,
            overrideModify: `${baseUrl}/api/series/override_modify.php`,
            overrideCancel: `${baseUrl}/api/series/override_cancel.php`,
            categoriesList: `${baseUrl}/api/categories/list.php`,
            logout: `${baseUrl}/api/auth/logout.php`,
        };

        const categoryListEl = document.getElementById('category-list');
        const toggleSystemEl = document.getElementById('toggle-system');
        const searchInputEl = document.getElementById('search-input');
        const btnCatAll = document.getElementById('btn-cat-all');
        const btnCatNone = document.getElementById('btn-cat-none');
        const btnNewEvent = document.getElementById('btn-new-event');
        const btnLogout = document.getElementById('btn-logout');

        const detailModal = document.getElementById('detail-modal');
        const detailTitle = document.getElementById('detail-title');
        const detailTime = document.getElementById('detail-time');
        const detailCategory = document.getElementById('detail-category');
        const detailDescription = document.getElementById('detail-description');
        const detailLocation = document.getElementById('detail-location');
        const detailSeries = document.getElementById('detail-series');
        const detailStatus = document.getElementById('detail-status');
        const btnDetailEdit = document.getElementById('btn-detail-edit');
        const btnDetailDelete = document.getElementById('btn-detail-delete');
        const btnDetailOccurrenceEdit = document.getElementById('btn-detail-occurrence-edit');
        const btnDetailSeriesEdit = document.getElementById('btn-detail-series-edit');
        const btnDetailOccurrenceCancel = document.getElementById('btn-detail-occurrence-cancel');

        const formModal = document.getElementById('form-modal');
        const formTitle = document.getElementById('form-title');
        const formSubtitle = document.getElementById('form-subtitle');
        const formStatus = document.getElementById('form-status');
        const btnFormSave = document.getElementById('btn-form-save');
        const formTabs = document.querySelectorAll('.form-tab');

        const singleForm = document.getElementById('single-form');
        const singleTitle = document.getElementById('single-title');
        const singleCategory = document.getElementById('single-category');
        const singleAllDay = document.getElementById('single-all-day');
        const singleStartDate = document.getElementById('single-start-date');
        const singleStartTime = document.getElementById('single-start-time');
        const singleEndDate = document.getElementById('single-end-date');
        const singleEndTime = document.getElementById('single-end-time');
        const singleLocation = document.getElementById('single-location');
        const singleLocationUrl = document.getElementById('single-location-url');
        const singleDescription = document.getElementById('single-description');

        const seriesForm = document.getElementById('series-form');
        const seriesTitle = document.getElementById('series-title');
        const seriesCategory = document.getElementById('series-category');
        const seriesAllDay = document.getElementById('series-all-day');
        const seriesStartDate = document.getElementById('series-start-date');
        const seriesStartTime = document.getElementById('series-start-time');
        const seriesEndDate = document.getElementById('series-end-date');
        const seriesEndTime = document.getElementById('series-end-time');
        const seriesWeekdays = document.getElementById('series-weekdays');
        const seriesInterval = document.getElementById('series-interval');
        const seriesCount = document.getElementById('series-count');
        const seriesUntil = document.getElementById('series-until');
        const seriesSkipHolidays = document.getElementById('series-skip-holidays');
        const seriesLocation = document.getElementById('series-location');
        const seriesLocationUrl = document.getElementById('series-location-url');
        const seriesDescription = document.getElementById('series-description');

        const weekdayOptions = [
            { key: 'MO', label: 'Mo' },
            { key: 'TU', label: 'Di' },
            { key: 'WE', label: 'Mi' },
            { key: 'TH', label: 'Do' },
            { key: 'FR', label: 'Fr' },
            { key: 'SA', label: 'Sa' },
            { key: 'SU', label: 'So' },
        ];

        let calendar;
        let categories = [];
        const selectedCategoryIds = new Set();
        let activeTab = 'single';
        let currentEventData = null;
        let formMode = { type: 'single', action: 'create', baseEvent: null };

        function setStatus(el, message, isError = false) {
            el.textContent = message;
            el.className = 'status ' + (isError ? 'error' : 'success');
            el.style.display = message ? 'block' : 'none';
        }

        function resetStatus(el) {
            setStatus(el, '', false);
        }

        function formatDateTimeRange(start, end, allDay) {
            if (!start) return '';
            const [sDate, sTime] = splitDateTime(start);
            const [eDate, eTime] = splitDateTime(end || start);
            if (allDay) {
                return sDate === eDate ? sDate : `${sDate} – ${eDate}`;
            }
            return sDate === eDate ? `${sDate}, ${sTime} – ${eTime}` : `${sDate} ${sTime} – ${eDate} ${eTime}`;
        }

        function splitDateTime(input) {
            if (!input) return ['', ''];
            const [datePart, timePart = ''] = input.split(' ');
            const time = timePart.slice(0,5);
            return [datePart, time];
        }

        function mergeDateTime(date, time, allDay, isEnd = false) {
            if (!date) return '';
            if (allDay) {
                return `${date} ${isEnd ? '23:59:59' : '00:00:00'}`;
            }
            if (!time) return `${date} 00:00:00`;
            return `${date} ${time.length === 5 ? time + ':00' : time}`;
        }

        function renderCategories() {
            categoryListEl.innerHTML = '';
            if (!categories.length) {
                categoryListEl.innerHTML = '<div class="muted">Keine Kategorien.</div>';
                return;
            }
            categories.forEach((cat) => {
                const item = document.createElement('label');
                item.className = 'checkbox-item';
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.value = cat.id;
                checkbox.checked = selectedCategoryIds.has(cat.id);
                checkbox.addEventListener('change', () => {
                    if (checkbox.checked) {
                        selectedCategoryIds.add(cat.id);
                    } else {
                        selectedCategoryIds.delete(cat.id);
                    }
                    calendar.refetchEvents();
                });
                const dot = document.createElement('span');
                dot.className = 'color-dot';
                dot.style.background = cat.color || '#9e9e9e';
                const text = document.createElement('span');
                text.textContent = cat.name;
                item.append(checkbox, dot, text);
                categoryListEl.appendChild(item);
            });

            [singleCategory, seriesCategory].forEach((select) => {
                select.innerHTML = '<option value="">Bitte wählen</option>';
                categories.forEach((cat) => {
                    const opt = document.createElement('option');
                    opt.value = cat.id;
                    opt.textContent = cat.name;
                    select.appendChild(opt);
                });
            });
        }

        async function loadCategories() {
            categoryListEl.innerHTML = '<div class="muted">Lade Kategorien...</div>';
            try {
                const res = await fetch(endpoints.categoriesList, { credentials: 'same-origin' });
                const data = await res.json();
                if (!data.success) throw new Error(data.error || 'Fehler beim Laden');
                categories = (data.data.categories || []).filter((c) => c.is_active === 1);
                categories.sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0) || a.name.localeCompare(b.name));
                categories.forEach((c) => selectedCategoryIds.add(c.id));
                renderCategories();
            } catch (error) {
                categoryListEl.innerHTML = `<div class="status error" style="display:block;">${error.message || 'Fehler beim Laden der Kategorien.'}</div>`;
            }
        }

        function mapApiEvent(evt) {
            return {
                id: evt.id,
                title: evt.title,
                start: evt.start_at,
                end: evt.end_at,
                allDay: evt.all_day === 1,
                backgroundColor: evt.color || undefined,
                borderColor: evt.color || undefined,
                extendedProps: {
                    ...evt,
                },
            };
        }

        async function fetchEvents(info, success, failure) {
            const params = new URLSearchParams();
            params.set('from', info.startStr.slice(0, 10));
            params.set('to', info.endStr.slice(0, 10));
            if (searchInputEl.value.trim() !== '') {
                params.set('q', searchInputEl.value.trim());
            }
            if (!toggleSystemEl.checked) {
                params.set('include_system', '0');
            } else {
                params.set('include_system', '1');
            }
            if (selectedCategoryIds.size > 0 && selectedCategoryIds.size !== categories.length) {
                params.set('category_ids', Array.from(selectedCategoryIds).join(','));
            }

            try {
                const res = await fetch(`${endpoints.eventsList}?${params.toString()}`, { credentials: 'same-origin' });
                const data = await res.json();
                if (!data.success) throw new Error(data.error || 'Events konnten nicht geladen werden.');
                const events = (data.data.events || []).map(mapApiEvent);
                success(events);
            } catch (error) {
                failure(error);
            }
        }

        function openModal(modal) { modal.hidden = false; }
        function closeModal(modal) { modal.hidden = true; }

        function populateDetail(event) {
            const start = event.start_at || event.start || '';
            const end = event.end_at || event.end || start;
            currentEventData = { ...event, start, end };
            resetStatus(detailStatus);
            detailTitle.textContent = currentEventData.title || 'Termin';
            detailTime.textContent = formatDateTimeRange(start, end, currentEventData.all_day === 1);
            const categoryName = categories.find((c) => c.id === currentEventData.category_id)?.name || 'Kategorie';
            detailCategory.textContent = categoryName;
            detailCategory.style.background = '#f1f5f9';
            if (currentEventData.description) {
                detailDescription.textContent = currentEventData.description;
                detailDescription.style.display = 'block';
            } else {
                detailDescription.style.display = 'none';
            }
            if (currentEventData.location_text || currentEventData.location_url) {
                detailLocation.innerHTML = '';
                const strong = document.createElement('strong');
                strong.textContent = 'Ort:';
                detailLocation.appendChild(strong);
                if (currentEventData.location_text) {
                    detailLocation.appendChild(document.createTextNode(' ' + currentEventData.location_text));
                }
                if (currentEventData.location_url) {
                    const link = document.createElement('a');
                    link.href = currentEventData.location_url;
                    link.target = '_blank';
                    link.rel = 'noreferrer';
                    link.textContent = 'Link';
                    detailLocation.appendChild(document.createTextNode(' '));
                    detailLocation.appendChild(link);
                }
                detailLocation.style.display = 'block';
            } else {
                detailLocation.style.display = 'none';
            }
            if (currentEventData.is_series) {
                detailSeries.textContent = 'Serientermin';
                detailSeries.style.display = 'block';
            } else {
                detailSeries.style.display = 'none';
            }

            btnDetailEdit.hidden = !canEdit;
            btnDetailDelete.hidden = !canEdit;
            btnDetailOccurrenceEdit.hidden = !(canEdit && currentEventData.is_series);
            btnDetailSeriesEdit.hidden = !(canEdit && currentEventData.is_series);
            btnDetailOccurrenceCancel.hidden = !(canEdit && currentEventData.is_series);
        }

        async function apiPost(url, payload) {
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify(payload),
            });
            return res.json();
        }

        async function handleLogout() {
            try {
                const res = await apiPost(endpoints.logout, {});
                if (!res.success) throw new Error(res.error || 'Abmeldung fehlgeschlagen.');
            } catch (error) {
                alert(error.message || 'Abmeldung fehlgeschlagen.');
                return;
            }
            window.location.href = `${baseUrl}/app/login.php`;
        }

        function clearForms() {
            singleForm.reset();
            seriesForm.reset();
            singleAllDay.checked = false;
            seriesAllDay.checked = false;
            seriesInterval.value = '1';
            seriesSkipHolidays.checked = true;
            weekdayOptions.forEach((opt) => {
                const input = document.getElementById(`weekday-${opt.key}`);
                if (input) input.checked = false;
            });
            resetStatus(formStatus);
        }

        function setActiveTab(tab) {
            activeTab = tab;
            formTabs.forEach((el) => el.classList.toggle('active', el.dataset.formTab === tab));
            singleForm.hidden = tab !== 'single';
            seriesForm.hidden = tab !== 'series';
        }

        function openCreateModal(tab = 'single') {
            formMode = { type: tab, action: 'create', baseEvent: null };
            formTitle.textContent = 'Neuer Termin';
            formSubtitle.textContent = tab === 'single' ? 'Einzeltermin erstellen.' : 'Serientermin erstellen.';
            clearForms();
            setActiveTab(tab);
            const today = new Date();
            const yyyy = today.getFullYear();
            const mm = String(today.getMonth() + 1).padStart(2, '0');
            const dd = String(today.getDate()).padStart(2, '0');
            const todayStr = `${yyyy}-${mm}-${dd}`;
            singleStartDate.value = todayStr;
            seriesStartDate.value = todayStr;
            singleCategory.value = '';
            seriesCategory.value = '';
            openModal(formModal);
        }

        function fillEventForm(event) {
            formMode = { type: 'single', action: 'edit', baseEvent: event };
            formTitle.textContent = 'Termin bearbeiten';
            formSubtitle.textContent = 'Einzeltermin anpassen.';
            clearForms();
            setActiveTab('single');
            singleTitle.value = event.title || '';
            singleCategory.value = event.category_id || '';
            singleAllDay.checked = event.all_day === 1;
            const [sDate, sTime] = splitDateTime(event.start_at || event.start);
            const [eDate, eTime] = splitDateTime(event.end_at || event.end || event.start_at || event.start);
            singleStartDate.value = sDate;
            singleStartTime.value = sTime;
            singleEndDate.value = eDate;
            singleEndTime.value = eTime;
            singleLocation.value = event.location_text || '';
            singleLocationUrl.value = event.location_url || '';
            singleDescription.value = event.description || '';
            openModal(formModal);
        }

        function fillSeriesForm(event) {
            formMode = { type: 'series', action: 'edit', baseEvent: event };
            formTitle.textContent = 'Serie bearbeiten';
            formSubtitle.textContent = 'Serientermin anpassen.';
            clearForms();
            setActiveTab('series');
            seriesTitle.value = event.title || '';
            seriesCategory.value = event.category_id || '';
            seriesAllDay.checked = event.all_day === 1;
            const [sDate, sTime] = splitDateTime(event.start_at || event.start);
            const [eDate, eTime] = splitDateTime(event.end_at || event.end || event.start_at || event.start);
            seriesStartDate.value = sDate;
            seriesStartTime.value = sTime;
            seriesEndDate.value = eDate;
            seriesEndTime.value = eTime;
            seriesLocation.value = event.location_text || '';
            seriesLocationUrl.value = event.location_url || '';
            seriesDescription.value = event.description || '';
            seriesSkipHolidays.checked = event.skip_if_holiday !== 0;
            const parsed = parseRrule(event.series_rrule || '');
            seriesInterval.value = parsed.interval || 1;
            seriesCount.value = parsed.count || '';
            seriesUntil.value = parsed.until || '';
            weekdayOptions.forEach((opt) => {
                const input = document.getElementById(`weekday-${opt.key}`);
                if (input) input.checked = parsed.byday.includes(opt.key);
            });
            openModal(formModal);
        }

        function parseRrule(rrule) {
            const result = { interval: 1, byday: [], until: '', count: '' };
            if (!rrule) return result;
            const parts = rrule.split(';').map((p) => p.split('='));
            const map = {};
            parts.forEach(([k, v]) => { if (k && v) map[k.trim().toUpperCase()] = v.trim(); });
            if (map.INTERVAL) result.interval = parseInt(map.INTERVAL, 10) || 1;
            if (map.BYDAY) result.byday = map.BYDAY.split(',').filter(Boolean);
            if (map.UNTIL) {
                result.until = map.UNTIL.slice(0, 8).replace(/(\d{4})(\d{2})(\d{2})/, '$1-$2-$3');
            }
            if (map.COUNT) result.count = parseInt(map.COUNT, 10) || '';
            return result;
        }

        function buildRrule() {
            const days = [];
            weekdayOptions.forEach((opt) => {
                const input = document.getElementById(`weekday-${opt.key}`);
                if (input && input.checked) days.push(opt.key);
            });
            return {
                byday: days,
                interval: Math.max(1, parseInt(seriesInterval.value, 10) || 1),
                until: seriesUntil.value ? seriesUntil.value.replace(/-/g, '') : '',
                count: seriesCount.value ? parseInt(seriesCount.value, 10) : '',
            };
        }

        function buildSeriesRruleString(defaultStartDay) {
            const rule = buildRrule();
            const byday = rule.byday.length ? rule.byday : [defaultStartDay];
            let rrule = `FREQ=WEEKLY;INTERVAL=${rule.interval};BYDAY=${byday.join(',')}`;
            if (rule.until) {
                rrule += `;UNTIL=${rule.until}T235959`;
            } else if (rule.count) {
                rrule += `;COUNT=${rule.count}`;
            }
            return rrule;
        }

        function collectSinglePayload() {
            const title = singleTitle.value.trim();
            const categoryId = parseInt(singleCategory.value, 10);
            const allDay = singleAllDay.checked;
            const startDate = singleStartDate.value;
            const endDate = singleEndDate.value || startDate;
            if (!title) throw new Error('Titel ist erforderlich.');
            if (!categoryId) throw new Error('Kategorie ist erforderlich.');
            if (!startDate) throw new Error('Startdatum ist erforderlich.');

            const start_at = mergeDateTime(startDate, singleStartTime.value, allDay, false);
            const end_at = mergeDateTime(endDate, singleEndTime.value || singleStartTime.value, allDay, true);

            return {
                category_id: categoryId,
                title,
                start_at,
                end_at,
                all_day: allDay ? 1 : 0,
                description: singleDescription.value.trim(),
                location_text: singleLocation.value.trim(),
                location_url: singleLocationUrl.value.trim(),
            };
        }

        function collectSeriesPayload(action) {
            const title = seriesTitle.value.trim();
            const categoryId = parseInt(seriesCategory.value, 10);
            const allDay = seriesAllDay.checked;
            const startDate = seriesStartDate.value;
            const endDate = seriesEndDate.value || startDate;
            if (!title) throw new Error('Titel ist erforderlich.');
            if (!categoryId) throw new Error('Kategorie ist erforderlich.');
            if (!startDate) throw new Error('Startdatum ist erforderlich.');
            const start_at = mergeDateTime(startDate, seriesStartTime.value, allDay, false);
            const end_at = mergeDateTime(endDate, seriesEndTime.value || seriesStartTime.value, allDay, true);
            const defaultStartDay = new Date(startDate).getDay();
            const map = ['SU','MO','TU','WE','TH','FR','SA'];
            const defaultDay = map[defaultStartDay] || 'MO';
            const rrule = buildSeriesRruleString(defaultDay);

            const payload = {
                category_id: categoryId,
                title,
                start_at,
                end_at,
                all_day: allDay ? 1 : 0,
                description: seriesDescription.value.trim(),
                location_text: seriesLocation.value.trim(),
                location_url: seriesLocationUrl.value.trim(),
                rrule,
                skip_if_holiday: seriesSkipHolidays.checked ? 1 : 0,
            };
            if (action === 'edit' && formMode.baseEvent?.series_id) {
                payload.id = formMode.baseEvent.series_id;
                payload.series_timezone = formMode.baseEvent.series_timezone || '';
            }
            return payload;
        }

        function collectOccurrencePayload(event) {
            const base = collectSinglePayload();
            return {
                ...base,
                series_id: event.series_id,
                occurrence_start: event.occurrence_start,
            };
        }

        async function handleSave() {
            resetStatus(formStatus);
            try {
                if (activeTab === 'single') {
                    const payload = collectSinglePayload();
                    if (formMode.action === 'edit' && formMode.baseEvent) {
                        payload.id = formMode.baseEvent.id;
                        const res = await apiPost(endpoints.eventsUpdate, payload);
                        if (!res.success) throw new Error(res.error || 'Aktualisierung fehlgeschlagen.');
                    } else {
                        const res = await apiPost(endpoints.eventsCreate, payload);
                        if (!res.success) throw new Error(res.error || 'Erstellung fehlgeschlagen.');
                    }
                } else {
                    const payload = collectSeriesPayload(formMode.action);
                    if (formMode.action === 'edit') {
                        const res = await apiPost(endpoints.seriesUpdate, payload);
                        if (!res.success) throw new Error(res.error || 'Aktualisierung fehlgeschlagen.');
                    } else {
                        const res = await apiPost(endpoints.seriesCreate, payload);
                        if (!res.success) throw new Error(res.error || 'Erstellung fehlgeschlagen.');
                    }
                }
                setStatus(formStatus, 'Gespeichert.', false);
                calendar.refetchEvents();
                setTimeout(() => closeModal(formModal), 350);
            } catch (error) {
                setStatus(formStatus, error.message || 'Speichern fehlgeschlagen.', true);
            }
        }

        async function deleteEvent(event) {
            resetStatus(detailStatus);
            try {
                if (event.is_series) {
                    const res = await apiPost(endpoints.overrideCancel, { series_id: event.series_id, occurrence_start: event.occurrence_start });
                    if (!res.success) throw new Error(res.error || 'Vorkommen konnte nicht abgesagt werden.');
                } else {
                    const res = await apiPost(endpoints.eventsDelete, { id: event.id });
                    if (!res.success) throw new Error(res.error || 'Löschen fehlgeschlagen.');
                }
                setStatus(detailStatus, 'Erfolgreich aktualisiert.', false);
                calendar.refetchEvents();
                setTimeout(() => closeModal(detailModal), 400);
            } catch (error) {
                setStatus(detailStatus, error.message || 'Aktion fehlgeschlagen.', true);
            }
        }

        async function cancelOccurrence(event) {
            resetStatus(detailStatus);
            try {
                const res = await apiPost(endpoints.overrideCancel, { series_id: event.series_id, occurrence_start: event.occurrence_start });
                if (!res.success) throw new Error(res.error || 'Vorkommen konnte nicht abgesagt werden.');
                setStatus(detailStatus, 'Vorkommen wurde abgesagt.', false);
                calendar.refetchEvents();
                setTimeout(() => closeModal(detailModal), 400);
            } catch (error) {
                setStatus(detailStatus, error.message || 'Aktion fehlgeschlagen.', true);
            }
        }

        async function saveOccurrenceEdit(event) {
            resetStatus(formStatus);
            try {
                const payload = collectOccurrencePayload(event);
                const res = await apiPost(endpoints.overrideModify, payload);
                if (!res.success) throw new Error(res.error || 'Speichern fehlgeschlagen.');
                setStatus(formStatus, 'Vorkommen aktualisiert.', false);
                calendar.refetchEvents();
                setTimeout(() => closeModal(formModal), 400);
            } catch (error) {
                setStatus(formStatus, error.message || 'Aktion fehlgeschlagen.', true);
            }
        }

        function handleEventClick(info) {
            const evt = { ...info.event.extendedProps, start: info.event.startStr, end: info.event.endStr };
            populateDetail(evt);
            openModal(detailModal);
        }

        function initWeekdays() {
            weekdayOptions.forEach((opt) => {
                const chip = document.createElement('label');
                chip.className = 'chip';
                const input = document.createElement('input');
                input.type = 'checkbox';
                input.id = `weekday-${opt.key}`;
                input.value = opt.key;
                const span = document.createElement('span');
                span.textContent = opt.label;
                chip.append(input, span);
                seriesWeekdays.appendChild(chip);
            });
        }

        function initTabs() {
            formTabs.forEach((tab) => {
                tab.addEventListener('click', () => setActiveTab(tab.dataset.formTab));
            });
        }

        function initFilters() {
            btnCatAll.addEventListener('click', () => {
                categories.forEach((c) => selectedCategoryIds.add(c.id));
                renderCategories();
                calendar.refetchEvents();
            });
            btnCatNone.addEventListener('click', () => {
                selectedCategoryIds.clear();
                renderCategories();
                calendar.refetchEvents();
            });
            toggleSystemEl.addEventListener('change', () => calendar.refetchEvents());
            searchInputEl.addEventListener('input', debounce(() => calendar.refetchEvents(), 350));
        }

        function debounce(fn, delay) {
            let t;
            return (...args) => {
                clearTimeout(t);
                t = setTimeout(() => fn(...args), delay);
            };
        }

        function initCalendar() {
            const calendarEl = document.getElementById('calendar');
            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'de',
                height: 'auto',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
                },
                buttonText: {
                    today: 'Heute',
                    month: 'Monat',
                    week: 'Woche',
                    day: 'Tag',
                    list: 'Liste',
                },
                events: fetchEvents,
                eventClick: handleEventClick,
                eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
            });
            calendar.render();
        }

        document.querySelectorAll('[data-close-modal]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const target = document.getElementById(btn.dataset.closeModal);
                if (target) closeModal(target);
            });
        });

        btnNewEvent.addEventListener('click', () => openCreateModal('single'));
        btnLogout.addEventListener('click', () => handleLogout());
        btnFormSave.addEventListener('click', () => {
            if (formMode.action === 'edit' && formMode.baseEvent?.is_series && activeTab === 'single') {
                saveOccurrenceEdit(formMode.baseEvent);
            } else {
                handleSave();
            }
        });
        btnDetailEdit.addEventListener('click', () => {
            if (!currentEventData) return;
            if (currentEventData.is_series) {
                fillSeriesForm(currentEventData);
            } else {
                fillEventForm(currentEventData);
            }
        });
        btnDetailSeriesEdit.addEventListener('click', () => {
            if (!currentEventData) return;
            fillSeriesForm(currentEventData);
        });
        btnDetailOccurrenceEdit.addEventListener('click', () => {
            if (!currentEventData) return;
            formMode = { type: 'single', action: 'edit', baseEvent: currentEventData };
            fillEventForm(currentEventData);
            formSubtitle.textContent = 'Nur dieses Vorkommen anpassen.';
        });
        btnDetailOccurrenceCancel.addEventListener('click', () => {
            if (!currentEventData) return;
            if (confirm('Dieses Vorkommen wirklich absagen?')) {
                cancelOccurrence(currentEventData);
            }
        });
        btnDetailDelete.addEventListener('click', () => {
            if (!currentEventData) return;
            if (confirm('Termin löschen?')) deleteEvent(currentEventData);
        });

        initWeekdays();
        initTabs();
        initFilters();
        initCalendar();
        loadCategories();
    </script>
</body>
</html>
