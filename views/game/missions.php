<?php
/** @var array<string,mixed> $player */
/** @var list<array<string,mixed>> $missions */
/** @var list<array<string,mixed>> $officers */  // disponibili
/** @var list<array<string,mixed>> $busy */
/** @var list<array<string,mixed>> $log */

use App\Game\Crew;
use App\Game\AwayMissions;

$SK = Crew::SKILL_LABEL;
?>
<section class="statusbar">
  <div><span class="k">Crediti</span><span class="v"><?= number_format((int) $player['credits'], 0, ',', '.') ?></span></div>
  <div><span class="k">Turni</span><span class="v"><?= (int) $player['turns'] ?></span></div>
  <div><span class="k">Leghe</span><span class="v"><?= number_format((int) ($player['salvage'] ?? 0), 0, ',', '.') ?></span></div>
  <div><span class="k">Squadra pronta</span><span class="v"><?= count($officers) ?></span></div>
  <div><a href="<?= e(url('/gioco/equipaggio')) ?>">Equipaggio</a></div>
  <div><a href="<?= e(url('/gioco')) ?>">← Plancia</a></div>
</section>

<?php if ($officers === []): ?>
  <div class="alert event-banner">Nessun ufficiale disponibile (imbarcato, in salute e non in ricarica). Recluta o aspetta i cooldown.</div>
<?php endif; ?>

<section class="panel">
  <h1>Missioni disponibili</h1>
  <?php if ($missions === []): ?>
    <p class="hint">Nessuna missione al momento. Se ne generano di nuove col tempo e cambiano con la regione in cui ti trovi.</p>
  <?php endif; ?>

  <?php foreach ($missions as $m):
    $need = json_decode((string) $m['skills'], true) ?: [];
    $rw = json_decode((string) $m['rewards'], true) ?: [];
    $here = $m['sector_id'] !== null && (int) $m['sector_id'] === (int) $player['sector_id'];
    $farSector = $m['sector_id'] !== null && !$here;
  ?>
  <form method="post" action="<?= e(url('/gioco/missioni/invia')) ?>" class="mission">
    <?= csrf_field() ?><input type="hidden" name="mission" value="<?= (int) $m['id'] ?>">
    <div class="mi-head">
      <strong><?= e($m['title']) ?></strong>
      <span class="pill mut">diff. <?= (int) $m['difficulty'] ?></span>
      <span class="pill mut"><?= (int) $m['turn_cost'] ?> turni</span>
      <?php if ($here): ?><span class="pill ok">in questo settore</span>
      <?php elseif ($farSector): ?><span class="pill warn">settore <?= (int) $m['sector_id'] ?></span><?php endif; ?>
    </div>
    <p class="mi-descr mut"><?= e($m['descr']) ?></p>
    <p class="mi-eff">
      Richiede:
      <?php foreach ($need as $k => $v): ?><span class="pill"><?= e($SK[$k] ?? $k) ?> ≥ <?= (int) $v ?></span><?php endforeach; ?>
      · Ricompense:
      <?php if (!empty($rw['credits'])): ?><span class="pill ok"><?= number_format((int) $rw['credits'], 0, ',', '.') ?> cr</span><?php endif; ?>
      <?php if (!empty($rw['salvage'])): ?><span class="pill ok">+<?= (int) $rw['salvage'] ?> Leghe</span><?php endif; ?>
      <?php if (!empty($rw['module_pct'])): ?><span class="pill exp">modulo <?= (int) $rw['module_pct'] ?>%</span><?php endif; ?>
      <?php if (!empty($rw['officer_pct'])): ?><span class="pill xeno">ufficiale <?= (int) $rw['officer_pct'] ?>%</span><?php endif; ?>
      <span class="pill">+<?= (int) ($rw['xp'] ?? 0) ?> XP</span>
    </p>
    <div class="mi-team">
      <?php foreach ($officers as $o): ?>
        <label class="chk"><input type="checkbox" name="officers[]" value="<?= (int) $o['id'] ?>">
          <?= e($o['name']) ?> <span class="mut">(<?= e(Crew::roleLabel($o['role'])) ?> Lv<?= (int) $o['level'] ?>)</span></label>
      <?php endforeach; ?>
    </div>
    <button class="btn xs" type="submit"<?= ($officers === [] || (int) $player['turns'] < (int) $m['turn_cost']) ? ' disabled' : '' ?>>Invia squadra (1-3)</button>
  </form>
  <?php endforeach; ?>
</section>

<?php if ($busy !== []): ?>
<section class="panel">
  <h2>Impegnati / in ricarica</h2>
  <p class="hint"><?= e(implode(' · ', array_map(fn ($o) => $o['name'] . ($o['status'] === 'injured' ? ' (ferito)' : ' (rientro ' . substr((string) $o['ready_at'], 11, 5) . ')'), $busy))) ?></p>
</section>
<?php endif; ?>

<?php if ($log !== []): ?>
<section class="panel">
  <h2>Esiti recenti</h2>
  <table class="tbl compact">
    <thead><tr><th>Quando</th><th>Missione</th><th>Squadra</th><th>Esito</th><th>Ricompensa</th></tr></thead>
    <tbody>
    <?php foreach ($log as $r): ?>
      <tr>
        <td class="nowrap"><?= e(fmt_dt($r['created_at'])) ?></td>
        <td><?= e($r['title']) ?></td>
        <td class="mut"><?= e(implode(', ', json_decode((string) $r['officers'], true) ?: [])) ?></td>
        <td><span class="pill <?= in_array($r['outcome'], ['failure','disaster'], true) ? 'err' : 'ok' ?>"><?= e(AwayMissions::OUTCOME_LABEL[$r['outcome']] ?? $r['outcome']) ?></span></td>
        <td class="mut"><?= e($r['reward_text']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>
