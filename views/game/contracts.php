<?php
/** @var array<string,mixed> $player */
/** @var list<array<string,mixed>> $board */
/** @var list<array<string,mixed>> $mine */
$U = e(url('/gioco/contratti'));
?>
<section class="statusbar">
  <div><span class="k">Crediti</span><span class="v"><?= number_format((int) $player['credits'], 0, ',', '.') ?></span></div>
  <div><span class="k">Settore</span><span class="v"><?= (int) $player['sector_id'] ?></span></div>
  <div><a href="<?= e(url('/gioco')) ?>">← Plancia</a></div>
</section>

<div class="game-grid">
  <section class="panel">
    <h1>Bacheca contratti</h1>
    <?php if ($board === []): ?><p class="hint">Nessun contratto aperto.</p><?php else: ?>
    <table class="tbl compact">
      <thead><tr><th>#</th><th>Tipo</th><th>Dettagli</th><th class="ta-r">Ricompensa</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($board as $c): ?>
        <tr>
          <td><?= (int) $c['id'] ?></td>
          <td><?= e($c['kind']) ?></td>
          <td>
            <?php if ($c['kind'] === 'bounty'): ?>
              Taglia su <strong><?= e($c['target'] ?? '?') ?></strong> · da <?= e($c['issuer']) ?>
            <?php else: ?>
              <?= (int) $c['qty'] ?> <?= e(\App\Game\Economy::label((string) $c['commodity'])) ?> → settore <strong><?= (int) $c['sector_id'] ?></strong> · da <?= e($c['issuer']) ?>
            <?php endif; ?>
          </td>
          <td class="ta-r"><?= number_format((int) $c['reward'], 0, ',', '.') ?></td>
          <td>
            <?php if ($c['kind'] === 'delivery' && (int) $c['sector_id'] === (int) $player['sector_id']): ?>
              <form method="post" action="<?= $U ?>" class="inline">
                <?= csrf_field() ?><input type="hidden" name="op" value="deliver"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                <button class="btn xs" type="submit">Consegna</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

    <h2>Pubblica un contratto</h2>
    <form method="post" action="<?= $U ?>" class="row">
      <?= csrf_field() ?><input type="hidden" name="op" value="bounty">
      <label>Taglia su <input type="text" name="target" placeholder="handle"></label>
      <label>Ricompensa <input type="number" name="reward" min="500" value="1000" class="qty"></label>
      <button class="btn xs danger" type="submit">Metti taglia</button>
    </form>
    <form method="post" action="<?= $U ?>" class="row">
      <?= csrf_field() ?><input type="hidden" name="op" value="delivery">
      <label>Consegna <select name="commodity"><option value="ore">Minerale</option><option value="organics">Organico</option><option value="equipment">Equip.</option></select></label>
      <label>Qta <input type="number" name="qty" min="1" value="100" class="qty"></label>
      <label>al settore <input type="number" name="sector" min="1" value="1" class="qty"></label>
      <label>Ricompensa <input type="number" name="reward" min="500" value="2000" class="qty"></label>
      <button class="btn xs" type="submit">Pubblica</button>
    </form>
  </section>

  <section class="panel">
    <h1>I miei contratti</h1>
    <?php if ($mine === []): ?><p class="hint">Nessuno.</p><?php else: ?>
    <table class="tbl compact">
      <thead><tr><th>#</th><th>Tipo</th><th>Stato</th><th class="ta-r">Ricompensa</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($mine as $c): ?>
        <tr>
          <td><?= (int) $c['id'] ?></td>
          <td><?= e($c['kind']) ?><?= (int) $c['issuer_player_id'] === (int) $player['id'] ? ' (emesso)' : ' (riscosso)' ?></td>
          <td><span class="pill <?= $c['status'] === 'open' ? 'warn' : ($c['status'] === 'claimed' ? 'ok' : 'mut') ?>"><?= e($c['status']) ?></span></td>
          <td class="ta-r"><?= number_format((int) $c['reward'], 0, ',', '.') ?></td>
          <td>
            <?php if ($c['status'] === 'open' && (int) $c['issuer_player_id'] === (int) $player['id']): ?>
              <form method="post" action="<?= $U ?>" class="inline">
                <?= csrf_field() ?><input type="hidden" name="op" value="cancel"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                <button class="btn xs ghost" type="submit">Annulla</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </section>
</div>
