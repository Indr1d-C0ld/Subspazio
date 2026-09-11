<?php
/** @var array<string,mixed> $player */
/** @var list<array<string,mixed>> $rows */
?>
<section class="statusbar">
  <div><span class="k">Comandante</span><span class="v"><?= e($player['handle']) ?></span></div>
  <div><a href="<?= e(url('/gioco/rotte')) ?>">Rotte</a></div>
  <div><a href="<?= e(url('/gioco')) ?>">← Plancia</a></div>
</section>

<section class="panel">
  <h1><span class="sec-ic">⚔️</span> Registro battaglie</h1>
  <?php if ($rows === []): ?>
    <p class="hint">Nessuno scontro registrato.</p>
  <?php else: ?>
  <div class="table-wrap">
  <table class="tbl rows">
    <thead><tr><th>#</th><th>Quando</th><th>Tipo</th><th>Settore</th><th>Ruolo</th><th>Avversario</th><th>Esito</th><th class="ta-r">Round</th><th class="ta-r">Persi</th><th class="ta-r">Bottino</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td class="rowtitle">#<?= (int) $r['id'] ?> · <?= e(fmt_dt($r['at'])) ?>
          <span class="pill <?= str_contains($r['outcome'], 'vittoria') || str_contains($r['outcome'], 'difesa') ? 'ok' : (str_contains($r['outcome'], 'distrutto') ? 'err' : 'mut') ?>"><?= e($r['outcome']) ?></span></td>
        <td data-th="Tipo"><?= e($r['kind']) ?></td>
        <td data-th="Settore"><?= (int) $r['sector'] ?></td>
        <td data-th="Ruolo"><?= e($r['role']) ?></td>
        <td data-th="Avversario"><?= e($r['opponent']) ?></td>
        <td class="ta-r" data-th="Round"><?= (int) $r['rounds'] ?></td>
        <td class="ta-r" data-th="Persi"><?= $r['role'] === 'attaccante' ? (int) $r['att_lost'] : (int) $r['def_lost'] ?></td>
        <td class="ta-r" data-th="Bottino"><?= $r['loot'] ? number_format($r['loot'], 0, ',', '.') : '—' ?></td>
        <td><?php if ($r['replayable']): ?><a class="btn xs" href="<?= e(url('/gioco/battaglia/' . $r['id'])) ?>">Replay</a><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</section>
