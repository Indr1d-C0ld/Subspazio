<?php
/** @var array<string,mixed> $player */
/** @var array<string,mixed> $ship */
/** @var bool $at_dock */
/** @var array<string,int> $slots */
/** @var list<array<string,mixed>> $installed */
/** @var list<array<string,mixed>> $inventory */
/** @var array<string,int> $up_credits */
/** @var array<string,int> $up_salvage */

$RARITY = ['civ' => 'Civile', 'mil' => 'Militare', 'exp' => 'Sperimentale', 'xeno' => 'Xeno', 'precursor' => 'Precursore'];
$CATLBL = ['weapon' => 'Armi', 'defense' => 'Difesa', 'drive' => 'Propulsione', 'computer' => 'Computer', 'utility' => 'Utility'];
$NEXT   = ['civ' => 'mil', 'mil' => 'exp', 'exp' => 'xeno', 'xeno' => 'precursor'];

$fmtEffects = static function ($rolled, $effects): string {
    $e = json_decode((string) ($rolled ?: $effects), true) ?: [];
    $out = [];
    foreach ($e as $k => $v) {
        $out[] = match ($k) {
            'combat_pct'          => '+' . (int) $v . '% combattimento',
            'max_shields_pct'     => '+' . (int) $v . '% scudi max',
            'shield_regen'        => '+' . (int) $v . ' rigen. scudi/salto',
            'warp_turn_reduction' => '−' . (int) $v . ' turno/i per warp',
            'cargo_bonus'         => '+' . (int) $v . ' stive',
            'scanner'             => 'scanner ' . $v,
            'scan_range'          => '+' . (int) $v . ' raggio scansione',
            'cloak'               => 'mantello',
            'salvage_bonus_pct'   => '+' . (int) $v . '% Leghe',
            'drop_luck_pct'       => '+' . (int) $v . '% fortuna bottino',
            default               => $k . ' ' . $v,
        };
    }
    return implode(' · ', $out);
};

$usedByCat = [];
foreach ($installed as $m) {
    $usedByCat[$m['category']] = ($usedByCat[$m['category']] ?? 0) + 1;
}
?>
<section class="statusbar">
  <div><span class="k">Crediti</span><span class="v"><?= number_format((int) $player['credits'], 0, ',', '.') ?></span></div>
  <div><span class="k">Leghe</span><span class="v"><?= number_format((int) ($player['salvage'] ?? 0), 0, ',', '.') ?></span></div>
  <div><span class="k">Cristalli</span><span class="v"><?= number_format((int) ($player['crystals'] ?? 0), 0, ',', '.') ?></span></div>
  <div><span class="k">Componenti</span><span class="v"><?= number_format((int) ($player['components'] ?? 0), 0, ',', '.') ?></span></div>
  <div><span class="k">Moduli</span><span class="v"><?= (int) ($ship['mod_count'] ?? count($installed)) ?>/<?= array_sum($slots) ?></span></div>
  <div><a href="<?= e(url('/gioco')) ?>">← Plancia</a></div>
  <div><a href="<?= e(url('/gioco/cantiere')) ?>">Cantiere</a></div>
</section>

<?php if (!$at_dock): ?>
  <div class="alert event-banner">Non sei allo <strong>StarDock</strong>: puoi consultare e <em>smontare</em> i moduli, ma per installare, rimuovere o potenziare devi essere in officina.</div>
<?php endif; ?>

