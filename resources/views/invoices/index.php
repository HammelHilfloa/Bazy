<section>
    <header>
        <h2>Rechnungen</h2>
        <a role="button" href="/invoices/create">Neue Rechnung</a>
    </header>
    <?php if (empty($invoices)): ?>
        <p>Keine Rechnungen vorhanden.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Kunde</th>
                    <th>Betrag</th>
                    <th>Fällig am</th>
                    <th>Status</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $invoice): ?>
                    <tr>
                        <td><?= (int) $invoice['id'] ?></td>
                        <td><?= htmlspecialchars($invoice['client_email'], ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($invoice['amount'], ENT_QUOTES) ?> €</td>
                        <td><?= htmlspecialchars($invoice['due_date'], ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($invoice['status'], ENT_QUOTES) ?></td>
                        <td>
                            <?php if ($invoice['status'] !== 'sent'): ?>
                                <form method="POST" action="/invoices/<?= (int) $invoice['id'] ?>/send">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                                    <button type="submit">PDF erzeugen &amp; senden</button>
                                </form>
                            <?php else: ?>
                                <span>Versendet am <?= htmlspecialchars($invoice['sent_at'] ?? '-', ENT_QUOTES) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
