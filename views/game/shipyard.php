<?php
/**
 * @var array<string,mixed> $player
 * @var array<string,mixed> $ship
 * @var list<array<string,mixed>> $catalog
 * @var int $trade_in
 * @var int $used
 * @var array<string,float|int> $prices
 * @var list<array<string,mixed>> $broken
 * @var int $repair_each
 */
$cr    = (int) $player['credits'];
$isPod = ($ship['type_key'] ?? '') === 'escape_pod';
$scan  = (string) ($ship['dev_scanner'] ?? 'none');

/** Card "acquisto": nome + prezzo, poi o il form o lo stato di possesso. */
$buy = static function (
    string $action,
    array $hidden,
    string $name,
    string $price,
    ?string $owned = null,
    bool $qty = false,
    ?string $note = null,
    bool $disabled = false
): void {
    echo '<div class="buy-card' . ($owned !== null ? ' is-owned' : '') . '">';
    echo '<div class="buy-head"><span class="buy-name">' . e($name) . '</span>'
       . '<span class="buy-price">' . e($price) . '</span></div>';
    if ($note !== null) {
        echo '<p class="buy-note">' . e($note) . '</p>';
    }
    if ($owned !== null) {
        echo '<span class="pill ok">✓ ' . e($owned) . '</span>';
    } else {
        echo '<form method="post" action="' . e($action) . '" class="buy-act">' . csrf_field();
        foreach ($hidden as $k => $v) {
            echo '<input type="hidden" name="' . e($k) . '" value="' . e((string) $v) . '">';
        }
        if ($qty) {
            echo '<input type="number" name="qty" min="1" value="1" class="qty" aria-label="quantità">';
        }
        echo '<button class="btn xs" type="submit"' . ($disabled ? ' disabled' : '') . '>Compra</button>';
        echo '</form>';
    }
    echo '</div>';
};
?>
<section class="statusbar">
  <div><span class="k">Crediti</span><span class="v"><?= number_format($cr, 0, ',', '.') ?></span></div>
  <div><span class="k">Nave</span><span class="v"><?= e($ship['type_name']) ?></span></div>
  <div><span class="k">Stive</span><span class="v"><?= $used ?>/<?= (int) $ship['holds_total'] ?> <span class="mut">max <?= (int) $ship['max_holds'] ?></span></span></div>
  <div><span class="k">Caccia</span><span class="v"><?= number_format((int) $ship['fighters'], 0, ',', '.') ?>/<?= number_format((int) $ship['max_fighters'], 0, ',', '.') ?></span></div>
  <div><span class="k">Scudi</span><span class="v"><?= number_format((int) $ship['shields'], 0, ',', '.') ?>/<?= number_format((int) $ship['max_shields'], 0, ',', '.') ?></span></div>
  <div><a href="<?= e(url('/gioco/moduli')) ?>">Officina moduli</a></div>
  <div><a href="<?= e(url('/gioco')) ?>">← Plancia</a></div>
</section>

<section class="panel">
  <h1><span class="sec-ic">🛠️</span> Cantiere StarDock</h1>
  <p class="hint">Allo StarDock cambi scafo, potenzi la nave e installi hardware.
     I prezzi sono in crediti; hai <strong><?= number_format($cr, 0, ',', '.') ?> cr</strong>.</p>

  <?php if ($isPod):
    $cheapest = null;
    foreach ($catalog as $t) { $bc = (int) $t['base_cost']; if ($cheapest === null || $bc < $cheapest) $cheapest = $bc; }
  ?>
  <div class="alert event-banner">
    <strong>Sei in capsula di salvataggio.</strong>
    Compra uno scafo dalla tabella «Navi» (il più economico costa <?= number_format((int) $cheapest, 0, ',', '.') ?> cr).
    <?php if ($cr < (int) $cheapest): ?>
      Non hai crediti a sufficienza: la Federazione può assegnarti una nave di soccorso.
      <form method="post" action="<?= e(url('/gioco/cantiere/soccorso')) ?>" class="inline">
        <?= csrf_field() ?><button class="btn xs" type="submit">Richiedi nave di soccorso</button>
      </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</section>

