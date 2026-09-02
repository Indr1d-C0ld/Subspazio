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
  <h1>Codex</h1>
  <p class="hint">Le voci si sbloccano scansionando, risolvendo anomalie e spingendosi oltre la Frontiera.</p>

  <?php foreach ($byCat as $cat => $list): ?>
    <h2><?= e(ucfirst($cat)) ?></h2>
    <div class="codex-list">
      <?php foreach ($list as $e): ?>
        <div class="codex-entry<?= $e['unlocked'] ? ' got' : ' locked' ?>">
          <strong><?= $e['unlocked'] ? e($e['title']) : '???' ?></strong>
          <p><?= $e['unlocked'] ? e($e['body']) : '<span class="mut">Voce non ancora sbloccata.</span>' ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</section>