<div class="game-grid plancia-grid">
  <section class="panel">
    <h1>Slot dello scafo</h1>
    <?php if (($ship['type_key'] ?? '') === 'escape_pod'): ?>
      <p class="hint">La capsula di salvataggio non ha slot. Procurati una nave al Cantiere.</p>
    <?php else: ?>
    <div class="slot-grid">
      <?php foreach ($slots as $cat => $tot): if ($tot <= 0) continue; ?>
        <div class="slot-cat">
          <h3><?= e($CATLBL[$cat] ?? $cat) ?> <span class="mut"><?= (int) ($usedByCat[$cat] ?? 0) ?>/<?= (int) $tot ?></span></h3>
          <?php foreach ($installed as $m): if ($m['category'] !== $cat) continue; ?>
            <div class="module-row">
              <span class="rarity rarity-<?= e($m['rarity']) ?>"><?= e($RARITY[$m['rarity']] ?? $m['rarity']) ?></span>
              <strong><?= e($m['name']) ?></strong>
              <span class="mut"><?= e($fmtEffects($m['rolled'], $m['effects'])) ?></span>
              <?php if ($at_dock): ?>
                <form method="post" action="<?= e(url('/gioco/moduli/rimuovi')) ?>" class="inline">
                  <?= csrf_field() ?><input type="hidden" name="mod" value="<?= (int) $m['id'] ?>">
                  <button class="btn xs ghost" type="submit">Rimuovi</button>
                </form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
          <?php for ($i = (int) ($usedByCat[$cat] ?? 0); $i < (int) $tot; $i++): ?>
            <div class="module-row empty"><span class="mut">— slot libero —</span></div>
          <?php endfor; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if (!empty($ship['mod_effects'])): ?>
      <p class="hint">Effetti attivi: <?= e($fmtEffects(json_encode($ship['mod_effects']), '{}')) ?>.</p>
    <?php endif; ?>
    <?php endif; ?>
  </section>

  <section class="panel">
    <h1>Inventario moduli</h1>
    <?php if ($inventory === []): ?>
      <p class="hint">Nessun modulo. Si trovano abbattendo NPC e navi nemiche, meglio se in regioni di frontiera o profonde.</p>
    <?php else: ?>
    <ul class="mod-list">
      <?php foreach ($inventory as $it):
        $catUsed = (int) ($usedByCat[$it['category']] ?? 0);
        $catFree = $catUsed < (int) ($slots[$it['category']] ?? 0);
        $nx = $NEXT[$it['rarity']] ?? null;
      ?>
        <li>
          <div class="mi-head">
            <span class="rarity rarity-<?= e($it['rarity']) ?>"><?= e($RARITY[$it['rarity']] ?? $it['rarity']) ?></span>
            <strong><?= e($it['name']) ?></strong>
            <span class="mut"><?= e($CATLBL[$it['category']] ?? $it['category']) ?></span>
          </div>
          <div class="mi-eff"><?= e($fmtEffects($it['rolled'], $it['effects'])) ?></div>
          <?php if ($it['descr']): ?><div class="mi-descr mut"><?= e($it['descr']) ?></div><?php endif; ?>
          <div class="mi-actions">
            <?php if ($at_dock): ?>
              <form method="post" action="<?= e(url('/gioco/moduli/installa')) ?>" class="inline">
                <?= csrf_field() ?><input type="hidden" name="item" value="<?= (int) $it['id'] ?>">
                <button class="btn xs" type="submit"<?= $catFree ? '' : ' disabled' ?>><?= $catFree ? 'Installa' : 'Slot pieni' ?></button>
              </form>
              <?php if ($nx): ?>
                <form method="post" action="<?= e(url('/gioco/moduli/potenzia')) ?>" class="inline">
                  <?= csrf_field() ?><input type="hidden" name="item" value="<?= (int) $it['id'] ?>">
                  <button class="btn xs ghost" type="submit">Potenzia → <?= e($RARITY[$nx]) ?>
                    <?php if (isset($up_credits[$it['rarity']])): ?>(<?= number_format($up_credits[$it['rarity']], 0, ',', '.') ?> cr + <?= (int) ($up_salvage[$it['rarity']] ?? 0) ?> Leghe)<?php endif; ?>
                  </button>
                </form>
              <?php endif; ?>
            <?php endif; ?>
            <form method="post" action="<?= e(url('/gioco/moduli/smonta')) ?>" class="inline"
                  onsubmit="return confirm('Smontare <?= e(addslashes($it['name'])) ?> per <?= (int) $it['base_salvage'] ?> Leghe?')">
              <?= csrf_field() ?><input type="hidden" name="item" value="<?= (int) $it['id'] ?>">
              <button class="btn xs ghost" type="submit">Smonta (+<?= (int) $it['base_salvage'] ?>)</button>
            </form>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>
</div>

