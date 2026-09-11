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
  <h1><span class="sec-ic">🏆</span> Classifica comandanti<?= partial('help', ['key' => 'classifica.comandanti']) ?></h1>
  <div class="table-wrap">
  <table class="tbl rows">
    <thead><tr><th class="ta-r">#</th><th>Comandante</th><th>Corp</th><th>Grado</th><th class="ta-r">Rating</th><th class="ta-r">Exp</th><th class="ta-r">Kill</th><th class="ta-r">Morti</th><th class="ta-r">Pianeti</th><th>Allineamento</th></tr></thead>
    <tbody>
    <?php foreach ($players as $i => $r): ?>
      <tr<?= $r['handle'] === $player['handle'] ? ' class="row-current"' : '' ?>>
        <td class="ta-r ld-pos" data-th="Posizione"><?= $i + 1 ?></td>
        <td class="rowtitle">
        <span class="ld-cmd">
          <?php if (!empty($r['has_avatar'])): ?>
            <?= partial('media_hover', ['id' => (int) $r['pid'], 'kind' => 'avatar', 'size' => 28, 'frame' => $r['color'] ?? null, 'alt' => $r['handle']]) ?>
          <?php else: ?>
            <?= partial('crest', ['crest' => $r['crest'] ?? null, 'color' => $r['color'] ?? null, 'size' => 22]) ?>
          <?php endif; ?>
          <strong style="color:<?= e($r['color'] ?? '') ?>"><?= e($r['handle']) ?></strong>
          <?php if (!empty($r['has_logo'])): ?>
            <?= partial('media_hover', ['id' => (int) $r['pid'], 'kind' => 'logo', 'size' => 28, 'alt' => 'logo di ' . $r['handle']]) ?>
          <?php endif; ?>
          <?php if (!empty($r['ship_key'])): ?>
            <?= partial('ship_art', ['type' => $r['ship_key'], 'size' => 30, 'color' => $r['color'] ?? null, 'title' => $r['ship_type'] ?? '']) ?>
          <?php endif; ?>
        </span></td>
        <td data-th="Corp"><?= $r['corp'] ? e($r['corp']) : '—' ?></td>
        <td data-th="Grado"><?= e($r['rank']) ?></td>
        <td class="ta-r" data-th="Rating"><?= number_format($r['rating'], 0, ',', '.') ?></td>
        <td class="ta-r" data-th="Exp"><?= number_format($r['experience'], 0, ',', '.') ?></td>
        <td class="ta-r" data-th="Kill"><?= (int) $r['kills'] ?></td>
        <td class="ta-r" data-th="Morti"><?= (int) $r['deaths'] ?></td>
        <td class="ta-r" data-th="Pianeti"><?= (int) $r['planets'] ?></td>
        <td data-th="Allineamento"><?= e($r['alignment']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($players === []): ?><tr><td colspan="10" class="hint">Nessun comandante in classifica.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</section>

<section class="panel">
  <h1><span class="sec-ic">🏛️</span> Classifica corporazioni<?= partial('help', ['key' => 'classifica.corp']) ?></h1>
  <div class="table-wrap">
  <table class="tbl rows">
    <thead><tr><th class="ta-r">#</th><th>Corporazione</th><th class="ta-r">Membri</th><th class="ta-r">Rating</th><th class="ta-r">Cassa</th><th class="ta-r">Pianeti</th></tr></thead>
    <tbody>
    <?php foreach ($corps as $i => $c): ?>
      <tr>
        <td class="ta-r ld-pos" data-th="Posizione"><?= $i + 1 ?></td>
        <td class="rowtitle"><strong><?= e($c['name']) ?></strong> [<?= e($c['tag']) ?>]</td>
        <td class="ta-r" data-th="Membri"><?= (int) $c['members'] ?></td>
        <td class="ta-r" data-th="Rating"><?= number_format($c['rating'], 0, ',', '.') ?></td>
        <td class="ta-r" data-th="Cassa"><?= number_format($c['treasury'], 0, ',', '.') ?></td>
        <td class="ta-r" data-th="Pianeti"><?= (int) $c['planets'] ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($corps === []): ?><tr><td colspan="6" class="hint">Nessuna corporazione.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</section>
