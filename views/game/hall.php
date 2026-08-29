<?php
/** @var array<string,mixed> $current */
/** @var list<array<string,mixed>> $hall */
?>
<section class="statusbar">
  <div><span class="k">Stagione in corso</span><span class="v"><?= (int) $current['number'] ?> — <?= e($current['name']) ?></span></div>
  <div><span class="k">Dal</span><span class="v"><?= e($current['started_at']) ?></span></div>
  <div><a href="<?= e(url('/gioco/classifica')) ?>">Classifica</a></div>
  <div><a href="<?= e(url('/gioco')) ?>">← Plancia</a></div>
</section>

<section class="panel">
  <h1>Albo d'oro</h1>
  <?php if ($hall === []): ?>
    <p class="hint">Nessuna stagione conclusa. La Stagione <?= (int) $current['number'] ?> e' la prima.</p>
  <?php else: ?>
    <table class="tbl">
      <thead><tr><th>Stagione</th><th>Vincitore</th><th>Conclusa il</th></tr></thead>
      <tbody>
      <?php foreach ($hall as $h): ?>
        <tr>
          <td>#<?= (int) $h['number'] ?> — <?= e($h['name']) ?></td>
          <td>👑 <strong><?= e($h['winner'] ?? '—') ?></strong></td>
          <td><?= e($h['ended_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>
