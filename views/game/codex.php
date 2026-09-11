<?php
/** @var list<array<string,mixed>> $entries */
/** @var array{got:int,tot:int} $counts */
$byCat = [];
foreach ($entries as $e) {
    $byCat[$e['category']][] = $e;
}
?>
<section class="statusbar">
  <div><span class="k">Codex</span><span class="v"><?= $counts['got'] ?>/<?= $counts['tot'] ?></span></div>
  <div><a href="<?= e(url('/gioco')) ?>">← Plancia</a></div>
</section>

<section class="panel">
  <h1><span class="sec-ic">📚</span> Codex<?= partial('help', ['key' => 'codex.home']) ?></h1>
  <p class="hint">Le voci si sbloccano scansionando, risolvendo anomalie e spingendosi oltre la Frontiera.</p>

  <?php foreach ($byCat as $cat => $list): ?>
    <?php
      $got = array_values(array_filter($list, static fn ($e) => $e['unlocked']));
      $lockedN = count($list) - count($got);
    ?>
    <h2><span class="sec-ic">🔹</span> <?= e(ucfirst($cat)) ?>
      <span class="mut"><?= count($got) ?>/<?= count($list) ?></span></h2>
    <div class="codex-list">
      <?php foreach ($got as $e): ?>
        <div class="codex-entry got">
          <strong><?= e($e['title']) ?></strong>
          <p><?= e($e['body']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if ($lockedN > 0): ?>
      <p class="hint codex-locked">🔒 <?= $lockedN ?>
        <?= $lockedN === 1 ? 'voce' : 'voci' ?> ancora da scoprire in questa categoria.</p>
    <?php endif; ?>
    <?php if ($got === [] && $lockedN === 0): ?>
      <p class="hint">Nessuna voce.</p>
    <?php endif; ?>
  <?php endforeach; ?>
</section>
