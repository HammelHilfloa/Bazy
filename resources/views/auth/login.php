<section>
    <h2>Anmeldung</h2>
    <?php if (!empty($errors)): ?>
        <article class="secondary">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error, ENT_QUOTES) ?></li>
                <?php endforeach; ?>
            </ul>
        </article>
    <?php endif; ?>
    <form method="POST" action="/login">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
        <label>E-Mail
            <input type="email" name="email" required>
        </label>
        <label>Passwort
            <input type="password" name="password" required>
        </label>
        <button type="submit">Einloggen</button>
    </form>
</section>
