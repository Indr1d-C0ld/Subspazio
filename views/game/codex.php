<?php
/** @var list<array<string,mixed>> $entries */
/** @var array{got:int,tot:int} $counts */
/** @var array<string,int> $reperti */
/** @var list<string> $completate */
/** @var list<array<string,mixed>> $progetti */
/** @var bool $at_dock */
$RAR = \App\Game\Loot::RARITY_LABEL;
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

<section class="panel" id="reperti">
  <h2><span class="sec-ic">🏺</span> Reperti e collezioni<?= partial('help', ['key' => 'codex.reperti']) ?></h2>
  <p class="hint">Si trovano abbattendo nemici e spogliando relitti, più spesso lontano da Sol. Si vendono all'antiquario dello StarDock<?= $at_dock ? '' : ' (non sei allo StarDock)' ?>, o si tengono: una collezione completa paga una volta sola crediti, esperienza e un modulo.</p>
  <?php foreach (\App\Game\Reperti::COLLEZIONI as $ck => [$cnome, $ccr, $cxp, $crar]):
    $pezzi = array_filter(\App\Game\Reperti::CATALOGO, static fn ($r) => $r[3] === $ck);
    $tutti = array_filter(array_keys($pezzi), static fn ($k) => ($reperti[$k] ?? 0) > 0);
    $fatta = in_array($ck, $completate, true); ?>
    <h3><?= e($cnome) ?> <span class="mut"><?= count($tutti) ?>/<?= count($pezzi) ?><?= $fatta ? ' · completata' : '' ?></span></h3>
    <ul class="note-list">
      <?php foreach ($pezzi as $rk => [$rnome, $rrar, $rval]): $q = (int) ($reperti[$rk] ?? 0); ?>
        <li class="nl-head">
          <span><span class="rarity rarity-<?= e($rrar) ?>"><?= e($RAR[$rrar] ?? $rrar) ?></span>
            <?= $q > 0 ? '<strong>' . e($rnome) . '</strong> ×' . $q : '<span class="mut">' . e($rnome) . '</span>' ?>
            <span class="mut">· <?= number_format($rval, 0, ',', '.') ?> cr</span></span>
          <?php if ($q > 0 && $at_dock): ?>
            <form method="post" action="<?= e(url('/gioco/reperti/vendi')) ?>" class="inline"
                  data-confirm="Vendere <?= e($rnome) ?> per <?= number_format($rval, 0, ',', '.') ?> cr?">
              <?= csrf_field() ?><input type="hidden" name="ckey" value="<?= e($rk) ?>"><input type="hidden" name="qty" value="1">
              <button class="btn xs ghost" type="submit">Vendi</button>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php if (!$fatta && count($tutti) === count($pezzi)): ?>
      <form method="post" action="<?= e(url('/gioco/reperti/collezione')) ?>" class="inline"
            data-confirm="Consegnare un esemplare di ogni reperto e completare la collezione?">
        <?= csrf_field() ?><input type="hidden" name="collezione" value="<?= e($ck) ?>">
        <button class="btn xs" type="submit">Completa la collezione (+<?= number_format($ccr, 0, ',', '.') ?> cr, +<?= $cxp ?> exp, un modulo almeno <?= e($RAR[$crar] ?? $crar) ?>)</button>
      </form>
    <?php endif; ?>
  <?php endforeach; ?>

  <h2><span class="sec-ic">📐</span> Progetti d'Officina</h2>
  <p class="hint">Sbloccano ricette di moduli Xeno e Precursore. Li lasciano i comandanti d'élite e, di rado, gli altri nemici dalla Frontiera in fuori.</p>
  <ul class="note-list">
    <?php foreach ($progetti as $pg): ?>
      <li><span class="rarity rarity-<?= e($pg['rarity']) ?>"><?= e($RAR[$pg['rarity']] ?? $pg['rarity']) ?></span>
        <?= $pg['posseduto'] ? '<strong>' . e($pg['label']) . '</strong> <span class="pill ok">sbloccato</span>' : '<span class="mut">' . e($pg['label']) . '</span>' ?></li>
    <?php endforeach; ?>
  </ul>
</section>
