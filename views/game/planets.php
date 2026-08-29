<?php
/** @var array<string,mixed> $player */
/** @var array<string,mixed> $ship */
/** @var list<array<string,mixed>> $planets */
?>
<section class="statusbar">
  <div><span class="k">Settore</span><span class="v"><?= (int) $player['sector_id'] ?></span></div>
  <div><span class="k">Genesi a bordo</span><span class="v"><?= (int) $ship['genesis'] ?></span></div>
  <div><span class="k">Coloni a bordo</span><span class="v"><?= number_format((int) $ship['hold_colonists'], 0, ',', '.') ?></span></div>
  <div><a href="<?= e(url('/gioco')) ?>">← Plancia</a></div>
</section>

<section class="panel">
  <h1>Pianeti — settore <?= (int) $player['sector_id'] ?></h1>

  <?php if ($planets === []): ?>
    <p class="hint">Nessun pianeta in questo settore.</p>
  <?php else: ?>
    <table class="tbl">
      <thead><tr><th>Nome</th><th>Tipo</th><th>Proprietario</th><th>Citadel</th><th>Quasar</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($planets as $pl): ?>
        <tr>
          <td><strong><?= e($pl['name']) ?></strong><?= $pl['mine'] ? ' <span class="pill ok">tuo</span>' : '' ?></td>
          <td><?= e($pl['type']) ?> — <?= e($pl['type_name']) ?></td>
          <td><?= e($pl['owner'] ?? '—') ?><?= $pl['corp'] ? ' [' . e($pl['corp']) . ']' : '' ?></td>
          <td><?= (int) $pl['citadel'] ?></td>
          <td><?= (int) $pl['quasar'] ?></td>
          <td><a class="btn xs" href="<?= e(url('/gioco/pianeta/' . $pl['id'])) ?>"><?= $pl['mine'] ? 'Gestisci' : 'Ispeziona' ?></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if ((int) $ship['genesis'] > 0): ?>
    <form method="post" action="<?= e(url('/gioco/genesi')) ?>" class="row" style="margin-top:1rem"
          onsubmit="return confirm('Lanciare un siluro Genesi qui?')">
      <?= csrf_field() ?>
      <button class="btn" type="submit">Lancia siluro Genesi (crea un pianeta)</button>
    </form>
  <?php else: ?>
    <p class="hint">Nessun siluro Genesi a bordo — acquistane al <a href="<?= e(url('/gioco/cantiere')) ?>">Cantiere StarDock</a>.</p>
  <?php endif; ?>

  <?php if ((int) $player['sector_id'] === 1): ?>
    <form method="post" action="<?= e(url('/gioco/coloni/carica')) ?>" class="row">
      <?= csrf_field() ?>
      <label>Imbarca coloni da Terra <input type="number" name="qty" min="1" value="1000" class="qty"></label>
      <button class="btn xs" type="submit">Carica</button>
    </form>
  <?php endif; ?>
</section>
