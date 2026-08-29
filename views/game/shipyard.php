<?php
/** @var array<string,mixed> $player */
/** @var array<string,mixed> $ship */
/** @var list<array<string,mixed>> $catalog */
/** @var int $trade_in */
/** @var int $used */
/** @var array<string,float|int> $prices */
$cr = (int) $player['credits'];
?>
<section class="statusbar">
  <div><span class="k">Crediti</span><span class="v"><?= number_format($cr, 0, ',', '.') ?></span></div>
  <div><span class="k">Nave</span><span class="v"><?= e($ship['type_name']) ?></span></div>
  <div><span class="k">Stive</span><span class="v"><?= $used ?>/<?= (int) $ship['holds_total'] ?> (max <?= (int) $ship['max_holds'] ?>)</span></div>
  <div><span class="k">Caccia</span><span class="v"><?= number_format((int) $ship['fighters'], 0, ',', '.') ?>/<?= number_format((int) $ship['max_fighters'], 0, ',', '.') ?></span></div>
  <div><span class="k">Scudi</span><span class="v"><?= number_format((int) $ship['shields'], 0, ',', '.') ?>/<?= number_format((int) $ship['max_shields'], 0, ',', '.') ?></span></div>
  <div><a href="<?= e(url('/gioco')) ?>">← Plancia</a></div>
</section>

<section class="panel">
  <h1>Cantiere StarDock</h1>

  <h2>Potenziamenti</h2>
  <div class="upg-grid">
    <form method="post" action="<?= e(url('/gioco/cantiere/upgrade')) ?>" class="row">
      <?= csrf_field() ?><input type="hidden" name="kind" value="holds">
      <label>Stive (<?= number_format((int) ($ship['hold_price'] ?? 0), 0, ',', '.') ?> cr cad.) <input type="number" name="qty" min="1" value="10" class="qty"></label>
      <button class="btn xs" type="submit">Compra stive</button>
    </form>
    <form method="post" action="<?= e(url('/gioco/cantiere/upgrade')) ?>" class="row">
      <?= csrf_field() ?><input type="hidden" name="kind" value="fighters">
      <label>Caccia (<?= number_format($prices['fighter'], 2, ',', '.') ?> cr cad.) <input type="number" name="qty" min="1" value="100" class="qty"></label>
      <button class="btn xs" type="submit">Compra caccia</button>
    </form>
    <form method="post" action="<?= e(url('/gioco/cantiere/upgrade')) ?>" class="row">
      <?= csrf_field() ?><input type="hidden" name="kind" value="shields">
      <label>Scudi (<?= number_format($prices['shield'], 2, ',', '.') ?> cr cad.) <input type="number" name="qty" min="1" value="100" class="qty"></label>
      <button class="btn xs" type="submit">Compra scudi</button>
    </form>
  </div>

  <h2>Hardware</h2>
  <div class="upg-grid">
    <?php
    $hw = [
      ['genesis', 'Siluri Genesi', $prices['genesis'] ?? 31000, (int) $ship['genesis'], true],
      ['probe', 'Sonde etere', $prices['probe'], (int) $ship['probes'], true],
      ['armid', 'Mine Armid', $prices['armid'], (int) $ship['mines_armid'], true],
      ['limpet', 'Mine Limpet', $prices['limpet'], (int) $ship['mines_limpet'], true],
      ['escape_pod', 'Capsula di salvataggio', $prices['escape_pod'], (int) $ship['escape_pod'], false],
      ['scanner_density', 'Scanner di densita\'', $prices['scanner_density'], $ship['dev_scanner'] === 'density' || $ship['dev_scanner'] === 'holo', false],
      ['scanner_holo', 'Scanner olografico', $prices['scanner_holo'], $ship['dev_scanner'] === 'holo', false],
      ['transwarp', 'Motore transwarp', $prices['transwarp'], (int) $ship['dev_transwarp'], false],
      ['cloak', 'Occultamento', $prices['cloak'], (int) $ship['dev_cloak'], false],
    ];
    foreach ($hw as [$key, $label, $price, $have, $qtyItem]): ?>
      <form method="post" action="<?= e(url('/gioco/cantiere/hardware')) ?>" class="row">
        <?= csrf_field() ?><input type="hidden" name="item" value="<?= e($key) ?>">
        <label><?= e($label) ?> — <?= number_format((int) $price, 0, ',', '.') ?> cr
          <?php if ($qtyItem): ?>
            <input type="number" name="qty" min="1" value="1" class="qty"> <small>(hai <?= (int) $have ?>)</small>
          <?php else: ?>
            <?= $have ? '<small>installato</small>' : '' ?>
          <?php endif; ?>
        </label>
        <button class="btn xs" type="submit"<?= (!$qtyItem && $have) ? ' disabled' : '' ?>>Compra</button>
      </form>
    <?php endforeach; ?>
  </div>

  <h2>Navi — permuta stimata: <?= number_format($trade_in, 0, ',', '.') ?> cr</h2>
  <table class="tbl">
    <thead><tr><th>Modello</th><th class="ta-r">Stive</th><th class="ta-r">Caccia</th><th class="ta-r">Scudi</th><th class="ta-r">Combat</th><th class="ta-r">Warp</th><th class="ta-r">Prezzo netto</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($catalog as $t):
      $net = max(0, (int) $t['base_cost'] - $trade_in);
      $mine = $t['ckey'] === $ship['type_key'];
    ?>
      <tr<?= $mine ? ' class="row-current"' : '' ?>>
        <td><strong><?= e($t['name']) ?></strong><?= $mine ? ' <span class="pill mut">attuale</span>' : '' ?></td>
        <td class="ta-r"><?= (int) $t['base_holds'] ?>–<?= (int) $t['max_holds'] ?></td>
        <td class="ta-r"><?= number_format((int) $t['max_fighters'], 0, ',', '.') ?></td>
        <td class="ta-r"><?= number_format((int) $t['max_shields'], 0, ',', '.') ?></td>
        <td class="ta-r"><?= number_format((float) $t['combat_rating'], 1, ',', '.') ?></td>
        <td class="ta-r"><?= (int) $t['turns_per_warp'] ?></td>
        <td class="ta-r nowrap"><?= number_format($net, 0, ',', '.') ?></td>
        <td>
          <?php if (!$mine): ?>
          <form method="post" action="<?= e(url('/gioco/cantiere/nave')) ?>" class="inline">
            <?= csrf_field() ?><input type="hidden" name="type" value="<?= e($t['ckey']) ?>">
            <button class="btn xs" type="submit"<?= $net > $cr ? ' disabled' : '' ?>>Acquista</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="hint">Caccia, scudi, scanner, transwarp e occultamento non si trasferiscono alla nuova nave. Le stive devono contenere il carico attuale.</p>
</section>
