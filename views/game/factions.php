<?php
/** @var array<string,mixed> $player */
/** @var bool $at_dock */
/** @var list<array<string,mixed>> $factions */
/** @var array<string,int> $rep */
/** @var list<array<string,mixed>> $offers */
/** @var list<array<string,mixed>> $log */
/** @var string|null $blocked */
/** @var bool $bandoRep */
/** @var array<string,mixed> $legge */

use App\Game\Faction;

$offersByF = [];
foreach ($offers as $o) {
    $offersByF[$o['faction']][] = $o;
}
$min = \App\Game\GameConfig::int('faction.min', -100);
$max = \App\Game\GameConfig::int('faction.max', 100);
?>
<section class="statusbar">
  <div><span class="k">Crediti</span><span class="v"><?= number_format((int) $player['credits'], 0, ',', '.') ?></span></div>
  <?php foreach ($factions as $f): $v = (int) ($rep[$f['ckey']] ?? 0); ?>
    <div><span class="k"><?= e($f['name']) ?></span><span class="v"><?= e(Faction::TIER_LABEL[Faction::tier($v)]) ?> (<?= $v > 0 ? '+' : '' ?><?= $v ?>)</span></div>
  <?php endforeach; ?>
  <div><a href="<?= e(url('/gioco')) ?>">← Plancia</a></div>
</section>

<?php if ($blocked !== null): ?>
  <div class="alert err">
    <?= e($blocked) ?>
    <?php if ($at_dock && $bandoRep): ?>
      <form method="post" action="<?= e(url('/gioco/fazioni/ammenda')) ?>" class="inline">
        <?= csrf_field() ?><button class="btn xs" type="submit">Paga l'ammenda (<?= number_format(\App\Game\GameConfig::int('faction.amnesty_cost', 15000), 0, ',', '.') ?> cr)</button>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php $lg = (int) $legge['grado']; ?>
<section class="panel legge-panel legge-<?= $lg ?>" id="legge">
  <h2><span class="sec-ic">⚖️</span> Legge federale<?= partial('help', ['key' => 'fazioni.legge']) ?></h2>
  <p class="legge-stato"><span class="pill legge-grado legge-<?= $lg ?>"><?= e(\App\Game\Legge::nome($lg)) ?></span>
    notorietà <strong><?= number_format((float) $legge['punti'], 1, ',', '.') ?></strong>
    <?php if ((float) $legge['ore'] > 0): ?><span class="mut">· torni sotto la soglia di ricercato fra circa <?= (int) ceil((float) $legge['ore']) ?> ore</span><?php endif; ?></p>
  <p class="hint"><?= e(\App\Game\Legge::RIASSUNTI[$lg]) ?></p>
  <?php if ((int) $legge['taglia'] > 0): ?>
    <p>Taglia federale sulla tua testa: <strong><?= number_format((int) $legge['taglia'], 0, ',', '.') ?> cr</strong>. Chi ti abbatte la incassa, confiscata a te: prima i crediti a bordo, poi la banca.</p>
  <?php endif; ?>
  <?php if ((int) $legge['recenti'] > 0): ?>
    <p class="hint">Recidiva: <?= (int) $legge['recenti'] ?> crimini nelle ultime <?= \App\Game\GameConfig::int('legge.recidiva_ore', 72) ?> ore, ognuno pesa sul successivo.</p>
  <?php endif; ?>
  <?php if ((int) $legge['costo'] > 0): ?>
    <form method="post" action="<?= e(url('/gioco/fazioni/legge')) ?>" class="inline"
          data-confirm="Pagare <?= number_format((int) $legge['costo'], 0, ',', '.') ?> cr per azzerare la notorietà?">
      <?= csrf_field() ?><button class="btn xs" type="submit">Paga l'ammenda e chiudi il fascicolo (<?= number_format((int) $legge['costo'], 0, ',', '.') ?> cr)</button>
    </form>
  <?php endif; ?>
  <?php if ($legge['fedina'] !== []): ?>
    <details class="legge-fedina"><summary>Fedina penale</summary>
      <ul class="note-list">
        <?php foreach ($legge['fedina'] as $c): ?>
          <li><?= e(\App\Game\Legge::ETICHETTE[$c['kind']] ?? $c['kind']) ?> · +<?= number_format((float) $c['punti'], 1, ',', '.') ?>
            <span class="mut"><?= $c['sector_id'] ? 'settore ' . (int) $c['sector_id'] . ' · ' : '' ?><?= e(date('d/m H:i', strtotime((string) $c['created_at']))) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </details>
  <?php endif; ?>
</section>

<?php foreach ($factions as $f): $v = (int) ($rep[$f['ckey']] ?? 0);
  $tier = Faction::tier($v);
  $pct = ($v - $min) / max(1, $max - $min) * 100; ?>
<section class="panel faction-panel" style="--fac: <?= e($f['color']) ?>">
  <h1><span class="sec-ic">🚩</span> <?= e($f['name']) ?> <span class="pill mut"><?= e(Faction::TIER_LABEL[$tier]) ?></span></h1>
  <p class="hint"><?= e($f['blurb']) ?></p>
  <div class="rep-bar"><span style="width: <?= (int) round($pct) ?>%"></span><b style="left: <?= (int) round((0 - $min) / max(1, $max - $min) * 100) ?>%"></b></div>
  <p class="mut"><?= $v > 0 ? '+' : '' ?><?= $v ?> / <?= $max ?></p>

  <?php $fo = $offersByF[$f['ckey']] ?? []; if ($fo !== []): ?>
    <h2><span class="sec-ic">🛒</span> Emporio<?= partial('help', ['key' => 'fazioni.emporio']) ?></h2>
    <ul class="mod-list">
      <?php foreach ($fo as $o): ?>
        <li class="fac-offer">
          <span class="rarity rarity-<?= e($o['rarity']) ?>"><?= e($o['item_name']) ?></span>
          <span class="mut"><?= e($o['label']) ?> · <?= e(Faction::TIER_LABEL[$o['min_tier']]) ?>+ · <?= number_format((int) $o['price'], 0, ',', '.') ?> cr</span>
          <?php if ($o['unlocked'] && $at_dock): ?>
            <form method="post" action="<?= e(url('/gioco/fazioni/compra')) ?>" class="inline">
              <?= csrf_field() ?><input type="hidden" name="offer" value="<?= (int) $o['id'] ?>">
              <button class="btn xs" type="submit"<?= (int) $player['credits'] >= (int) $o['price'] ? '' : ' disabled' ?>>Compra</button>
            </form>
          <?php elseif (!$o['unlocked']): ?>
            <span class="pill mut">bloccato</span>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php endforeach; ?>

<?php if ($log !== []): ?>
<section class="panel">
  <h2><span class="sec-ic">📈</span> Movimenti di reputazione<?= partial('help', ['key' => 'fazioni.reputazione']) ?></h2>
  <table class="tbl compact">
    <thead><tr><th>Quando</th><th>Fazione</th><th class="ta-r">Δ</th><th>Motivo</th></tr></thead>
    <tbody>
    <?php foreach ($log as $r): ?>
      <tr>
        <td class="nowrap"><?= e(fmt_dt($r['created_at'])) ?></td>
        <td><?= e($r['faction']) ?></td>
        <td class="ta-r <?= (int) $r['delta'] < 0 ? 'mut' : '' ?>"><?= (int) $r['delta'] > 0 ? '+' : '' ?><?= (int) $r['delta'] ?></td>
        <td class="mut"><?= e($r['reason']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>
