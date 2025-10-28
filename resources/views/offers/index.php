<section>
    <header>
        <h2>Angebote</h2>
        <a role="button" href="/offers/create">Neues Angebot</a>
    </header>
    <?php if (empty($offers)): ?>
        <p>Keine Angebote vorhanden.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Kunde</th>
                    <th>Betreff</th>
                    <th>Status</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($offers as $offer): ?>
                    <tr>
                        <td><?= (int) $offer['id'] ?></td>
                        <td><?= htmlspecialchars($offer['client_email'], ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($offer['subject'], ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($offer['status'], ENT_QUOTES) ?></td>
                        <td>
                            <?php if ($offer['status'] !== 'sent'): ?>
                                <form method="POST" action="/offers/<?= (int) $offer['id'] ?>/send">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                                    <button type="submit">Versenden</button>
                                </form>
                            <?php else: ?>
                                <span>Versendet am <?= htmlspecialchars($offer['sent_at'] ?? '-', ENT_QUOTES) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
