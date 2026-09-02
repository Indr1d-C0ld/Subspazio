<?php
/** @var array<string,mixed> $player */
/** @var array<string,mixed> $ship */
/** @var bool $at_dock */
/** @var int $slots */
/** @var list<array<string,mixed>> $roster */
/** @var array{assigned:int,bench:int,injured:int} $counts */
/** @var list<array<string,mixed>> $recruits */

use App\Game\Crew;

$SK = Crew::SKILL_LABEL;
$now = time();
$assigned = array_values(array_filter($roster, fn ($o) => (int) $o['assigned'] === 1));
$reserve  = array_values(array_filter($roster, fn ($o) => (int) $o['assigned'] === 0));
$injured  = array_values(array_filter($roster, fn ($o) => $o['status'] === 'injured'));
$loyLvl   = (int) \App\Game\GameConfig::int('crew.loyalty_level', 4);

$card = function (array $o) use ($SK, $now, $at_dock, $slots, $counts, $loyLvl) {
    $sk = json_decode((string) $o['skills'], true) ?: [];
    arsort($sk);
    $next = Crew::xpForNext((int) $o['level']);
    $pct = $next > 0 ? min(100, round((int) $o['xp'] / $next * 100)) : 100;
    $ab = Crew::abilityInfo($o['role'], (int) $o['ability_tier']);
    $ready = $o['ready_at'] === null || strtotime((string) $o['ready_at']) <= $now;
    $loyalty = (int) $o['level'] >= $loyLvl && (int) $o['loyalty_done'] === 0;
    ?>
    <div class="officer-card<?= $o['status'] === 'injured' ? ' injured' : '' ?>">
      <div class="oc-head">
        <span class="rarity rarity-<?= ['tactical'=>'mil','navigator'=>'exp','engineer'=>'civ','scientist'=>'xeno','medic'=>'precursor','diplomat'=>'mil'][$o['role']] ?? 'civ' ?>"><?= e(Crew::roleLabel($o['role'])) ?></span>
        <strong><?= e($o['name']) ?></strong>
        <span class="mut">Lv <?= (int) $o['level'] ?></span>
        <?php if ($o['status'] === 'injured'): ?><span class="pill err">ferito<?= $o['ready_at'] ? ' · ' . substr((string) $o['ready_at'], 5, 11) : '' ?></span><?php endif; ?>
        <?php if ((int) $o['ability_tier'] >= 2): ?><span class="pill ok">lealtà</span><?php endif; ?>
      </div>
      <div class="xp-bar"><span style="width: <?= (int) $pct ?>%"></span></div>
      <div class="oc-skills">
        <?php $i = 0; foreach ($sk as $k => $v) { if ($i++ >= 4) break; echo '<span>' . e($SK[$k] ?? $k) . ' ' . (int) $v . '</span>'; } ?>
      </div>
      <div class="oc-ability">
        <strong><?= e($ab['name']) ?></strong> — <span class="mut"><?= e($ab['desc']) ?></span>
      </div>
      <?php if ($loyalty): ?><p class="hint">★ Pronto per la missione di lealtà: portalo a termine con successo in una missione away.</p><?php endif; ?>
      <div class="oc-actions">
        <?php if ((int) $o['assigned'] === 1 && $o['status'] === 'active'): ?>
          <form method="post" action="<?= e(url('/gioco/equipaggio/abilita')) ?>" class="inline">
            <?= csrf_field() ?><input type="hidden" name="officer" value="<?= (int) $o['id'] ?>">
            <button class="btn xs" type="submit"<?= $ready ? '' : ' disabled' ?>><?= $ready ? 'Usa abilità' : 'In ricarica' ?></button>
          </form>
          <form method="post" action="<?= e(url('/gioco/equipaggio/panchina')) ?>" class="inline">
            <?= csrf_field() ?><input type="hidden" name="officer" value="<?= (int) $o['id'] ?>">
            <button class="btn xs ghost" type="submit">In riserva</button>
          </form>
        <?php elseif ((int) $o['assigned'] === 0): ?>
          <form method="post" action="<?= e(url('/gioco/equipaggio/assegna')) ?>" class="inline">
            <?= csrf_field() ?><input type="hidden" name="officer" value="<?= (int) $o['id'] ?>">
            <button class="btn xs" type="submit"<?= $counts['assigned'] < $slots ? '' : ' disabled' ?>>Imbarca</button>
          </form>
        <?php endif; ?>
        <?php if ($o['status'] === 'injured' && $at_dock): ?>
          <form method="post" action="<?= e(url('/gioco/equipaggio/cura')) ?>" class="inline">
            <?= csrf_field() ?><input type="hidden" name="officer" value="<?= (int) $o['id'] ?>">
            <button class="btn xs ghost" type="submit">Cura (<?= number_format(\App\Game\GameConfig::int('crew.injury_heal_cost', 2500), 0, ',', '.') ?> cr)</button>
          </form>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/gioco/equipaggio/congeda')) ?>" class="inline"
              onsubmit="return confirm('Congedare <?= e(addslashes($o['name'])) ?>? È definitivo.')">
          <?= csrf_field() ?><input type="hidden" name="officer" value="<?= (int) $o['id'] ?>">
          <button class="btn xs ghost" type="submit">Congeda</button>
        </form>
      </div>
    </div>
    <?php
};
?>
<section class="statusbar">
  <div><span class="k">Crediti</span><span class="v"><?= number_format((int) $player['credits'], 0, ',', '.') ?></span></div>
  <div><span class="k">Turni</span><span class="v"><?= (int) $player['turns'] ?></span></div>
  <div><span class="k">Equipaggio</span><span class="v"><?= $counts['assigned'] ?>/<?= $slots ?></span></div>
  <div><span class="k">Riserva</span><span class="v"><?= $counts['bench'] ?></span></div>
  <div><a href="<?= e(url('/gioco/missioni')) ?>">Missioni</a></div>
  <div><a href="<?= e(url('/gioco')) ?>">← Plancia</a></div>
