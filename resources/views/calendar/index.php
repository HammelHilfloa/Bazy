<?php
    $statusLabels = [
        'available' => 'Verfügbar',
        'booked' => 'Gebucht',
        'done' => 'Abgeschlossen',
    ];

    $statusCounts = array_fill_keys(array_keys($statusLabels), 0);
    foreach ($appointments as $appointmentItem) {
        $status = $appointmentItem['status'] ?? 'available';
        if (!array_key_exists($status, $statusCounts)) {
            $statusCounts[$status] = 0;
        }
        $statusCounts[$status]++;
    }

    $sortedAppointments = $appointments;
    usort($sortedAppointments, static function (array $a, array $b): int {
        return strcmp($a['start_at'] ?? '', $b['start_at'] ?? '');
    });

    $upcomingAppointments = array_slice($sortedAppointments, 0, 4);

    $formatDateTime = static function (?string $value): string {
        if (empty($value)) {
            return '-';
        }

        try {
            $date = new DateTime($value);
            return $date->format('d.m.Y H:i');
        } catch (Exception $exception) {
            return $value;
        }
    };

    $formatDateInput = static function (?string $value): string {
        if (empty($value)) {
            return '';
        }

        $normalized = str_replace(' ', 'T', substr($value, 0, 16));
        return $normalized;
    };
?>

