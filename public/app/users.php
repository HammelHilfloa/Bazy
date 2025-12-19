<?php
$config = require __DIR__ . '/../lib/bootstrap.php';

require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Csrf.php';

$currentUser = Auth::requireRole(['admin']);

$csrfToken = Csrf::getToken();
$baseUrl = rtrim($config['base_url'] ?? '', '/');
$appName = htmlspecialchars($config['app_name'] ?? 'Vereinskalender', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benutzerverwaltung | <?php echo $appName; ?></title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: #f7f7f7;
            margin: 0;
            padding: 0;
            color: #1f2933;
        }
        a { color: #1e88e5; text-decoration: none; }
        .page { max-width: 1100px; margin: 0 auto; padding: 24px 16px 48px; }
        .topbar { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
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
        h1 { margin: 0; font-size: 1.6rem; }
        .card {
            background: #fff;
            border-radius: 10px;
            padding: 16px;
            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.1);
            margin-top: 14px;
        }
        .muted { color: #5f6b76; margin: 6px 0 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td {
            padding: 12px 10px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            font-size: 0.95rem;
        }
        th { background: #f1f5f9; font-weight: 600; }
        tr:last-child td { border-bottom: none; }
        button, .btn {
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
        button:hover, .btn:hover { transform: translateY(-1px); }
        .actions { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.85rem;
            color: #0f172a;
            background: #e2e8f0;
        }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-muted { background: #f3f4f6; color: #4b5563; }
        .table-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .status {
            margin-top: 10px;
            padding: 10px 12px;
            border-radius: 8px;
            font-weight: 600;
            display: none;
        }
        .status.error { background: #fee2e2; color: #991b1b; }
        .status.success { background: #e0f2fe; color: #0369a1; }
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
            max-width: 520px;
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
        form label { display: block; font-weight: 600; margin: 12px 0 6px; }
        form input[type="text"],
        form input[type="password"],
        form select {
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            font-size: 1rem;
        }
        form input[type="checkbox"] { transform: scale(1.1); }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
        .hint { font-size: 0.9rem; color: #5f6b76; }
        .form-footer { margin-top: 16px; display: flex; gap: 10px; flex-wrap: wrap; }
        .row-muted { opacity: 0.65; }
        @media (max-width: 640px) {
            .topbar { align-items: flex-start; flex-direction: column; }
            .table-actions { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="topbar">
            <a href="<?php echo htmlspecialchars($baseUrl . '/app/calendar.php', ENT_QUOTES, 'UTF-8'); ?>" class="pill">← Zurück</a>
            <div>
                <h1>Benutzerverwaltung</h1>
                <p class="muted">Nur für Admins. Benutzer anlegen, bearbeiten oder deaktivieren.</p>
            </div>
        </div>

        <div class="card actions">
            <div class="pill">App: <?php echo $appName; ?></div>
            <button id="btn-new-user" class="btn btn-primary">Neuen Benutzer anlegen</button>
        </div>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th style="width:30%;">Benutzername</th>
                        <th style="width:15%;">Rolle</th>
                        <th style="width:15%;">Status</th>
                        <th style="width:20%;">Letzter Login</th>
                        <th style="width:20%;">Aktionen</th>
                    </tr>
                </thead>
                <tbody id="user-rows">
                    <tr><td colspan="5">Lade Benutzer...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal-backdrop" id="create-modal" hidden>
        <div class="modal">
            <div class="modal-header">
                <h2>Benutzer anlegen</h2>
                <button class="icon-button" type="button" data-close-modal="create-modal" aria-label="Schließen">✕</button>
            </div>
            <form id="create-form" novalidate>
                <label for="create-username">Benutzername *</label>
                <input type="text" id="create-username" name="username" minlength="3" maxlength="64" autocomplete="username" required>

                <div class="form-grid">
                    <div>
                        <label for="create-role">Rolle *</label>
                        <select id="create-role" name="role" required>
                            <option value="admin">Admin</option>
                            <option value="editor">Editor</option>
                            <option value="viewer">Viewer</option>
                        </select>
                    </div>
                    <div>
                        <label for="create-password">Passwort *</label>
                        <input type="password" id="create-password" name="password" autocomplete="new-password" required>
                        <p class="hint">Mindestens 8 Zeichen.</p>
                    </div>
                </div>
                <div class="form-footer">
                    <button type="submit" class="btn btn-primary">Anlegen</button>
                    <button type="button" class="btn btn-secondary" data-close-modal="create-modal">Abbrechen</button>
                </div>
                <div id="create-status" class="status"></div>
            </form>
        </div>
    </div>

    <div class="modal-backdrop" id="edit-modal" hidden>
        <div class="modal">
            <div class="modal-header">
                <h2>Benutzer bearbeiten</h2>
                <button class="icon-button" type="button" data-close-modal="edit-modal" aria-label="Schließen">✕</button>
            </div>
            <form id="edit-form" novalidate>
                <input type="hidden" id="edit-id" name="id" value="">
                <label for="edit-username">Benutzername</label>
                <input type="text" id="edit-username" name="username" disabled>

                <div class="form-grid">
                    <div>
                        <label for="edit-role">Rolle *</label>
                        <select id="edit-role" name="role" required>
                            <option value="admin">Admin</option>
                            <option value="editor">Editor</option>
                            <option value="viewer">Viewer</option>
                        </select>
                    </div>
                    <div>
                        <label for="edit-password">Neues Passwort (optional)</label>
                        <input type="password" id="edit-password" name="password" autocomplete="new-password">
                        <p class="hint">Nur ausfüllen, wenn das Passwort geändert werden soll.</p>
                    </div>
                </div>
                <label>
                    <input type="checkbox" id="edit-active" name="is_active">
                    Benutzer ist aktiv
                </label>
                <div class="form-footer">
                    <button type="submit" class="btn btn-primary">Speichern</button>
                    <button type="button" class="btn btn-secondary" data-close-modal="edit-modal">Abbrechen</button>
                </div>
                <div id="edit-status" class="status"></div>
            </form>
        </div>
    </div>

    <script>
        const csrfToken = <?php echo json_encode($csrfToken, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const baseUrl = <?php echo json_encode($baseUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const currentUserId = <?php echo json_encode($currentUser['id'] ?? null, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const endpoints = {
            list: `${baseUrl}/api/users/list.php`,
            create: `${baseUrl}/api/users/create.php`,
            update: `${baseUrl}/api/users/update.php`,
            delete: `${baseUrl}/api/users/delete.php`,
        };

        const rowsEl = document.getElementById('user-rows');
        const createModal = document.getElementById('create-modal');
        const editModal = document.getElementById('edit-modal');

        const createForm = document.getElementById('create-form');
        const createStatus = document.getElementById('create-status');
        const inputCreateUsername = document.getElementById('create-username');
        const inputCreateRole = document.getElementById('create-role');
        const inputCreatePassword = document.getElementById('create-password');

        const editForm = document.getElementById('edit-form');
        const editStatus = document.getElementById('edit-status');
        const inputEditId = document.getElementById('edit-id');
        const inputEditUsername = document.getElementById('edit-username');
        const inputEditRole = document.getElementById('edit-role');
        const inputEditPassword = document.getElementById('edit-password');
        const inputEditActive = document.getElementById('edit-active');

        document.getElementById('btn-new-user').addEventListener('click', () => openModal(createModal));

        document.querySelectorAll('[data-close-modal]').forEach((btn) => {
            btn.addEventListener('click', (event) => {
                const target = event.currentTarget.getAttribute('data-close-modal');
                const modal = document.getElementById(target);
                closeModal(modal);
            });
        });

        createForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearStatus(createStatus);

            const username = inputCreateUsername.value.trim();
            const role = inputCreateRole.value;
            const password = inputCreatePassword.value;

            if (!username || username.length < 3) {
                showStatus(createStatus, 'Benutzername muss mindestens 3 Zeichen lang sein.', true);
                inputCreateUsername.focus();
                return;
            }

            if (!password || password.length < 8) {
                showStatus(createStatus, 'Passwort muss mindestens 8 Zeichen lang sein.', true);
                inputCreatePassword.focus();
                return;
            }

            try {
                await request(endpoints.create, {
                    username,
                    role,
                    password,
                });
                showStatus(createStatus, 'Benutzer wurde angelegt.', false);
                createForm.reset();
                closeModal(createModal);
                await loadUsers();
            } catch (error) {
                showStatus(createStatus, error.message || 'Benutzer konnte nicht angelegt werden.', true);
            }
        });

        editForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearStatus(editStatus);

            const id = parseInt(inputEditId.value, 10);
            const role = inputEditRole.value;
            const isActive = inputEditActive.checked ? 1 : 0;
            const password = inputEditPassword.value;

            if (!Number.isInteger(id) || id <= 0) {
                showStatus(editStatus, 'Ungültige Benutzer-ID.', true);
                return;
            }

            if (password && password.length < 8) {
                showStatus(editStatus, 'Neues Passwort muss mindestens 8 Zeichen lang sein.', true);
                inputEditPassword.focus();
                return;
            }

            try {
                await request(endpoints.update, {
                    id,
                    role,
                    is_active: isActive,
                    password,
                });
                showStatus(editStatus, 'Änderungen gespeichert.', false);
                editForm.reset();
                closeModal(editModal);
                await loadUsers();
            } catch (error) {
                showStatus(editStatus, error.message || 'Benutzer konnte nicht aktualisiert werden.', true);
            }
        });

        async function request(url, payload) {
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

            let data;
            try {
                data = await response.json();
            } catch (_) {
                throw new Error('Server-Antwort konnte nicht gelesen werden.');
            }

            if (!response.ok || !data.success) {
                throw new Error((data && data.error) || 'Anfrage fehlgeschlagen.');
            }

            return data;
        }

        async function loadUsers() {
            rowsEl.innerHTML = '<tr><td colspan="5">Lade Benutzer...</td></tr>';
            try {
                const response = await fetch(endpoints.list, { credentials: 'same-origin' });
                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.error || 'Fehler beim Laden.');
                }
                renderRows(data.data.users || []);
            } catch (error) {
                rowsEl.innerHTML = `<tr><td colspan="5" style="color:#b91c1c;">${error.message || 'Fehler beim Laden der Benutzer.'}</td></tr>`;
            }
        }

        function renderRows(users) {
            if (!Array.isArray(users) || users.length === 0) {
                rowsEl.innerHTML = '<tr><td colspan="5">Keine Benutzer gefunden.</td></tr>';
                return;
            }

            rowsEl.innerHTML = '';
            users.forEach((user) => {
                const tr = document.createElement('tr');
                if (user.is_active !== 1) {
                    tr.classList.add('row-muted');
                }

                const tdName = document.createElement('td');
                tdName.textContent = user.username;

                const tdRole = document.createElement('td');
                tdRole.textContent = roleLabel(user.role);

                const tdStatus = document.createElement('td');
                const badge = document.createElement('span');
                badge.className = `badge ${user.is_active === 1 ? 'badge-success' : 'badge-muted'}`;
                badge.textContent = user.is_active === 1 ? 'Aktiv' : 'Inaktiv';
                tdStatus.appendChild(badge);

                const tdLogin = document.createElement('td');
                tdLogin.textContent = formatDate(user.last_login_at);

                const tdActions = document.createElement('td');
                const actions = document.createElement('div');
                actions.className = 'table-actions';

                const editBtn = document.createElement('button');
                editBtn.type = 'button';
                editBtn.className = 'btn btn-secondary';
                editBtn.textContent = 'Bearbeiten';
                editBtn.addEventListener('click', () => openEdit(user));

                const toggleBtn = document.createElement('button');
                toggleBtn.type = 'button';
                toggleBtn.className = user.is_active === 1 ? 'btn btn-danger' : 'btn btn-primary';
                toggleBtn.textContent = user.is_active === 1 ? 'Deaktivieren' : 'Aktivieren';
                toggleBtn.disabled = currentUserId === user.id;
                toggleBtn.title = toggleBtn.disabled ? 'Eigenes Konto kann nicht geändert werden.' : '';
                toggleBtn.addEventListener('click', () => {
                    if (toggleBtn.disabled) {
                        return;
                    }
                    if (user.is_active === 1) {
                        confirmDeactivate(user.id);
                    } else {
                        changeActiveState(user, 1);
                    }
                });

                actions.appendChild(editBtn);
                actions.appendChild(toggleBtn);
                tdActions.appendChild(actions);

                tr.append(tdName, tdRole, tdStatus, tdLogin, tdActions);
                rowsEl.appendChild(tr);
            });
        }

        function roleLabel(role) {
            switch (role) {
                case 'admin': return 'Admin';
                case 'editor': return 'Editor';
                case 'viewer': return 'Viewer';
                default: return role || 'Unbekannt';
            }
        }

        function formatDate(value) {
            if (!value) return '—';
            const normalized = value.replace(' ', 'T');
            const date = new Date(normalized);
            if (Number.isNaN(date.getTime())) {
                return value;
            }
            return date.toLocaleString('de-DE');
        }

        function openModal(modal) {
            modal.hidden = false;
            const focusable = modal.querySelector('input, select');
            if (focusable) {
                focusable.focus();
            }
        }

        function closeModal(modal) {
            modal.hidden = true;
            const form = modal.querySelector('form');
            if (form) {
                form.reset();
            }
            clearStatus(modal.querySelector('.status'));
        }

        function showStatus(element, message, isError) {
            if (!element) return;
            element.textContent = message;
            element.className = `status ${isError ? 'error' : 'success'}`;
            element.style.display = 'block';
        }

        function clearStatus(element) {
            if (!element) return;
            element.textContent = '';
            element.className = 'status';
            element.style.display = 'none';
        }

        function openEdit(user) {
            clearStatus(editStatus);
            inputEditId.value = user.id;
            inputEditUsername.value = user.username;
            inputEditRole.value = user.role;
            inputEditActive.checked = user.is_active === 1;
            inputEditPassword.value = '';

            inputEditRole.disabled = currentUserId === user.id;
            inputEditActive.disabled = currentUserId === user.id;

            openModal(editModal);
        }

        async function confirmDeactivate(id) {
            if (!id || !confirm('Benutzer wirklich deaktivieren?')) {
                return;
            }
            try {
                await request(endpoints.delete, { id });
                await loadUsers();
            } catch (error) {
                alert(error.message || 'Benutzer konnte nicht deaktiviert werden.');
            }
        }

        async function changeActiveState(user, isActive) {
            try {
                await request(endpoints.update, {
                    id: user.id,
                    role: user.role,
                    is_active: isActive,
                });
                await loadUsers();
            } catch (error) {
                alert(error.message || 'Status konnte nicht geändert werden.');
            }
        }

        loadUsers();
    </script>
</body>
</html>
