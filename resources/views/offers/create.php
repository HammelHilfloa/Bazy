<section>
    <h2>Neues Angebot erstellen</h2>
    <form method="POST" action="/offers">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
        <label>Kunden-E-Mail
            <input type="email" name="client_email" required>
        </label>
        <label>Betreff
            <input type="text" name="subject" required>
        </label>
        <label>Angebotsinhalt
            <textarea name="body" rows="5" required></textarea>
        </label>
        <button type="submit">Speichern</button>
    </form>
</section>
