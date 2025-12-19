<?php
$config = require __DIR__ . '/../lib/bootstrap.php';

require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Csrf.php';

Auth::requireRole(['editor']);

$csrfToken = Csrf::getToken();
$baseUrl = rtrim($config['base_url'] ?? '', '/');
$appName = htmlspecialchars($config['app_name'] ?? 'Vereinskalender', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategorien verwalten | <?php echo $appName; ?></title>
    <style>
        :root {
            color-scheme: light;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            background: #f7f7f7;
            margin: 0;
            padding: 0;
            color: #1f2933;
        }
        a {
            color: #1e88e5;
            text-decoration: none;
        }
        .page {
            max-width: 1100px;
            margin: 0 auto;
            padding: 24px 16px 48px;
        }
        .topbar {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            background: #e3f2fd;
            color: #1565c0;
            border-radius: 999px;
            font-size: 0.9rem;
        }
        h1 {
            margin: 0;
            font-size: 1.6rem;
        }
        .card {
            background: #fff;
            border-radius: 10px;
            padding: 16px;
            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.1);
            margin-top: 14px;
        }
        .muted {
            color: #5f6b76;
            margin: 6px 0 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px 10px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            font-size: 0.95rem;
        }
        th {
            background: #f1f5f9;
            font-weight: 600;
        }
        tr:last-child td {
            border-bottom: none;
        }
        button, .btn {
            cursor: pointer;
            border: none;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.95rem;
            font-weight: 600;
            transition: background 0.15s ease, transform 0.15s ease;
        }
        .btn-primary {
            background: #1e88e5;
            color: #fff;
        }
        .btn-secondary {
            background: #e2e8f0;
            color: #1f2933;
        }
        .btn-danger {
            background: #ef4444;
            color: #fff;
        }
        button:hover, .btn:hover {
            transform: translateY(-1px);
        }
        .actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        form label {
            display: block;
            font-weight: 600;
            margin: 12px 0 6px;
        }
        form input[type="text"],
        form input[type="number"] {
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            font-size: 1rem;
        }
        form input[type="checkbox"] {
            transform: scale(1.1);
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
        }
        .color-preview {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .color-dot {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 1px solid #cbd5e1;
            display: inline-block;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.85rem;
            color: #0f172a;
            background: #e2e8f0;
        }
        .badge-success {
            background: #dcfce7;
            color: #166534;
        }
        .badge-muted {
            background: #f3f4f6;
            color: #4b5563;
        }
        .table-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .hint {
            font-size: 0.9rem;
            color: #5f6b76;
        }
        .form-footer {
            margin-top: 16px;
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
        .status.error {
            background: #fee2e2;
            color: #991b1b;
        }
        .status.success {
            background: #e0f2fe;
            color: #0369a1;
        }
        @media (max-width: 640px) {
            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }
            .table-actions {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="topbar">
            <a href="<?php echo htmlspecialchars($baseUrl . '/app/calendar.php', ENT_QUOTES, 'UTF-8'); ?>" class="pill">← Zurück</a>
            <div>
                <h1>Kategorien verwalten</h1>
                <p class="muted">Nur für Admins/Editoren. Name ist erforderlich, Farbwert optional (#RRGGBB).</p>
            </div>
        </div>

        <div class="card actions">
            <div>
                <div class="pill">App: <?php echo $appName; ?></div>
            </div>
            <button id="btn-new" class="btn btn-primary">Neu</button>
        </div>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th style="width:30%;">Name</th>
                        <th style="width:20%;">Farbe</th>
                        <th style="width:15%;">Sortierung</th>
                        <th style="width:15%;">Status</th>
                        <th style="width:20%;">Aktionen</th>
                    </tr>
                </thead>
                <tbody id="category-rows">
                    <tr><td colspan="5">Lade Kategorien...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="card" id="form-card" hidden>
            <h2 id="form-title">Kategorie anlegen</h2>
            <form id="category-form" novalidate>
                <input type="hidden" name="id" id="category-id" value="">
                <div class="form-grid">
                    <div>
                        <label for="name">Name *</label>
                        <input type="text" id="name" name="name" required placeholder="z. B. Training" autocomplete="off">
                    </div>
                    <div>
                        <label for="color">Farbe (optional, #RRGGBB)</label>
                        <input type="text" id="color" name="color" placeholder="#1E88E5" autocomplete="off">
                        <p class="hint">Leerlassen für Standardfarbe. Format: #RRGGBB.</p>
                    </div>
                    <div>
                        <label for="sort_order">Sortierung</label>
                        <input type="number" id="sort_order" name="sort_order" value="0" step="1">
                    </div>
                    <div>
                        <label for="is_active">Aktiv</label>
                        <div>
                            <input type="checkbox" id="is_active" name="is_active" checked>
                            <span class="hint">Deaktivierte Kategorien bleiben erhalten.</span>
                        </div>
                    </div>
                </div>
                <div class="form-footer">
                    <button type="submit" class="btn btn-primary">Speichern</button>
                    <button type="button" class="btn btn-secondary" id="btn-cancel">Abbrechen</button>
                </div>
                <div id="form-status" class="status"></div>
            </form>
        </div>
    </div>
    <script>
        const csrfToken = <?php echo json_encode($csrfToken, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const baseUrl = <?php echo json_encode($baseUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const endpoints = {
            list: `${baseUrl}/api/categories/list.php`,
            create: `${baseUrl}/api/categories/create.php`,
            update: `${baseUrl}/api/categories/update.php`,
            delete: `${baseUrl}/api/categories/delete.php`,
        };

        const rowsEl = document.getElementById('category-rows');
        const formCard = document.getElementById('form-card');
        const formEl = document.getElementById('category-form');
        const formTitle = document.getElementById('form-title');
        const statusEl = document.getElementById('form-status');

        const inputId = document.getElementById('category-id');
        const inputName = document.getElementById('name');
        const inputColor = document.getElementById('color');
        const inputSort = document.getElementById('sort_order');
        const inputActive = document.getElementById('is_active');

        document.getElementById('btn-new').addEventListener('click', () => openForm());
        document.getElementById('btn-cancel').addEventListener('click', () => {
            formEl.reset();
            inputId.value = '';
            formCard.hidden = true;
            clearStatus();
        });

        formEl.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearStatus();

            const payload = collectPayload();
            if (!payload) {
                return;
            }

            const isEdit = Boolean(payload.id);
            const url = isEdit ? endpoints.update : endpoints.create;

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': csrfToken,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload),
                });

                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.error || 'Unbekannter Fehler.');
                }

                showStatus(isEdit ? 'Kategorie aktualisiert.' : 'Kategorie erstellt.', false);
                formEl.reset();
                inputId.value = '';
                formCard.hidden = true;
                await loadCategories();
            } catch (error) {
                showStatus(error.message || 'Speichern fehlgeschlagen.', true);
            }
        });

        function collectPayload() {
            const name = inputName.value.trim();
            const color = inputColor.value.trim();
            const sortOrder = parseInt(inputSort.value, 10);
            const isActive = inputActive.checked;

            if (!name) {
                showStatus('Name ist erforderlich.', true);
                inputName.focus();
                return null;
            }

            if (color && !/^#[0-9a-fA-F]{6}$/.test(color)) {
                showStatus('Farbwert muss im Format #RRGGBB sein.', true);
                inputColor.focus();
                return null;
            }

            const payload = {
                name,
                color,
                sort_order: Number.isFinite(sortOrder) ? sortOrder : 0,
                is_active: isActive ? 1 : 0,
            };

            if (inputId.value) {
                payload.id = parseInt(inputId.value, 10);
            }

            return payload;
        }

        function openForm(category = null) {
            clearStatus();
            if (category) {
                formTitle.textContent = 'Kategorie bearbeiten';
                inputId.value = category.id;
                inputName.value = category.name;
                inputColor.value = category.color || '';
                inputSort.value = category.sort_order ?? 0;
                inputActive.checked = category.is_active === 1;
            } else {
                formTitle.textContent = 'Kategorie anlegen';
                formEl.reset();
                inputId.value = '';
                inputSort.value = 0;
                inputActive.checked = true;
            }

            formCard.hidden = false;
            inputName.focus();
        }

        function clearStatus() {
            statusEl.textContent = '';
            statusEl.className = 'status';
            statusEl.style.display = 'none';
        }

        function showStatus(message, isError) {
            statusEl.textContent = message;
            statusEl.className = `status ${isError ? 'error' : 'success'}`;
            statusEl.style.display = 'block';
        }

        async function loadCategories() {
            rowsEl.innerHTML = '<tr><td colspan="5">Lade Kategorien...</td></tr>';
            try {
                const response = await fetch(endpoints.list, { credentials: 'same-origin' });
                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.error || 'Fehler beim Laden.');
                }
                renderRows(data.data.categories || []);
            } catch (error) {
                rowsEl.innerHTML = `<tr><td colspan="5" style="color:#b91c1c;">${error.message || 'Fehler beim Laden der Kategorien.'}</td></tr>`;
            }
        }

        function renderRows(categories) {
            if (!Array.isArray(categories) || categories.length === 0) {
                rowsEl.innerHTML = '<tr><td colspan="5">Keine Kategorien vorhanden.</td></tr>';
                return;
            }

            rowsEl.innerHTML = '';
            categories.forEach((cat) => {
                const tr = document.createElement('tr');

                const tdName = document.createElement('td');
                tdName.textContent = cat.name;

                const tdColor = document.createElement('td');
                const colorWrapper = document.createElement('div');
                colorWrapper.className = 'color-preview';
                const swatch = document.createElement('span');
                swatch.className = 'color-dot';
                swatch.style.background = cat.color || '#9E9E9E';
                const colorText = document.createElement('span');
                colorText.textContent = cat.color || 'Standard';
                colorWrapper.appendChild(swatch);
                colorWrapper.appendChild(colorText);
                tdColor.appendChild(colorWrapper);

                const tdSort = document.createElement('td');
                tdSort.textContent = cat.sort_order ?? 0;

                const tdStatus = document.createElement('td');
                const badge = document.createElement('span');
                badge.className = `badge ${cat.is_active === 1 ? 'badge-success' : 'badge-muted'}`;
                badge.textContent = cat.is_active === 1 ? 'Aktiv' : 'Inaktiv';
                tdStatus.appendChild(badge);

                const tdActions = document.createElement('td');
                const actions = document.createElement('div');
                actions.className = 'table-actions';

                const editBtn = document.createElement('button');
                editBtn.type = 'button';
                editBtn.className = 'btn btn-secondary';
                editBtn.textContent = 'Bearbeiten';
                editBtn.addEventListener('click', () => openForm(cat));

                const deleteBtn = document.createElement('button');
                deleteBtn.type = 'button';
                deleteBtn.className = 'btn btn-danger';
                deleteBtn.textContent = 'Deaktivieren';
                deleteBtn.addEventListener('click', () => deactivateCategory(cat.id));

                actions.appendChild(editBtn);
                actions.appendChild(deleteBtn);
                tdActions.appendChild(actions);

                tr.append(tdName, tdColor, tdSort, tdStatus, tdActions);
                rowsEl.appendChild(tr);
            });
        }

        async function deactivateCategory(id) {
            if (!id || !confirm('Kategorie wirklich deaktivieren?')) {
                return;
            }

            try {
                const response = await fetch(endpoints.delete, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': csrfToken,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ id }),
                });
                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.error || 'Deaktivierung fehlgeschlagen.');
                }
                await loadCategories();
            } catch (error) {
                alert(error.message || 'Die Kategorie konnte nicht deaktiviert werden.');
            }
        }

        loadCategories();
    </script>
</body>
</html>
