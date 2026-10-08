<?php
/** @var array<string,mixed> $player */
/** @var list<array<string,mixed>> $all */
/** @var array<string,string> $earned */
/** @var int $points */
/** @var array<string,int> $stats */
/** @var list<string> $titoli */
/** @var array{0:int,1:string}|null $prossimo */
use App\Game\Achievements;

$perCat = [];
foreach ($all as $a) {
    $perCat[$a['categoria'] ?? 'carriera'][] = $a;
}
$LIV = ['bronzo' => 'Bronzo', 'argento' => 'Argento', 'oro' => 'Oro', 'platino' => 'Platino'];
$puntiTot = array_sum(array_map(static fn ($a) => (int) $a['points'], $all));
?>
<section class="statusbar">
  <div><span class="k">Comandante</span><span class="v"><?= e($player['handle']) ?><?= !empty($player['titolo']) ? ' <span class="titolo-chip">' . e($player['titolo']) . '</span>' : '' ?></span></div>
  <div><span class="k">Traguardi</span><span class="v"><?= count($earned) ?>/<?= count($all) ?></span></div>
  <div><span class="k">Punti</span><span class="v"><?= (int) $points ?>/<?= $puntiTot ?></span></div>
  <div><a href="<?= e(url('/gioco/albo')) ?>">Albo d'oro</a></div>
  <div><a href="<?= e(url('/gioco')) ?>">← Plancia</a></div>
</section>

<section class="panel">
  <h1><span class="sec-ic">🏅</span> Traguardi<?= partial('help', ['key' => 'traguardi.home']) ?></h1>
  <p class="hint">Prestigio, non potere: i traguardi restano da una stagione all'altra, come i contatori di carriera che li sbloccano, e non danno vantaggi in partita. I punti sbloccano titoli onorifici da mostrare accanto al nome; alcuni traguardi ne danno uno proprio. Quelli segreti si scoprono solo ottenendoli.</p>

  <form method="post" action="<?= e(url('/gioco/traguardi/titolo')) ?>" class="row titolo-form">
    <?= csrf_field() ?>
    <label>Titolo accanto al nome
      <select name="titolo">
        <option value="">— nessuno —</option>
        <?php foreach ($titoli as $t): ?>
          <option value="<?= e($t) ?>"<?= ($player['titolo'] ?? null) === $t ? ' selected' : '' ?>><?= e($t) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="btn xs" type="submit">Mostra</button>
    <?php if ($prossimo !== null): ?>
      <span class="mut">Prossimo titolo: «<?= e($prossimo[1]) ?>» a <?= (int) $prossimo[0] ?> punti (ne mancano <?= (int) $prossimo[0] - (int) $points ?>).</span>
    <?php endif; ?>
  </form>
</section>

<?php foreach (Achievements::CATEGORIE as $ck => $cnome): if (empty($perCat[$ck])) continue;
  $lista = $perCat[$ck];
  $presi = count(array_filter($lista, static fn ($a) => isset($earned[$a['ckey']]))); ?>
<section class="panel">
  <h2><?= e($cnome) ?> <span class="mut"><?= $presi ?>/<?= count($lista) ?></span></h2>
  <div class="ach-grid">
    <?php foreach ($lista as $a):
      $got = isset($earned[$a['ckey']]);
      $nascosto = !$got && !empty($a['segreto']);
      $liv = $a['livello'] ?? null;
      $val = $a['contatore'] ? (int) ($stats[$a['contatore']] ?? 0) : null;
      $sog = $a['soglia'] !== null ? (int) $a['soglia'] : null; ?>
      <div class="ach<?= $got ? ' got' : '' ?><?= $liv ? ' liv-' . e($liv) : '' ?><?= $nascosto ? ' segreto' : '' ?>">
        <span class="ach-icon"><?= $nascosto ? '?' : e($a['icon']) ?></span>
        <div>
          <strong><?= $nascosto ? 'Traguardo segreto' : e($a['name']) ?></strong>
          <?php if ($liv): ?><span class="ach-liv"><?= e($LIV[$liv] ?? $liv) ?></span><?php endif; ?>
          <span class="ach-pts"><?= (int) $a['points'] ?></span>
          <p><?= $nascosto ? 'Si scopre ottenendolo.' : e($a['descr']) ?></p>
          <?php if (!empty($a['titolo']) && !$nascosto): ?><p class="ach-titolo">Titolo: «<?= e($a['titolo']) ?>»</p><?php endif; ?>
          <?php if ($got): ?>
            <small class="hint">sbloccato il <?= e(fmt_dt($earned[$a['ckey']])) ?></small>
          <?php elseif ($val !== null && $sog !== null && !$nascosto): ?>
            <div class="ach-bar" title="<?= number_format(min($val, $sog), 0, ',', '.') ?> / <?= number_format($sog, 0, ',', '.') ?>">
              <span style="width:<?= $sog > 0 ? min(100, (int) floor(100 * $val / $sog)) : 0 ?>%"></span>
            </div>
            <small class="mut"><?= number_format(min($val, $sog), 0, ',', '.') ?> / <?= number_format($sog, 0, ',', '.') ?></small>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endforeach; ?>
