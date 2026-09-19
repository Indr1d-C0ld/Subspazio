<section class="panel narrow">
  <h1>Password dimenticata</h1>

  <p>Indica l'indirizzo con cui ti sei iscritto: ti arriverà un collegamento
     per sceglierne una nuova.</p>

  <form method="post" action="<?= e(url('/password-dimenticata')) ?>" class="stack">
    <?= csrf_field() ?>
    <label>
      <span>Email</span>
      <input type="email" name="email" value="<?= e(old('email')) ?>" autocomplete="email"
             autocapitalize="none" autocorrect="off" spellcheck="false" required autofocus>
    </label>
    <button type="submit" class="btn">Mandami il collegamento</button>
  </form>

  <p class="hint">Il collegamento vale
     <?= (int) App\Game\GameConfig::int('auth.reset_ttl_hours', 2) ?> ore e una volta sola.
     Aprendolo verranno chiuse le sessioni già aperte sul tuo account.</p>
  <p class="hint"><a href="<?= e(url('/login')) ?>">← Torna all'accesso</a></p>
</section>
