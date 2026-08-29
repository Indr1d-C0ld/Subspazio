<?php
/** @var int $to */
/** @var array<string,mixed> $player */
/** @var array<string,mixed> $ship */
/** @var array{ok:bool,error?:string,path?:list<int>,hops?:int,turns?:int} $known */
/** @var array{ok:bool,error?:string,path?:list<int>,hops?:int,turns?:int}|null $full */
?>
<section class="panel narrow">
  <h1>Rotta verso il settore <?= (int) $to ?></h1>

  <?php if ($known['ok']): ?>
    <p class="course-path"><?= e(implode('  →  ', $known['path'])) ?></p>
    <p><strong><?= (int) $known['hops'] ?></strong> warp · circa <strong><?= (int) $known['turns'] ?></strong> turni
       (turni disponibili: <?= (int) $player['turns'] ?>).</p>
    <form method="post" action="<?= e(url('/gioco/autopilot')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="to" value="<?= (int) $to ?>">
      <button class="btn" type="submit">Attiva autopilota</button>
      <a class="btn ghost" href="<?= e(url('/gioco')) ?>">Annulla</a>
    </form>
  <?php else: ?>
    <div class="alert err"><?= e($known['error'] ?? 'Rotta non disponibile.') ?></div>
    <?php if ($full !== null && $full['ok']): ?>
      <p class="hint">Sulla topologia completa esisterebbe una rotta di
         <strong><?= (int) $full['hops'] ?></strong> warp, ma attraversa settori che non hai ancora esplorato.
         L'autopilota richiede una rotta interamente nota.</p>
      <p class="course-path muted"><?= e(implode('  →  ', $full['path'])) ?></p>
    <?php endif; ?>
    <p><a class="btn ghost" href="<?= e(url('/gioco')) ?>">Torna alla plancia</a></p>
  <?php endif; ?>
</section>
