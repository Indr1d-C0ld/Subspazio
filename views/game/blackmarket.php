<?php
/** @var array<string,mixed> $player */
/** @var array<string,mixed> $ship */
/** @var bool $available */
/** @var list<array{item:string,name:string,price:int}> $catalog */
$U = e(url('/gioco/mercato-nero'));
?>
<section class="statusbar">
  <div><span class="k">Crediti</span><span class="v"><?= number_format((int) $player['credits'], 0, ',', '.') ?></span></div>
  <div><span class="k">Allineamento</span><span class="v"><?= e(\App\Game\Ranks::alignmentLabel((int) $player['alignment'])) ?> (<?= (int) $player['alignment'] ?>)</span></div>
  <div><span class="k">Taglia</span><span class="v"><?= number_format((int) $player['bounty'], 0, ',', '.') ?></span></div>
  <div><a href="<?= e(url('/gioco')) ?>">← Plancia</a></div>
</section>

<section class="panel">
  <h1>Mercato nero</h1>
  <?php if (!$available): ?>
    <p class="hint">Nessun contatto in questo settore. Il contrabbandiere batte gli attracchi (StarDock o settori con porto).</p>
  <?php else: ?>
    <p class="hint">Ogni affare qui costa allineamento. Nessuna domanda.</p>

    <h2>Piazza merce (premio sul prezzo equo)</h2>
    <form method="post" action="<?= $U ?>" class="row">
      <?= csrf_field() ?><input type="hidden" name="op" value="sell">
      <label>Merce <select name="commodity"><option value="ore">Minerale</option><option value="organics">Organico</option><option value="equipment">Equipaggiamento</option></select></label>
      <label>Qta <input type="number" name="qty" min="1" value="10" class="qty"></label>
      <button class="btn xs" type="submit">Vendi</button>
    </form>

    <h2>Hardware scontato</h2>
    <div class="upg-grid">
      <?php foreach ($catalog as $c): ?>
        <form method="post" action="<?= $U ?>" class="row">
          <?= csrf_field() ?><input type="hidden" name="op" value="buy"><input type="hidden" name="item" value="<?= e($c['item']) ?>">
          <label><?= e($c['name']) ?> — <?= number_format($c['price'], 0, ',', '.') ?> cr
            <?php if ($c['item'] !== 'cloak'): ?><input type="number" name="qty" min="1" value="1" class="qty"><?php endif; ?>
          </label>
          <button class="btn xs" type="submit">Compra</button>
        </form>
      <?php endforeach; ?>
    </div>

    <?php if ((int) $player['bounty'] > 0): ?>
    <h2>Ripulisci la taglia</h2>
    <form method="post" action="<?= $U ?>" class="row">
      <?= csrf_field() ?><input type="hidden" name="op" value="bounty">
      <button class="btn xs danger" type="submit">Paga per cancellare <?= number_format((int) $player['bounty'], 0, ',', '.') ?> cr di taglia</button>
    </form>
    <?php endif; ?>
  <?php endif; ?>
</section>