</section>

<?php if (!$at_dock): ?>
  <div class="alert event-banner">Fuori dallo StarDock: puoi gestire l'equipaggio e usare le abilità, ma il reclutamento e l'infermeria completa sono in stazione.</div>
<?php endif; ?>

<div class="game-grid plancia-grid">
  <section class="panel">
    <h1>Plancia di comando <span class="mut"><?= count($assigned) ?>/<?= $slots ?></span></h1>
    <?php if ($slots <= 0): ?>
      <p class="hint">Questo scafo non ha posti equipaggio. Serve una nave vera (Cantiere).</p>
    <?php elseif ($assigned === []): ?>
      <p class="hint">Nessun ufficiale imbarcato. Reclutane allo StarDock o recuperali dalle missioni away.</p>
    <?php endif; ?>
    <div class="crew-grid">
      <?php foreach ($assigned as $o) $card($o); ?>
    </div>

    <?php if ($reserve !== []): ?>
      <h2>Riserva</h2>
      <div class="crew-grid">
        <?php foreach ($reserve as $o) $card($o); ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="panel">
    <h1>Reclutamento</h1>
    <?php if (!$at_dock): ?>
      <p class="hint">Disponibile allo StarDock.</p>
    <?php elseif ($recruits === []): ?>
      <p class="hint">Nessun candidato al momento.</p>
    <?php else: ?>
    <div class="crew-grid">
      <?php foreach ($recruits as $c): $sk = json_decode((string) $c['skills'], true) ?: []; arsort($sk); ?>
        <div class="officer-card">
          <div class="oc-head">
            <span class="rarity rarity-civ"><?= e(Crew::roleLabel($c['role'])) ?></span>
            <strong><?= e($c['name']) ?></strong>
            <span class="mut">Lv <?= (int) $c['level'] ?></span>
          </div>
          <div class="oc-skills">
            <?php $i = 0; foreach ($sk as $k => $v) { if ($i++ >= 4) break; echo '<span>' . e($SK[$k] ?? $k) . ' ' . (int) $v . '</span>'; } ?>
          </div>
          <div class="oc-actions">
            <form method="post" action="<?= e(url('/gioco/equipaggio/assumi')) ?>" class="inline">
              <?= csrf_field() ?><input type="hidden" name="candidate" value="<?= (int) $c['id'] ?>">
              <button class="btn xs" type="submit"<?= (int) $player['credits'] >= (int) $c['cost'] ? '' : ' disabled' ?>>Assumi — <?= number_format((int) $c['cost'], 0, ',', '.') ?> cr</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <p class="hint">Ruoli: Tattico (+danno), Navigatore (rotte), Ingegnere (scudi), Scienziato (scansione/bottino), Medico (missioni/cure), Diplomatico (allineamento/pedaggi). Bonus dello stesso ruolo con rendimenti decrescenti.</p>
  </section>
</div>
