<?php
$config = require __DIR__ . '/../lib/bootstrap.php';

require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Csrf.php';

$currentUser = Auth::requireRole(['editor']);
$csrfToken = Csrf::getToken();
$baseUrl = rtrim($config['base_url'] ?? '', '/');
$appName = htmlspecialchars($config['app_name'] ?? 'Vereinskalender', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JSON Export/Import | <?php echo $appName; ?></title>
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
            max-width: 1100px;
            margin: 0 auto;
            padding: 24px 16px 48px;
        }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 18px;
        }
        h1 { margin: 0; font-size: 1.6rem; }
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
        .muted { color: #475569; }
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.08);
            padding: 16px;
            margin-bottom: 16px;
        }
        .card h2 { margin-top: 0; margin-bottom: 4px; }
        .card p { margin-top: 4px; }
        label { font-weight: 700; display: block; margin: 10px 0 6px; }
        input[type="date"], input[type="datetime-local"], select {
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            font-size: 0.95rem;
        }
        input[type="file"] { margin-top: 6px; }
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
        .btn-ghost { background: transparent; color: #1e88e5; }
        button:hover { transform: translateY(-1px); }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 14px;
        }
        .checkbox-list {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px;
            max-height: 220px;
            overflow: auto;
            background: #f8fafc;
        }
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 4px;
            border-radius: 8px;
        }
        .status {
            margin-top: 12px;
            padding: 12px 14px;
            border-radius: 10px;
            font-weight: 700;
            display: none;
        }
        .status.success { background: #e0f2fe; color: #0f172a; }
        .status.error { background: #fee2e2; color: #991b1b; }
        .report {
            margin-top: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px;
        }
        .report h3 { margin: 0 0 8px; }
        .report-section { margin-bottom: 10px; }
        .report-section ul { margin: 4px 0 0 18px; color: #0f172a; }
        .tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            background: #f1f5f9;
            border-radius: 999px;
            font-size: 0.9rem;
        }
        @media (max-width: 640px) {
            .topbar { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="topbar">
            <div>
                <h1><?php echo $appName; ?></h1>
                <div class="muted">Manueller JSON Export/Import</div>
            </div>
            <div style="display:flex; align-items:center; gap:10px; flex-wrap: wrap;">
                <span class="pill">Angemeldet als <?php echo htmlspecialchars($currentUser['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                <a class="tag" href="<?php echo htmlspecialchars($baseUrl . '/app/calendar.php', ENT_QUOTES, 'UTF-8'); ?>">← Zurück zum Kalender</a>
            </div>
        </div>

        <div class="grid">
            <section class="card">
                <h2>Export</h2>
                <p class="muted">Wähle Zeitraum und Kategorien. Systemtermine können optional einbezogen werden.</p>
                <form id="export-form">
                    <label for="export-from">Von</label>
                    <input type="datetime-local" id="export-from" name="from" required>

                    <label for="export-to">Bis</label>
                    <input type="datetime-local" id="export-to" name="to" required>

                    <label>Kategorien</label>
                    <div id="category-list" class="checkbox-list">
                        <div class="muted">Lade Kategorien...</div>
                    </div>

                    <label style="display:flex; align-items:center; gap:10px; font-weight:600; margin-top:12px;">
                        <input type="checkbox" id="include-system">
                        Systemtermine (Ferien/Feiertage) einbeziehen
                    </label>

                    <div style="display:flex; gap:10px; margin-top:12px; flex-wrap: wrap;">
                        <button type="submit" class="btn-primary">Export starten</button>
                        <button type="button" class="btn-secondary" id="select-all">Alle Kategorien</button>
                        <button type="button" class="btn-ghost" id="select-none">Keine Kategorien</button>
                    </div>
                    <div id="export-status" class="status"></div>
                </form>
            </section>

            <section class="card">
                <h2>Import</h2>
                <p class="muted">Lade die exportierte JSON-Datei hoch. Kategorien werden nach Name upserted, Events nach Quelle/External-ID aktualisiert.</p>
                <form id="import-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <label for="import-file">JSON-Datei</label>
                    <input type="file" id="import-file" name="file" accept="application/json" required>
                    <div style="display:flex; gap:10px; margin-top:12px; flex-wrap: wrap;">
                        <button type="submit" class="btn-primary">Import starten</button>
                        <button type="reset" class="btn-secondary">Zurücksetzen</button>
                    </div>
                    <div id="import-status" class="status"></div>
                </form>
                <div id="report" class="report" style="display:none;"></div>
            </section>
        </div>
    </div>

    <script>
        const baseUrl = <?php echo json_encode($baseUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const csrfToken = <?php echo json_encode($csrfToken, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const endpoints = {
            categories: `${baseUrl}/api/categories/list.php`,
            export: `${baseUrl}/api/json/export.php`,
            import: `${baseUrl}/api/json/import.php`,
        };

        const categoryListEl = document.getElementById('category-list');
        const exportForm = document.getElementById('export-form');
        const exportStatus = document.getElementById('export-status');
        const importForm = document.getElementById('import-form');
        const importStatus = document.getElementById('import-status');
        const reportEl = document.getElementById('report');

        function setStatus(el, message, isError = false) {
            el.textContent = message;
            el.classList.toggle('error', isError);
            el.classList.toggle('success', !isError);
            el.style.display = message ? 'block' : 'none';
        }

        async function loadCategories() {
            categoryListEl.innerHTML = '<div class="muted">Lade Kategorien...</div>';
            try {
                const res = await fetch(endpoints.categories, { credentials: 'same-origin' });
                const json = await res.json();
                if (!json.success) {
                    throw new Error(json.error || 'Kategorien konnten nicht geladen werden.');
                }
                const categories = json.data.categories || [];
                if (categories.length === 0) {
                    categoryListEl.innerHTML = '<div class="muted">Keine Kategorien gefunden.</div>';
                    return;
                }

                categoryListEl.innerHTML = '';
                categories.forEach((cat) => {
                    const id = `cat-${cat.id}`;
                    const wrapper = document.createElement('label');
                    wrapper.className = 'checkbox-item';
                    wrapper.innerHTML = `
                        <input type="checkbox" value="${cat.id}" id="${id}">
                        <span style="display:inline-flex;align-items:center;gap:8px;">
                            <span style="width:14px;height:14px;border-radius:50%;border:1px solid #cbd5e1;background:${cat.color};"></span>
                            ${cat.name}
                        </span>
                    `;
                    categoryListEl.appendChild(wrapper);
                });
            } catch (e) {
                categoryListEl.innerHTML = `<div class="muted">Fehler: ${e.message}</div>`;
            }
        }

        function getSelectedCategoryIds() {
            return Array.from(categoryListEl.querySelectorAll('input[type="checkbox"]:checked'))
                .map((cb) => cb.value)
                .filter(Boolean);
        }

        exportForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            setStatus(exportStatus, 'Export läuft...', false);

            const from = document.getElementById('export-from').value;
            const to = document.getElementById('export-to').value;
            const includeSystem = document.getElementById('include-system').checked ? 1 : 0;
            const categoryIds = getSelectedCategoryIds();

            if (!from || !to) {
                setStatus(exportStatus, 'Bitte Zeitraum angeben.', true);
                return;
            }

            const params = new URLSearchParams();
            params.set('from', from);
            params.set('to', to);
            params.set('include_system', includeSystem.toString());
            if (categoryIds.length > 0) {
                params.set('category_ids', categoryIds.join(','));
            }

            try {
                const res = await fetch(`${endpoints.export}?${params.toString()}`, { credentials: 'same-origin' });
                if (!res.ok) {
                    let message = res.statusText;
                    try {
                        const errJson = await res.json();
                        message = errJson.error || message;
                    } catch (err) {
                        const errText = await res.text();
                        message = errText || message;
                    }
                    setStatus(exportStatus, `Fehler: ${message}`, true);
                    return;
                }
                const blob = await res.blob();
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                const stamp = new Date().toISOString().replace(/[:]/g, '-');
                link.href = url;
                link.download = `events-export-${stamp}.json`;
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(url);
                setStatus(exportStatus, 'Export bereitgestellt.', false);
            } catch (err) {
                setStatus(exportStatus, `Fehler: ${err.message}`, true);
            }
        });

        document.getElementById('select-all').addEventListener('click', () => {
            categoryListEl.querySelectorAll('input[type="checkbox"]').forEach((cb) => { cb.checked = true; });
        });
        document.getElementById('select-none').addEventListener('click', () => {
            categoryListEl.querySelectorAll('input[type="checkbox"]').forEach((cb) => { cb.checked = false; });
        });

        importForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            setStatus(importStatus, 'Import läuft...', false);
            reportEl.style.display = 'none';
            reportEl.innerHTML = '';

            const fileInput = document.getElementById('import-file');
            if (!fileInput.files || fileInput.files.length === 0) {
                setStatus(importStatus, 'Bitte eine Datei auswählen.', true);
                return;
            }

            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('file', fileInput.files[0]);

            try {
                const res = await fetch(endpoints.import, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData,
                });
                const json = await res.json();
                if (!json.success) {
                    setStatus(importStatus, json.error || 'Import fehlgeschlagen.', true);
                    return;
                }
                setStatus(importStatus, 'Import abgeschlossen.', false);
                renderReport(json.data.report || {});
            } catch (err) {
                setStatus(importStatus, `Fehler: ${err.message}`, true);
            }
        });

        function renderReport(report) {
            const created = report.created || [];
            const updated = report.updated || [];
            const skipped = report.skipped || [];
            const errors = report.errors || [];

            const renderList = (items) => {
                if (!items.length) return '<p class="muted">Keine Einträge.</p>';
                return `<ul>${items.map((it) => `<li><strong>${it.type}</strong> ${it.title || it.name || ''} ${it.reason ? '(' + it.reason + ')' : ''}</li>`).join('')}</ul>`;
            };

            reportEl.innerHTML = `
                <h3>Ergebnis</h3>
                <div class="report-section"><strong>Erstellt (${created.length}):</strong> ${renderList(created)}</div>
                <div class="report-section"><strong>Aktualisiert (${updated.length}):</strong> ${renderList(updated)}</div>
                <div class="report-section"><strong>Übersprungen (${skipped.length}):</strong> ${renderList(skipped)}</div>
                <div class="report-section"><strong>Fehler (${errors.length}):</strong> ${renderList(errors)}</div>
            `;
            reportEl.style.display = 'block';
        }

        loadCategories();
    </script>
</body>
</html>