<?php if (!empty($broken)): ?>
<section class="panel repair-box">
  <h2><span class="sec-ic">🔧</span> Riparazioni <span class="pill err"><?= count($broken) ?> fuori uso</span><?= partial('help', ['key' => 'cantiere.riparazioni']) ?></h2>
  <ul class="repair-list">
    <?php foreach ($broken as $b): ?>
      <li><strong><?= e($b['name']) ?></strong> <span class="mut">slot <?= e($b['slot']) ?> — dal <?= e(fmt_dt($b['broken_at'])) ?></span></li>
    <?php endforeach; ?>
  </ul>
  <form method="post" action="<?= e(url('/gioco/cantiere/riparazioni')) ?>" class="inline">
    <?= csrf_field() ?>
    <button class="btn" type="submit">Ripara tutto — <?= number_format(count($broken) * (int) $repair_each, 0, ',', '.') ?> cr</button>
  </form>
  <p class="hint"><?= number_format((int) $repair_each, 0, ',', '.') ?> cr a modulo. In alternativa l'Ingegnere di bordo ne rimette in linea sul tick, e dopo qualche ora un modulo si ripristina da solo.</p>
</section>
<?php endif; ?>

<?php if (!$isPod): ?>
<section class="panel">
  <h2><span class="sec-ic">⬆️</span> Potenziamenti<?= partial('help', ['key' => 'cantiere.potenziamenti']) ?></h2>
  <p class="hint">Aumentano i limiti della nave attuale entro il massimo dello scafo.</p>
  <div class="buy-grid">
    <?php
    $U = url('/gioco/cantiere/upgrade');
    $buy($U, ['kind' => 'holds'], 'Stive',
        number_format((int) ($ship['hold_price'] ?? 0), 0, ',', '.') . ' cr/u', null, true,
        'A bordo ' . $used . '/' . (int) $ship['holds_total'] . ', max ' . (int) $ship['max_holds']);
    $buy($U, ['kind' => 'fighters'], 'Caccia',
        number_format((float) $prices['fighter'], 2, ',', '.') . ' cr/u', null, true,
        'A bordo ' . number_format((int) $ship['fighters'], 0, ',', '.') . '/' . number_format((int) $ship['max_fighters'], 0, ',', '.'));
    $buy($U, ['kind' => 'shields'], 'Scudi',
        number_format((float) $prices['shield'], 2, ',', '.') . ' cr/u', null, true,
        'A bordo ' . number_format((int) $ship['shields'], 0, ',', '.') . '/' . number_format((int) $ship['max_shields'], 0, ',', '.'));
    ?>
  </div>
</section>

