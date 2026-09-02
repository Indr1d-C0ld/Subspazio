<?php
/** @var array<string,mixed> $player */
/** @var bool $at_dock */
/** @var list<array<string,mixed>> $factions */
/** @var array<string,int> $rep */
/** @var list<array<string,mixed>> $offers */
/** @var list<array<string,mixed>> $log */
/** @var string|null $blocked */

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
    <?php if ($at_dock): ?>
      <form method="post" action="<?= e(url('/gioco/fazioni/ammenda')) ?>" class="inline">
        <?= csrf_field() ?><button class="btn xs" type="submit">Paga l'ammenda (<?= number_format(\App\Game\GameConfig::int('faction.amnesty_cost', 15000), 0, ',', '.') ?> cr)</button>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php foreach ($factions as $f): $v = (int) ($rep[$f['ckey']] ?? 0);
  $tier = Faction::tier($v);
  $pct = ($v - $min) / max(1, $max - $min) * 100; ?>
<section class="panel faction-panel" style="--fac: <?= e($f['color']) ?>">
  <h1><?= e($f['name']) ?> <span class="pill mut"><?= e(Faction::TIER_LABEL[$tier]) ?></span></h1>
  <p class="hint"><?= e($f['blurb']) ?></p>
  <div class="rep-bar"><span style="width: <?= (int) round($pct) ?>%"></span><b style="left: <?= (int) round((0 - $min) / max(1, $max - $min) * 100) ?>%"></b></div>
  <p class="mut"><?= $v > 0 ? '+' : '' ?><?= $v ?> / <?= $max ?></p>

  <?php $fo = $offersByF[$f['ckey']] ?? []; if ($fo !== []): ?>
    <h2>Emporio</h2>
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
  <h2>Movimenti di reputazione</h2>
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