<section class="dashboard" aria-label="Einsatzsteuerung">
    <header class="dashboard__hero">
        <p class="eyebrow">Operationszentrale</p>
        <h1>Kalender &amp; Einsatzplanung</h1>
        <p>
            Lore ipsum dolor sit amet, consectetur adipiscing elit. Sed imperdiet, nunc vel facilisis eleifend, ante
            risus porttitor mi, in aliquet magna magna id orci. Praesent vitae sodales est, non tempor lorem.
        </p>
        <div class="hero-actions">
            <a class="contrast" href="#appointment-create">Jetzt Termin koordinieren</a>
            <a class="ghost" href="#operations-timeline">Nächste Schritte einsehen</a>
        </div>
    </header>

    <div class="summary-grid" role="list">
        <article class="summary-card" role="listitem">
            <span>Gesamteinsätze</span>
            <strong><?= (int) count($appointments) ?></strong>
            <div class="status-pill">Statusübersicht aktualisiert</div>
        </article>
        <?php foreach ($statusLabels as $statusKey => $statusLabel): ?>
            <article class="summary-card" role="listitem">
                <span><?= htmlspecialchars($statusLabel, ENT_QUOTES) ?></span>
                <strong><?= (int) ($statusCounts[$statusKey] ?? 0) ?></strong>
                <div class="status-pill">Letzte Prüfung abgeschlossen</div>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="grid-layout">
        <article id="appointment-create" class="panel" aria-labelledby="create-heading">
            <header>
                <h2 id="create-heading">Neuen Einsatztermin planen</h2>
                <p>Alle Eingaben werden unmittelbar im Echtbetrieb synchronisiert.</p>
            </header>
            <form method="POST" action="/appointments">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                <label for="client_name">Kunde / Zielperson</label>
                <input id="client_name" type="text" name="client_name" required placeholder="Name oder Rufzeichen">

                <div class="grid" style="--pico-grid-gap: 1rem;">
                    <div>
                        <label for="start_at">Start</label>
                        <input id="start_at" type="datetime-local" name="start_at" required>
                    </div>
                    <div>
                        <label for="end_at">Ende</label>
                        <input id="end_at" type="datetime-local" name="end_at" required>
                    </div>
                </div>

                <label for="status">Status</label>
                <select id="status" name="status">
                    <?php foreach ($statusLabels as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="notes">Lagebild / Notizen</label>
                <textarea id="notes" name="notes" placeholder="Kurze Lageeinschätzung und Ziele"></textarea>

                <div class="button-row">
                    <button type="submit" class="contrast">Speichern &amp; aktivieren</button>
                </div>
            </form>
        </article>

        <article id="operations-timeline" class="panel" aria-labelledby="timeline-heading">
            <header>
                <h2 id="timeline-heading">Nächste Operationen</h2>
                <p>Prüfen Sie die nächsten Schritte bevor der erste Angriff startet.</p>
            </header>
            <?php if (empty($upcomingAppointments)): ?>
                <div class="empty-state">Noch keine Einsätze geplant. Legen Sie oben einen neuen Termin an.</div>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($upcomingAppointments as $item): ?>
                        <div class="timeline-item">
                            <time datetime="<?= htmlspecialchars($item['start_at'] ?? '', ENT_QUOTES) ?>">
                                Start: <?= htmlspecialchars($formatDateTime($item['start_at'] ?? ''), ENT_QUOTES) ?>
                            </time>
                            <strong><?= htmlspecialchars($item['client_name'] ?? 'frei', ENT_QUOTES) ?></strong>
                            <span class="status-chip status-<?= htmlspecialchars($item['status'] ?? 'available', ENT_QUOTES) ?>">
                                <?= htmlspecialchars($statusLabels[$item['status'] ?? 'available'] ?? ucfirst($item['status'] ?? ''), ENT_QUOTES) ?>
                            </span>
                            <?php if (!empty($item['notes'])): ?>
                                <p><?= nl2br(htmlspecialchars($item['notes'], ENT_QUOTES)) ?></p>
                            <?php else: ?>
                                <p>Bereit zur Ausführung. Lore ipsum dolor sit amet, kurze Einsatznotiz folgt.</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    </div>

    <article class="panel" aria-labelledby="overview-heading">
        <header>
            <h2 id="overview-heading">Gesamtüberblick</h2>
            <p>Hier verwalten Sie sämtliche Einsätze, aktualisieren Details und bereiten Tests im Realbetrieb vor.</p>
        </header>

        <?php if (empty($appointments)): ?>
            <div class="empty-state">Keine Termine vorhanden. Starten Sie mit dem ersten Angriff, indem Sie einen neuen Eintrag anlegen.</div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="plan-table">
                    <thead>
                        <tr>
                            <th scope="col">Kunde</th>
                            <th scope="col">Start</th>
                            <th scope="col">Ende</th>
                            <th scope="col">Status</th>
                            <th scope="col">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($appointments as $appointment): ?>
                        <?php
                            $startValue = $appointment['start_at'] ?? '';
                            $endValue = $appointment['end_at'] ?? '';
                            $startFormatted = $formatDateInput($startValue);
                            $endFormatted = $formatDateInput($endValue);
                            $statusKey = $appointment['status'] ?? 'available';
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($appointment['client_name'] ?? 'frei', ENT_QUOTES) ?></td>
                            <td><?= htmlspecialchars($formatDateTime($startValue), ENT_QUOTES) ?></td>
                            <td><?= htmlspecialchars($formatDateTime($endValue), ENT_QUOTES) ?></td>
                            <td>
                                <span class="status-chip status-<?= htmlspecialchars($statusKey, ENT_QUOTES) ?>">
                                    <?= htmlspecialchars($statusLabels[$statusKey] ?? ucfirst($statusKey), ENT_QUOTES) ?>
                                </span>
                            </td>
                            <td>
                                <div class="appointment-actions">
                                    <details>
                                        <summary>Bearbeiten</summary>
                                        <form method="POST" action="/appointments/<?= (int) $appointment['id'] ?>/update">
                                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                                            <label for="client_name_<?= (int) $appointment['id'] ?>">Kunde</label>
                                            <input id="client_name_<?= (int) $appointment['id'] ?>" type="text" name="client_name" value="<?= htmlspecialchars($appointment['client_name'] ?? '', ENT_QUOTES) ?>" required>

                                            <label for="start_at_<?= (int) $appointment['id'] ?>">Start</label>
                                            <input id="start_at_<?= (int) $appointment['id'] ?>" type="datetime-local" name="start_at" value="<?= htmlspecialchars($startFormatted, ENT_QUOTES) ?>" required>

                                            <label for="end_at_<?= (int) $appointment['id'] ?>">Ende</label>
                                            <input id="end_at_<?= (int) $appointment['id'] ?>" type="datetime-local" name="end_at" value="<?= htmlspecialchars($endFormatted, ENT_QUOTES) ?>" required>

                                            <label for="status_<?= (int) $appointment['id'] ?>">Status</label>
                                            <select id="status_<?= (int) $appointment['id'] ?>" name="status">
                                                <?php foreach ($statusLabels as $value => $label): ?>
                                                    <option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>" <?= ($appointment['status'] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
                                                <?php endforeach; ?>
                                            </select>

                                            <label for="notes_<?= (int) $appointment['id'] ?>">Notizen</label>
                                            <textarea id="notes_<?= (int) $appointment['id'] ?>" name="notes"><?= htmlspecialchars($appointment['notes'] ?? '', ENT_QUOTES) ?></textarea>

                                            <div class="button-row">
                                                <button type="submit">Aktualisieren</button>
                                            </div>
                                        </form>
                                    </details>
                                    <form method="POST" action="/appointments/<?= (int) $appointment['id'] ?>/delete" onsubmit="return confirm('Termin wirklich löschen?');">
                                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                                        <button type="submit" class="secondary">Löschen</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>
</section>
