<section class="panel narrow">
  <h1>Accedi</h1>
  <form method="post" action="<?= e(url('/login')) ?>" class="stack">
    <?= csrf_field() ?>
    <label>
      <span>Nome utente o email</span>
      <input type="text" name="login" value="<?= e(old('login')) ?>" autocomplete="username"
             autocapitalize="none" autocorrect="off" spellcheck="false" inputmode="text" required autofocus>
    </label>
    <label>
      <span>Password</span>
      <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <button type="submit" class="btn">Entra</button>
  </form>
  <p class="hint">Non hai un accesso? <a href="<?= e(url('/registrati')) ?>">Richiedilo qui</a>.</p>
</section>
