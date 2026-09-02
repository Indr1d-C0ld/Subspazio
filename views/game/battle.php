<?php
/** @var array<string,mixed> $player */
/** @var array<string,mixed> $b */
?>
<section class="statusbar">
  <div><span class="k">Battaglia</span><span class="v">#<?= (int) $b['id'] ?></span></div>
  <div><span class="k">Settore</span><span class="v"><?= (int) $b['sector'] ?><?= $b['sector_name'] ? ' — ' . e($b['sector_name']) : '' ?></span></div>
  <div><a href="<?= e(url('/gioco/battaglie')) ?>">← Registro</a></div>
</section>

<section class="panel">
  <h1><?= e($b['attacker']) ?> <span class="vs">vs</span> <?= e($b['defender']) ?></h1>
  <p class="hint"><?= e(fmt_dt($b['at'])) ?> · tipo <?= e($b['kind']) ?> · <?= (int) $b['rounds'] ?> round · esito <strong><?= e($b['outcome']) ?></strong><?= $b['loot'] ? ' · bottino ' . number_format($b['loot'], 0, ',', '.') . ' cr' : '' ?></p>
  <?php $dr = $b['drops'] ?? ['items' => [], 'salvage' => 0]; if (!empty($dr['salvage']) || !empty($dr['items'])): ?>
    <p class="hint">Recuperato:
      <?php if (!empty($dr['salvage'])): ?><span class="pill mut">+<?= number_format((int) $dr['salvage'], 0, ',', '.') ?> Leghe</span><?php endif; ?>
      <?php foreach ($dr['items'] as $it): ?><span class="rarity rarity-<?= e($it['rarity']) ?>"><?= e($it['name']) ?> · <?= e($it['label']) ?></span><?php endforeach; ?>
    </p>
  <?php endif; ?>

  <?php if ($b['trace'] === []): ?>
    <p class="hint">Nessun dettaglio round-per-round per questo scontro (registrato prima dell'aggiornamento replay).</p>
  <?php else: ?>
  <div id="replay"
       data-trace='<?= e(json_encode($b['trace'], JSON_UNESCAPED_SLASHES)) ?>'
       data-att="<?= e($b['attacker']) ?>"
       data-def="<?= e($b['defender']) ?>"
       data-att0="<?= (int) ($b['att_ftr0'] ?? 0) ?>"
       data-def0="<?= (int) ($b['def_ftr0'] ?? 0) ?>">
    <div class="rp-side">
      <div class="rp-name" id="rp-att-name"></div>
      <div class="rp-bar"><span id="rp-att-ftr"></span></div>
      <div class="rp-nums"><span id="rp-att-f">—</span> caccia · <span id="rp-att-s">—</span> scudi</div>
    </div>
    <div class="rp-side">
      <div class="rp-name" id="rp-def-name"></div>
      <div class="rp-bar def"><span id="rp-def-ftr"></span></div>
      <div class="rp-nums"><span id="rp-def-f">—</span> caccia · <span id="rp-def-s">—</span> scudi</div>
    </div>
    <div class="rp-controls">
      <button class="btn xs" id="rp-play">▶ Play</button>
      <button class="btn xs ghost" id="rp-step">Passo</button>
      <button class="btn xs ghost" id="rp-reset">⟲</button>
      <span id="rp-round" class="rp-round">Round 0</span>
    </div>
    <ol id="rp-log" class="rp-log"></ol>
  </div>
  <script src="<?= e(asset('js/replay.js')) ?>" defer></script>
  <?php endif; ?>
</section>
