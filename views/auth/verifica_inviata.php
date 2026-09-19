<?php /** @var string $email */ ?>
<section class="panel narrow">
  <h1>Controlla la posta</h1>

  <p>Abbiamo spedito un collegamento di conferma<?= $email !== '' ? ' a <strong>' . e($email) . '</strong>' : '' ?>.
     Aprilo e il tuo account sarà attivo: non serve che nessuno lo approvi.</p>

  <p class="hint">Il collegamento vale
     <?= (int) App\Game\GameConfig::int('auth.verify_ttl_hours', 48) ?> ore.
     Se non lo trovi, guarda fra la posta indesiderata: i messaggi automatici
     finiscono spesso lì.</p>

  <form method="post" action="<?= e(url('/rinvia-verifica')) ?>" class="stack">
    <?= csrf_field() ?>
    <label>
      <span>Non è arrivato? Rispediscilo</span>
      <input type="email" name="email" value="<?= e($email) ?>" autocomplete="email"
             autocapitalize="none" autocorrect="off" spellcheck="false" required>
    </label>
    <button type="submit" class="btn">Rispedisci il collegamento</button>
  </form>

  <p class="hint"><a href="<?= e(url('/login')) ?>">← Torna all'accesso</a></p>
</section>
