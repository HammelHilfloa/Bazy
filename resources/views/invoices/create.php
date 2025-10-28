<section>
    <h2>Neue Rechnung erstellen</h2>
    <form method="POST" action="/invoices">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
        <label>Kunden-E-Mail
            <input type="email" name="client_email" required>
        </label>
        <label>Betrag (EUR)
            <input type="number" name="amount" step="0.01" min="0" required>
        </label>
        <label>Fälligkeitsdatum
            <input type="date" name="due_date" required>
        </label>
        <button type="submit">Speichern</button>
    </form>
</section>
