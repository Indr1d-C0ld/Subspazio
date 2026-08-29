<section class="panel narrow ta-c">
  <h1>Universo non ancora generato</h1>
  <p>La galassia di <?= e(config('app.name', 'SubSpazio')) ?> non e' stata ancora creata.</p>
  <?php if (is_admin()): ?>
    <p class="hint">Da amministratore, esegui:</p>
    <pre class="ta-l"><code>php /data/html/subspazio/bin/console.php universe:generate</code></pre>
  <?php else: ?>
    <p class="hint">Torna piu' tardi: l'amministratore deve avviare la partita.</p>
  <?php endif; ?>
  <p><a class="btn ghost" href="<?= e(url('/')) ?>">Home</a></p>
</section>
