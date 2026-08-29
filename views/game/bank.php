<?php
/** @var array<string,mixed> $player */
/** @var array{player_id:int,balance:int,last_interest_at:string} $account */
/** @var float $rate */
?>
<section class="panel narrow">
  <h1>Banca Intergalattica</h1>
  <p class="hint">Interesse composto <?= number_format($rate, 2, ',', '.') ?>% al giorno. Operativa solo allo StarDock.</p>

  <div class="grid tight">
    <div class="card"><span class="k">Crediti a bordo</span><span class="v"><?= number_format((int) $player['credits'], 0, ',', '.') ?></span></div>
    <div class="card"><span class="k">Saldo in banca</span><span class="v"><?= number_format((int) $account['balance'], 0, ',', '.') ?></span></div>
  </div>

  <form method="post" action="<?= e(url('/gioco/banca/deposita')) ?>" class="row">
    <?= csrf_field() ?>
    <label>Deposita <input type="number" name="amount" min="1" value="0"></label>
    <button class="btn xs" type="submit">Deposita</button>
  </form>
  <form method="post" action="<?= e(url('/gioco/banca/preleva')) ?>" class="row">
    <?= csrf_field() ?>
    <label>Preleva <input type="number" name="amount" min="1" value="0"></label>
    <button class="btn xs ghost" type="submit">Preleva</button>
  </form>

  <p><a class="btn ghost" href="<?= e(url('/gioco')) ?>">← Plancia</a></p>
</section>
