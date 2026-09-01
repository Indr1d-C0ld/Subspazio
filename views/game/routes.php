<?php
/** @var array<string,mixed> $player */
/** @var list<array<string,mixed>> $recent */
/** @var list<array<string,mixed>> $visited */
/** @var array{moves:int,turns:int,distinct_dest:int} $stats */
/** @var list<array<string,mixed>> $notes */
?>
<section class="statusbar">
  <div><span class="k">Warp totali</span><span class="v"><?= number_format((int) $player['total_warps'], 0, ',', '.') ?></span></div>
  <div><span class="k">Spostamenti</span><span class="v"><?= number_format($stats['moves'], 0, ',', '.') ?></span></div>
  <div><span class="k">Turni spesi</span><span class="v"><?= number_format($stats['turns'], 0, ',', '.') ?></span></div>
  <div><span class="k">Mete distinte</span><span class="v"><?= $stats['distinct_dest'] ?></span></div>
  <div><a href="<?= e(url('/gioco/battaglie')) ?>">Battaglie</a></div>
  <div><a href="<?= e(url('/gioco')) ?>">← Plancia</a></div>
</section>

<div class="game-grid">
  <section class="panel">
    <h1>Ultimi spostamenti</h1>
    <table class="tbl compact">
      <thead><tr><th>Quando</th><th>Da → A</th><th>Modo</th><th class="ta-r">TL</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($recent as $r): ?>
        <tr>
          <td class="nowrap"><?= e(fmt_dt($r['at'])) ?></td>
          <td><?= (int) $r['from'] ?> → <strong><?= (int) $r['to'] ?></strong> <?= $r['to_name'] ? '<small>' . e($r['to_name']) . '</small>' : '' ?></td>
          <td><?= e($r['mode']) ?></td>
          <td class="ta-r"><?= (int) $r['turns'] ?></td>
          <td><a class="btn xs ghost" href="<?= e(url('/gioco/rotta?to=' . $r['to'])) ?>">Ripercorri</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($recent === []): ?><tr><td colspan="5" class="hint">Nessuno spostamento.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </section>

  <section class="panel">
    <h1>Settori più frequentati</h1>
    <table class="tbl compact">
      <thead><tr><th>Settore</th><th class="ta-r">Visite</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($visited as $v): ?>
        <tr>
          <td><?= (int) $v['sector'] ?> <small><?= e($v['name']) ?></small></td>
          <td class="ta-r"><?= (int) $v['visits'] ?></td>
          <td><a class="btn xs ghost" href="<?= e(url('/gioco/rotta?to=' . $v['sector'])) ?>">Rotta</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <h2>Preferiti e note</h2>
    <?php if ($notes === []): ?>
      <p class="hint">Nessuna nota. Le aggiungi dalla scheda del settore in plancia.</p>
    <?php else: ?>
    <ul class="note-list">
      <?php foreach ($notes as $n): ?>
        <li>
          <?= $n['pinned'] ? '★ ' : '' ?><a href="<?= e(url('/gioco/rotta?to=' . $n['sector_id'])) ?>"><?= (int) $n['sector_id'] ?></a>
          <strong><?= e($n['label'] ?? $n['name']) ?></strong>
          <?php if ($n['note']): ?><span class="nl-note"><?= e($n['note']) ?></span><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>
</div>
