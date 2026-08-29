<?php
/** @var array<string,mixed> $player */
/** @var array<string,mixed> $ship */
/** @var array<string,mixed> $p */
/** @var bool $own */
/** @var bool $here */
/** @var array{level:int,costs:array}|null $next */
/** @var int $used */
$total = (int) $p['col_ore'] + (int) $p['col_org'] + (int) $p['col_equ'] + (int) $p['col_idle'];
$u = url('/gioco/pianeta/' . (int) $p['id']);
?>
<section class="statusbar">
  <div><span class="k">Crediti</span><span class="v"><?= number_format((int) $player['credits'], 0, ',', '.') ?></span></div>
  <div><span class="k">Stive</span><span class="v"><?= $used ?>/<?= (int) $ship['holds_total'] ?></span></div>
  <div><span class="k">Coloni a bordo</span><span class="v"><?= number_format((int) $ship['hold_colonists'], 0, ',', '.') ?></span></div>
  <div><span class="k">Caccia</span><span class="v"><?= number_format((int) $ship['fighters'], 0, ',', '.') ?></span></div>
  <div><a href="<?= e(url('/gioco')) ?>">← Plancia</a></div>
</section>

<section class="panel">
  <header class="sector-head">
    <h1><?= e($p['name']) ?></h1>
    <span class="tag">Tipo <?= e($p['type_key']) ?> · <?= e($p['type_name']) ?></span>
    <?php if ($own): ?><span class="tag fed">tuo</span><?php endif; ?>
  </header>
  <p class="hint"><?= e($p['type_descr']) ?></p>
  <p>Proprietario: <strong><?= e($p['owner_handle'] ?? '—') ?></strong><?= $p['corp_name'] ? ' — corp ' . e($p['corp_name']) . ' [' . e($p['corp_tag']) . ']' : '' ?>
     · Settore <?= (int) $p['sector_id'] ?><?= $here ? '' : ' <span class="pill warn">non sei qui</span>' ?></p>

  <div class="grid tight">
    <div class="card"><span class="k">Coloni</span><span class="v"><?= number_format($total, 0, ',', '.') ?>/<?= number_format((int) $p['max_col'], 0, ',', '.') ?></span></div>
    <div class="card"><span class="k">Citadel</span><span class="v">liv. <?= (int) $p['citadel_level'] ?><?= $p['citadel_upgrade_to'] ? ' → ' . (int) $p['citadel_upgrade_to'] : '' ?></span></div>
    <div class="card"><span class="k">Quasar</span><span class="v">liv. <?= (int) $p['quasar_level'] ?></span></div>
    <div class="card"><span class="k">Guarnigione</span><span class="v"><?= number_format((int) $p['fighters'], 0, ',', '.') ?></span></div>
    <div class="card"><span class="k">Tesoreria</span><span class="v"><?= number_format((int) $p['credits'], 0, ',', '.') ?></span></div>
  </div>
  <?php if ($p['citadel_ready_at']): ?>
    <p class="hint">Citadel liv. <?= (int) $p['citadel_upgrade_to'] ?> pronta il <?= e($p['citadel_ready_at']) ?>.</p>
  <?php endif; ?>

  <h2>Coloni e produzione</h2>
  <table class="tbl">
    <thead><tr><th>Categoria</th><th class="ta-r">Coloni</th><th class="ta-r">Magazzino</th><th>Produzione</th></tr></thead>
    <tbody>
      <?php foreach ([
        ['ore', 'Minerale', 'col_ore', 'stock_ore', 'prod_ore'],
        ['org', 'Organico', 'col_org', 'stock_org', 'prod_org'],
        ['equ', 'Equipaggiamento', 'col_equ', 'stock_equ', 'prod_equ'],
      ] as [$b, $lbl, $cc, $sc, $pc]): ?>
      <tr>
        <td><?= $lbl ?></td>
        <td class="ta-r"><?= number_format((int) $p[$cc], 0, ',', '.') ?></td>
        <td class="ta-r"><?= number_format((int) $p[$sc], 0, ',', '.') ?></td>
        <td><?= number_format((float) $p[$pc], 2, ',', '.') ?>/colono/h</td>
      </tr>
      <?php endforeach; ?>
      <tr><td>Inattivi</td><td class="ta-r"><?= number_format((int) $p['col_idle'], 0, ',', '.') ?></td><td colspan="2"></td></tr>
    </tbody>
  </table>

  <?php if ($own && $here): ?>
  <div class="upg-grid">
    <form method="post" action="<?= e($u . '/coloni') ?>" class="row">
      <?= csrf_field() ?>
      <label>Coloni <select name="dir"><option value="down">sbarca sul pianeta</option><option value="up">imbarca</option></select></label>
      <label>Categoria <select name="bucket"><option value="idle">inattivi</option><option value="ore">minerale</option><option value="org">organico</option><option value="equ">equip.</option></select></label>
      <label>Qta <input type="number" name="qty" min="1" value="1000" class="qty"></label>
      <button class="btn xs" type="submit">Trasferisci</button>
    </form>
    <form method="post" action="<?= e($u . '/assegna') ?>" class="row">
      <?= csrf_field() ?>
      <label>Riassegna da <select name="from"><option value="idle">inattivi</option><option value="ore">minerale</option><option value="org">organico</option><option value="equ">equip.</option></select></label>
      <label>a <select name="to"><option value="ore">minerale</option><option value="org">organico</option><option value="equ">equip.</option><option value="idle">inattivi</option></select></label>
      <label>Qta <input type="number" name="qty" min="1" value="1000" class="qty"></label>
      <button class="btn xs" type="submit">Assegna</button>
    </form>
    <form method="post" action="<?= e($u . '/risorse') ?>" class="row">
      <?= csrf_field() ?>
      <label>Risorse <select name="dir"><option value="load">carica sulla nave</option><option value="unload">scarica sul pianeta</option></select></label>
      <label>Merce <select name="commodity"><option value="ore">minerale</option><option value="organics">organico</option><option value="equipment">equip.</option></select></label>
      <label>Qta <input type="number" name="qty" min="1" value="100" class="qty"></label>
      <button class="btn xs" type="submit">Trasferisci</button>
    </form>
    <?php if ((int) $p['citadel_level'] >= 1): ?>
    <form method="post" action="<?= e($u . '/tesoreria') ?>" class="row">
      <?= csrf_field() ?>
      <label>Tesoreria <select name="dir"><option value="deposit">deposita</option><option value="withdraw">preleva</option></select></label>
      <label>Cr <input type="number" name="amount" min="1" value="10000" class="qty"></label>
      <button class="btn xs" type="submit">OK</button>
    </form>
    <?php endif; ?>
    <form method="post" action="<?= e($u . '/guarnigione') ?>" class="row">
      <?= csrf_field() ?>
      <label>Caccia <select name="dir"><option value="garrison">metti a guarnigione</option><option value="recall">richiama a bordo</option></select></label>
      <label>Qta <input type="number" name="qty" min="1" value="1000" class="qty"></label>
      <button class="btn xs" type="submit">OK</button>
    </form>
  </div>

  <h2>Citadel</h2>
  <?php if ($next === null): ?>
    <p class="hint">Citadel al massimo o non costruibile su questo tipo di pianeta.</p>
  <?php elseif ($p['citadel_upgrade_to']): ?>
    <p class="hint">Potenziamento gia' in corso.</p>
  <?php else: $c = $next['costs']; ?>
    <p>Prossimo livello <strong><?= (int) $next['level'] ?></strong>: <?= number_format($c['col'], 0, ',', '.') ?> coloni,
       <?= number_format($c['ore'], 0, ',', '.') ?> minerale, <?= number_format($c['equ'], 0, ',', '.') ?> equip.,
       <?= number_format($c['cr'], 0, ',', '.') ?> cr, ~<?= (int) $c['hours'] ?>h.</p>
    <form method="post" action="<?= e($u . '/citadel') ?>" class="inline">
      <?= csrf_field() ?><button class="btn xs" type="submit">Avvia potenziamento</button>
    </form>
  <?php endif; ?>
  <?php if ((int) $p['citadel_level'] >= 3): ?>
    <form method="post" action="<?= e($u . '/quasar') ?>" class="inline" style="margin-left:.5rem">
      <?= csrf_field() ?><button class="btn xs" type="submit">Costruisci/potenzia Quasar</button>
    </form>
  <?php endif; ?>

  <?php elseif ($here && !$own): ?>
  <h2>Assalto</h2>
  <form method="post" action="<?= e($u . '/attacca') ?>" class="row"
        onsubmit="return confirm('Attaccare il pianeta? Colpo pesante all\'allineamento.')">
    <?= csrf_field() ?>
    <label>Caccia <input type="number" name="fighters" min="0" value="0" class="qty" title="0 = tutti"></label>
    <label><input type="checkbox" name="bombard" value="1"> bombarda i coloni</label>
    <button class="btn xs danger" type="submit">Attacca</button>
  </form>
  <?php endif; ?>
</section>