<?php if (!empty($jobs)): ?>
<section class="panel">
  <h2>Officina — lavori in corso <span class="mut"><?= count($jobs) ?>/<?= (int) ($max_jobs ?? 3) ?></span></h2>
  <ul class="mod-list craft-jobs">
    <?php foreach ($jobs as $j):
      $s = (int) $j['secs_left'];
      $when = $j['ready'] ? 'pronto — consegna al prossimo ciclo'
        : ($s < 90 ? 'pronto tra meno di un minuto'
          : ($s < 3600 ? 'pronto tra circa ' . max(1, (int) round($s / 60)) . ' min'
            : 'pronto tra circa ' . max(1, (int) round($s / 3600)) . ' h'));
    ?>
      <li class="fac-offer">
        <span class="rarity rarity-<?= e($j['rarity']) ?>"><?= e($j['item_name']) ?></span>
        <span class="mut"><?= $j['ready'] ? '✓ ' : '⚙ ' ?><?= e($when) ?></span>
        <form method="post" action="<?= e(url('/gioco/moduli/annulla-lavoro')) ?>" class="inline"
              onsubmit="return confirm('Annullare il lavoro? I materiali tornano indietro, i turni no.')">
          <?= csrf_field() ?><input type="hidden" name="job" value="<?= (int) $j['id'] ?>">
          <button class="btn xs ghost" type="submit">Annulla</button>
        </form>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<section class="panel">
  <h1>Raffineria &amp; produzione</h1>
  <?php if (!$at_dock): ?>
    <p class="hint">Raffineria e avvio dei lavori disponibili allo StarDock<?= !empty($jobs) ? ' (i lavori già avviati proseguono ovunque)' : '' ?>.</p>
  <?php else: ?>
  <p class="hint">Minerale + equipaggiamento → <strong>Componenti</strong>. Componenti + Cristalli + Leghe (e a volte crediti/merci) su ricetta → un modulo preciso.</p>

  <form method="post" action="<?= e(url('/gioco/moduli/raffina')) ?>" class="row">
    <?= csrf_field() ?>
    <label>Raffina (× <?= (int) $refine['ore'] ?> minerale + <?= (int) $refine['equ'] ?> equip. per Componente)
      <input type="number" name="qty" min="1" value="10" class="qty">
    </label>
    <button class="btn xs" type="submit">Raffina</button>
  </form>

  <h2>Ricette</h2>
  <p class="hint">La fabbricazione richiede tempo: il modulo arriva quando il lavoro matura (dai ~4 min dei civili alle ~3 h dei Precursori). Massimo <?= (int) ($max_jobs ?? 3) ?> lavori in parallelo.</p>
  <ul class="mod-list">
    <?php foreach (($recipes ?? []) as $rc): ?>
      <li class="fac-offer">
        <span class="rarity rarity-<?= e($rc['rarity']) ?>"><?= e($rc['item_name']) ?></span>
        <span class="mut">
          <?php
          $c = [];
          if ((int) $rc['cost_credits'] > 0)    $c[] = number_format((int) $rc['cost_credits'], 0, ',', '.') . ' cr';
          if ((int) $rc['cost_components'] > 0) $c[] = (int) $rc['cost_components'] . ' Comp.';
          if ((int) $rc['cost_crystals'] > 0)  $c[] = (int) $rc['cost_crystals'] . ' Crist.';
          if ((int) $rc['cost_salvage'] > 0)   $c[] = (int) $rc['cost_salvage'] . ' Leghe';
          if ((int) $rc['cargo_ore'] > 0)      $c[] = (int) $rc['cargo_ore'] . ' min.';
          if ((int) $rc['cargo_equ'] > 0)      $c[] = (int) $rc['cargo_equ'] . ' equip.';
          echo e(implode(' · ', $c));
          if ($rc['min_faction']) echo ' · <span>rep ' . e($rc['min_faction']) . ' ' . e($rc['min_tier']) . '+</span>';
          ?>
        </span>
        <?php if (!$rc['unlocked']): ?>
          <span class="pill mut">bloccata</span>
        <?php else: ?>
          <form method="post" action="<?= e(url('/gioco/moduli/crafta')) ?>" class="inline">
            <?= csrf_field() ?><input type="hidden" name="recipe" value="<?= e($rc['ckey']) ?>">
            <button class="btn xs" type="submit"<?= $rc['affordable'] && count($jobs ?? []) < (int) ($max_jobs ?? 3) ? '' : ' disabled' ?>>Avvia</button>
          </form>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
