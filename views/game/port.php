<?php
/** @var array<string,mixed> $player */
/** @var array<string,mixed> $ship */
/** @var array<string,mixed> $port */
/** @var list<array<string,mixed>> $rows */
/** @var array<string,mixed>|null $haggle */
$used = \App\Game\Economy::holdsUsed($ship);
?>
<section class="statusbar">
  <div><span class="k">Comandante</span><span class="v"><?= e($player['handle']) ?></span></div>
  <div><span class="k">Crediti</span><span class="v" data-bind="credits"><?= number_format((int) $player['credits'], 0, ',', '.') ?></span></div>
  <div><span class="k">Stive</span><span class="v" data-bind="holds"><?= $used ?>/<?= (int) $ship['holds_total'] ?></span></div>
  <div><span class="k">Settore</span><span class="v"><?= (int) $player['sector_id'] ?></span></div>
  <div><a href="<?= e(url('/gioco')) ?>">← Plancia</a></div>
</section>

<section class="panel port-card"
         data-haggle-url="<?= e(url('/api/porto/contratta')) ?>"
         data-offer-url="<?= e(url('/api/porto/contratta/offerta')) ?>"
         data-accept-url="<?= e(url('/api/porto/contratta/accetta')) ?>"
         data-abort-url="<?= e(url('/api/porto/contratta/lascia')) ?>">
  <header class="sector-head">
    <h1><?= e($port['name']) ?></h1>
    <span class="tag port">Classe <?= (int) $port['class'] ?> · <?= e($port['code']) ?></span>
    <span class="tag">Tech <?= (int) $port['tech'] ?></span>
    <?php if ($port['is_stardock']): ?><span class="tag dock">StarDock</span><?php endif; ?>
  </header>
  <p class="hint">
    <strong>VENDE a te</strong> = puoi comprare · <strong>COMPRA da te</strong> = puoi vendere.
    Il prezzo si muove con le scorte del porto: ogni scambio sposta il mercato.
  </p>

  <table class="tbl port-table">
    <thead>
      <tr><th>Merce</th><th>Verso</th><th>Scorte</th><th class="ta-r">Prezzo</th><th class="ta-r">Equo</th><th class="ta-r">Max</th><th class="ta-r">A bordo</th><th>Azione</th></tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><strong><?= e($r['label']) ?></strong></td>
        <td>
          <?php if ($r['mode'] === 'sell'): ?><span class="pill ok">vende a te</span>
          <?php else: ?><span class="pill warn">compra da te</span><?php endif; ?>
        </td>
        <td>
          <span class="bar"><span style="width: <?= (int) $r['pct'] ?>%"></span></span>
          <small><?= (int) $r['pct'] ?>%</small>
        </td>
        <td class="ta-r nowrap"><?= number_format((float) $r['unit'], 2, ',', '.') ?></td>
        <td class="ta-r nowrap muted"><?= number_format((float) $r['fair'], 2, ',', '.') ?></td>
        <td class="ta-r"><?= (int) $r['max'] ?></td>
        <td class="ta-r"><?= (int) $r['cargo'] ?></td>
        <td class="nowrap">
          <?php if ($r['max'] > 0): ?>
          <form method="post" action="<?= e(url('/gioco/porto/scambio')) ?>" class="inline trade-form">
            <?= csrf_field() ?>
            <input type="hidden" name="commodity" value="<?= e($r['commodity']) ?>">
            <input type="hidden" name="action" value="<?= e($r['action']) ?>">
            <input type="number" name="qty" min="1" max="<?= (int) $r['max'] ?>" value="<?= min(10, (int) $r['max']) ?>" class="qty">
            <button class="btn xs" type="submit"><?= $r['action'] === 'buy' ? 'Compra' : 'Vendi' ?> veloce</button>
            <button class="btn xs ghost haggle-btn" type="button"
                    data-commodity="<?= e($r['commodity']) ?>" data-action="<?= e($r['action']) ?>"
                    data-label="<?= e($r['label']) ?>" data-max="<?= (int) $r['max'] ?>">Contratta</button>
          </form>
          <?php else: ?>
            <span class="hint">—</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div id="haggle-panel" class="haggle-panel" hidden>
    <h2>Contrattazione — <span id="hg-title"></span></h2>
    <p id="hg-line" class="hg-line"></p>
    <div class="hg-controls">
      <label>La tua offerta (cr totali)
        <input type="number" id="hg-offer" min="1" step="1">
      </label>
      <button class="btn xs" id="hg-send" type="button">Offri</button>
      <button class="btn xs" id="hg-accept" type="button">Accetta offerta del porto</button>
      <button class="btn xs ghost" id="hg-quit" type="button">Lascia</button>
    </div>
    <p id="hg-result" class="hg-result"></p>
  </div>

  <?php if ($port['is_stardock']): ?>
    <p class="port-links"><a class="btn ghost" href="<?= e(url('/gioco/banca')) ?>">Banca Intergalattica</a></p>
  <?php endif; ?>
</section>

<script src="<?= e(asset('js/port.js')) ?>" defer></script>
