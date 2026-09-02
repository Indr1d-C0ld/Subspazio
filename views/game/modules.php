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
  <div><span class="k">Leghe di recupero</span><span class="v"><?= number_format((int) ($player['salvage'] ?? 0), 0, ',', '.') ?></span></div>
  <div><span class="k">Scafo</span><span class="v"><?= e($ship['type_name']) ?></span></div>
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
