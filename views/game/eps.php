<?php
/** @var array<string,mixed> $player */
/** @var array<string,mixed> $ship */
/** @var list<array<string,mixed>> $grid */
/** @var int $nominal */
/** @var int $max_per */
/** @var int $total */
/** @var int $cost */
/** @var float $step_pct */
/** @var bool $is_pod */
?>
<section class="statusbar">
  <div><span class="k">Griglia di potenza</span><span class="v"><?= e($ship['type_name'] ?? $ship['type_key']) ?></span></div>
  <div><span class="k">Turni</span><span class="v" data-bind="turns"><?= (int) $player['turns'] ?></span></div>
  <div><a href="<?= e(url('/gioco')) ?>">← Plancia</a></div>
</section>

<section class="panel" id="eps-panel">
  <h1>Griglia di potenza <span class="mut">EPS</span></h1>
  <p class="hint">Il reattore fornisce <strong><?= (int) $total ?> tacche</strong> di potenza da
     ripartire su quattro canali. Il nominale è <strong><?= (int) $nominal ?></strong> per canale
     (nessun effetto); la somma resta sempre <?= (int) $total ?>. A <strong>+<?= (int) $nominal ?> tacche</strong>
     un canale rende +25%, a <strong>0</strong> rende −25%. Ri-tarare costa
     <strong><?= (int) $cost ?> turno<?= $cost === 1 ? '' : 'i' ?></strong>.</p>

  <?php if ($is_pod): ?>
    <p class="hint">La capsula di salvataggio non ha una griglia di potenza. Torna ai comandi di una nave vera.</p>
  <?php else: ?>
  <form method="post" action="<?= e(url('/gioco/eps')) ?>" class="eps-form" id="eps-form"
        data-total="<?= (int) $total ?>" data-nominal="<?= (int) $nominal ?>"
        data-max="<?= (int) $max_per ?>" data-step="<?= e((string) $step_pct) ?>">
    <?= csrf_field() ?>

    <div class="eps-budget">Potenza distribuita: <strong id="eps-used"><?= (int) $total ?></strong> / <?= (int) $total ?></div>

    <div class="eps-grid">
      <?php foreach ($grid as $ch): ?>
        <div class="eps-ch" data-key="<?= e($ch['key']) ?>">
          <div class="eps-ch-head">
            <span class="eps-ic"><?= $ch['icon'] ?></span>
            <strong><?= e($ch['label']) ?></strong>
            <span class="eps-mult" data-role="mult"><?= ($ch['pct'] > 0 ? '+' : '') . (int) $ch['pct'] ?>%</span>
          </div>
          <label class="eps-field">
            <span class="mut">tacche (0–<?= (int) $max_per ?>)</span>
            <input type="number" name="eps_<?= e($ch['key']) ?>" value="<?= (int) $ch['pips'] ?>"
                   min="0" max="<?= (int) $max_per ?>" step="1" inputmode="numeric" data-role="input">
          </label>
          <p class="eps-effect" data-role="effect"><?= e($ch['effect']) ?></p>
          <p class="eps-strain" data-role="strain"<?= empty($ch['strain']) ? ' hidden' : '' ?>>⚠ canale al massimo: rischio di guasti per sovraccarico a ogni warp</p>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="eps-actions">
      <button type="submit" class="btn" id="eps-apply">Applica (−<?= (int) $cost ?> turno<?= $cost === 1 ? '' : 'i' ?>)</button>
      <button type="button" class="btn ghost" id="eps-balance">Bilanciato</button>
      <a class="btn ghost" href="<?= e(url('/gioco')) ?>">Annulla</a>
    </div>
    <p class="hint eps-warn" id="eps-warn" hidden>Distribuisci esattamente <?= (int) $total ?> tacche prima di applicare.</p>
  </form>
  <?php endif; ?>
</section>

<script src="<?= e(asset('js/eps.js')) ?>" defer></script>