<section class="panel">
  <h2><span class="sec-ic">🔌</span> Hardware<?= partial('help', ['key' => 'cantiere.hardware']) ?></h2>

  <h3 class="buy-subhead">Munizioni &amp; sonde <span class="mut">a consumo, quantità a scelta</span></h3>
  <div class="buy-grid">
    <?php
    $H = url('/gioco/cantiere/hardware');
    $buy($H, ['item' => 'genesis'], 'Siluri Genesi',
        number_format((int) ($prices['genesis'] ?? 31000), 0, ',', '.') . ' cr', null, true,
        'A bordo ' . (int) $ship['genesis']);
    $buy($H, ['item' => 'probe'], 'Sonde etere',
        number_format((int) $prices['probe'], 0, ',', '.') . ' cr', null, true,
        'A bordo ' . (int) $ship['probes']);
    $buy($H, ['item' => 'armid'], 'Mine Armid',
        number_format((int) $prices['armid'], 0, ',', '.') . ' cr', null, true,
        'A bordo ' . (int) $ship['mines_armid']);
    $buy($H, ['item' => 'limpet'], 'Mine Limpet',
        number_format((int) $prices['limpet'], 0, ',', '.') . ' cr', null, true,
        'A bordo ' . (int) $ship['mines_limpet']);
    ?>
  </div>

  <h3 class="buy-subhead">Dispositivi di bordo <span class="mut">permanenti, uno per tipo</span></h3>
  <div class="buy-grid">
    <?php
    // scanner: density < holo. L'olografico include la densità.
    $buy($H, ['item' => 'scanner_density'], 'Scanner di densità',
        number_format((int) $prices['scanner_density'], 0, ',', '.') . ' cr',
        $scan === 'density' ? 'installato' : ($scan === 'holo' ? 'incluso nell\'olografico' : null),
        false, 'Rivela le scorte dei porti e le mine nei settori vicini.');
    $buy($H, ['item' => 'scanner_holo'], 'Scanner olografico',
        number_format((int) $prices['scanner_holo'], 0, ',', '.') . ' cr',
        $scan === 'holo' ? 'installato' : null,
        false, 'Il migliore: vede anche le navi occultate nel tuo settore.');
    $buy($H, ['item' => 'transwarp'], 'Motore Transwarp',
        number_format((int) $prices['transwarp'], 0, ',', '.') . ' cr',
        !empty($ship['dev_transwarp']) ? 'installato' : null,
        false, 'Salto diretto a un settore già esplorato, a turni fissi.');
    $buy($H, ['item' => 'cloak'], 'Occultamento',
        number_format((int) $prices['cloak'], 0, ',', '.') . ' cr',
        !empty($ship['dev_cloak']) ? 'installato' : null,
        false, 'Ti toglie dai sensori. Più lento e disarmato da occultato.');
    $buy($H, ['item' => 'mining_laser'], 'Laser minerario',
        number_format((int) ($prices['mining_laser'] ?? 8000), 0, ',', '.') . ' cr',
        !empty($ship['mining_laser']) ? 'installato' : null,
        false, 'Estrae minerale dai giacimenti di asteroidi.');
    $buy($H, ['item' => 'escape_pod'], 'Capsula di salvataggio',
        number_format((int) $prices['escape_pod'], 0, ',', '.') . ' cr',
        !empty($ship['escape_pod']) ? 'installato' : null,
        false, 'Ti salva la vita quando la nave viene distrutta.');
    ?>
  </div>
</section>
<?php endif; ?>

<section class="panel">
  <h2><span class="sec-ic">🚀</span> Navi<?= partial('help', ['key' => 'cantiere.navi']) ?></h2>
  <p class="hint">Prezzo <strong>netto</strong> = costo dello scafo meno la permuta stimata della tua nave
     (<?= number_format($trade_in, 0, ',', '.') ?> cr). Caccia, scudi e hardware non si trasferiscono;
     le nuove stive devono contenere il carico attuale.</p>
  <div class="table-wrap">
  <table class="tbl ships-tbl">
    <thead><tr>
      <th>Modello</th><th class="ta-r">Stive</th><th class="ta-r">Caccia</th><th class="ta-r">Scudi</th>
      <th class="ta-r">Combat</th><th class="ta-r">Warp</th><th class="ta-r">Netto</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($catalog as $t):
      $net  = max(0, (int) $t['base_cost'] - $trade_in);
      $mine = $t['ckey'] === $ship['type_key'];
    ?>
      <tr<?= $mine ? ' class="row-current"' : '' ?>>
        <td class="ship-cell">
          <span class="ship-mark"><?= partial('ship_art', ['type' => $t['ckey'], 'size' => 30, 'title' => $t['name']]) ?></span>
          <strong><?= e($t['name']) ?></strong><?= $mine ? ' <span class="pill mut">attuale</span>' : '' ?>
        </td>
        <td class="ta-r" data-th="Stive"><?= (int) $t['base_holds'] ?>–<?= (int) $t['max_holds'] ?></td>
        <td class="ta-r" data-th="Caccia"><?= number_format((int) $t['max_fighters'], 0, ',', '.') ?></td>
        <td class="ta-r" data-th="Scudi"><?= number_format((int) $t['max_shields'], 0, ',', '.') ?></td>
        <td class="ta-r" data-th="Combat"><?= number_format((float) $t['combat_rating'], 1, ',', '.') ?></td>
        <td class="ta-r" data-th="Warp"><?= (int) $t['turns_per_warp'] ?></td>
        <td class="ta-r nowrap" data-th="Netto"><?= number_format($net, 0, ',', '.') ?></td>
        <td class="ta-r">
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
  </div>
</section>
