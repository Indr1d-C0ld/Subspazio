<?php
/** @var array<string,mixed> $player */
/** @var list<array<string,mixed>> $all */
/** @var array<string,string> $earned */
/** @var int $points */
?>
<section class="statusbar">
  <div><span class="k">Comandante</span><span class="v"><?= e($player['handle']) ?></span></div>
  <div><span class="k">Traguardi</span><span class="v"><?= count($earned) ?>/<?= count($all) ?></span></div>
  <div><span class="k">Punti</span><span class="v"><?= (int) $points ?></span></div>
  <div><a href="<?= e(url('/gioco/albo')) ?>">Albo d'oro</a></div>
  <div><a href="<?= e(url('/gioco')) ?>">← Plancia</a></div>
</section>

<section class="panel">
  <h1>Traguardi</h1>
  <div class="ach-grid">
    <?php foreach ($all as $a): $got = isset($earned[$a['ckey']]); ?>
      <div class="ach<?= $got ? ' got' : '' ?>">
        <span class="ach-icon"><?= e($a['icon']) ?></span>
        <div>
          <strong><?= e($a['name']) ?></strong> <span class="ach-pts"><?= (int) $a['points'] ?></span>
          <p><?= e($a['descr']) ?></p>
          <?php if ($got): ?><small class="hint">sbloccato <?= e($earned[$a['ckey']]) ?></small><?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
