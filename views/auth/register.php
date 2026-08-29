<section class="panel narrow">
  <h1>Richiedi un accesso</h1>
  <p class="hint">La registrazione crea una richiesta: un amministratore la approvera' prima del primo accesso.</p>
  <form method="post" action="<?= e(url('/registrati')) ?>" class="stack">
    <?= csrf_field() ?>
    <label>
      <span>Nome utente</span>
      <input type="text" name="username" value="<?= e(old('username')) ?>"
             pattern="[A-Za-z0-9_]{3,32}" title="3-32 caratteri: lettere, numeri, underscore"
             autocomplete="username" required autofocus>
    </label>
    <label>
      <span>Email</span>
      <input type="email" name="email" value="<?= e(old('email')) ?>" autocomplete="email" required>
    </label>
    <label>
      <span>Password (min. 10 caratteri)</span>
      <input type="password" name="password" autocomplete="new-password" minlength="10" required>
    </label>
    <label>
      <span>Conferma password</span>
      <input type="password" name="password_confirm" autocomplete="new-password" minlength="10" required>
    </label>
    <button type="submit" class="btn">Invia richiesta</button>
  </form>
  <p class="hint">Hai gia' un accesso? <a href="<?= e(url('/login')) ?>">Accedi</a>.</p>
</section>
