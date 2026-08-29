<?php
/** @var array<string,mixed> $player */
/** @var list<array<string,mixed>> $players */
/** @var list<array<string,mixed>> $corps */
?>
<section class="statusbar">
  <div><span class="k">Comandante</span><span class="v"><?= e($player['handle']) ?></span></div>
  <div><span class="k">Il tuo rating</span><span class="v"><?= number_format((int) ($player['rating'] ?? 0), 0, ',', '.') ?></span></div>
  <div><a href="<?= e(url('/gioco')) ?>">← Plancia</a></div>
</section>

<section class="panel">
  <h1>Classifica comandanti</h1>
  <table class="tbl">
    <thead><tr><th>#</th><th>Comandante</th><th>Corp</th><th>Grado</th><th class="ta-r">Rating</th><th class="ta-r">Exp</th><th class="ta-r">Kill</th><th class="ta-r">Morti</th><th class="ta-r">Pianeti</th><th>Allineamento</th></tr></thead>
    <tbody>
    <?php foreach ($players as $i => $r): ?>
      <tr<?= $r['handle'] === $player['handle'] ? ' class="row-current"' : '' ?>>
        <td><?= $i + 1 ?></td>
        <td><strong><?= e($r['handle']) ?></strong></td>
        <td><?= $r['corp'] ? e($r['corp']) : '—' ?></td>
        <td><?= e($r['rank']) ?></td>
        <td class="ta-r"><?= number_format($r['rating'], 0, ',', '.') ?></td>
        <td class="ta-r"><?= number_format($r['experience'], 0, ',', '.') ?></td>
        <td class="ta-r"><?= (int) $r['kills'] ?></td>
        <td class="ta-r"><?= (int) $r['deaths'] ?></td>
        <td class="ta-r"><?= (int) $r['planets'] ?></td>
        <td><?= e($r['alignment']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($players === []): ?><tr><td colspan="10" class="hint">Nessun comandante in classifica.</td></tr><?php endif; ?>
    </tbody>
  </table>
</section>

<section class="panel">
  <h1>Classifica corporazioni</h1>
  <table class="tbl">
    <thead><tr><th>#</th><th>Corporazione</th><th class="ta-r">Membri</th><th class="ta-r">Rating</th><th class="ta-r">Cassa</th><th class="ta-r">Pianeti</th></tr></thead>
    <tbody>
    <?php foreach ($corps as $i => $c): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td><strong><?= e($c['name']) ?></strong> [<?= e($c['tag']) ?>]</td>
        <td class="ta-r"><?= (int) $c['members'] ?></td>
        <td class="ta-r"><?= number_format($c['rating'], 0, ',', '.') ?></td>
        <td class="ta-r"><?= number_format($c['treasury'], 0, ',', '.') ?></td>
        <td class="ta-r"><?= (int) $c['planets'] ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($corps === []): ?><tr><td colspan="6" class="hint">Nessuna corporazione.</td></tr><?php endif; ?>
    </tbody>
  </table>
</section>
