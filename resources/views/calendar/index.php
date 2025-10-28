<section>
    <h2>Kalenderübersicht</h2>
    <article>
        <h3>Neuen Termin anlegen</h3>
        <form method="POST" action="/appointments">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
            <label>Kunde
                <input type="text" name="client_name" required>
            </label>
            <label>Start
                <input type="datetime-local" name="start_at" required>
            </label>
            <label>Ende
                <input type="datetime-local" name="end_at" required>
            </label>
            <label>Status
                <select name="status">
                    <option value="available">Verfügbar</option>
                    <option value="booked">Gebucht</option>
                    <option value="done">Abgeschlossen</option>
                </select>
            </label>
            <label>Notizen
                <textarea name="notes"></textarea>
            </label>
            <button type="submit">Speichern</button>
        </form>
    </article>

    <article>
        <h3>Geplante Termine</h3>
        <?php if (empty($appointments)): ?>
            <p>Keine Termine vorhanden.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Kunde</th>
                        <th>Start</th>
                        <th>Ende</th>
                        <th>Status</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($appointments as $appointment): ?>
                    <?php
                        $startValue = $appointment['start_at'] ?? '';
                        $endValue = $appointment['end_at'] ?? '';
                        $startFormatted = $startValue ? str_replace(' ', 'T', substr($startValue, 0, 16)) : '';
                        $endFormatted = $endValue ? str_replace(' ', 'T', substr($endValue, 0, 16)) : '';
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($appointment['client_name'] ?? 'frei', ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($appointment['start_at'], ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($appointment['end_at'], ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($appointment['status'], ENT_QUOTES) ?></td>
                        <td>
                            <details>
                                <summary>Bearbeiten</summary>
                                <form method="POST" action="/appointments/<?= (int) $appointment['id'] ?>/update">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                                    <label>Kunde
                                        <input type="text" name="client_name" value="<?= htmlspecialchars($appointment['client_name'] ?? '', ENT_QUOTES) ?>" required>
                                    </label>
                                    <label>Start
                                        <input type="datetime-local" name="start_at" value="<?= htmlspecialchars($startFormatted, ENT_QUOTES) ?>" required>
                                    </label>
                                    <label>Ende
                                        <input type="datetime-local" name="end_at" value="<?= htmlspecialchars($endFormatted, ENT_QUOTES) ?>" required>
                                    </label>
                                    <label>Status
                                        <select name="status">
                                            <?php foreach (['available' => 'Verfügbar', 'booked' => 'Gebucht', 'done' => 'Abgeschlossen'] as $value => $label): ?>
                                                <option value="<?= $value ?>" <?= ($appointment['status'] ?? '') === $value ? 'selected' : '' ?>><?= $label ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label>Notizen
                                        <textarea name="notes"><?= htmlspecialchars($appointment['notes'] ?? '', ENT_QUOTES) ?></textarea>
                                    </label>
                                    <button type="submit">Aktualisieren</button>
                                </form>
                            </details>
                            <form method="POST" action="/appointments/<?= (int) $appointment['id'] ?>/delete" onsubmit="return confirm('Termin wirklich löschen?');">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                                <button type="submit" class="secondary">Löschen</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </article>
</section>
