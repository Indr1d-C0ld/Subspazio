<?php
/** @var bool $debug */
/** @var string $detail */
?>
<section class="panel narrow ta-c">
  <h1 class="big">503</h1>
  <p>Il database non e' raggiungibile. Il gioco tornera' disponibile a breve.</p>
  <?php if ($debug): ?>
    <pre class="ta-l"><code><?= e($detail) ?></code></pre>
    <p class="hint">Verifica <code>/data/subspazio-config/config.php</code> e che MariaDB sia attivo,
       poi esegui <code>php bin/console.php migrate</code>.</p>
  <?php endif; ?>
</section>
