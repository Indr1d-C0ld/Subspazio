<?php
/**
 * Le schede dei temi grafici: anteprima in miniatura, nome, ispirazione.
 * Ogni scheda e' un bottone che sceglie il tema e torna a $torna (un
 * percorso interno). Usata nel Profilo e nella pagina iniziale.
 *
 * @var string $torna
 */
$temaInUso = \App\Core\Temi::attuale();
?>
  <form method="post" action="<?= e(url('/tema')) ?>" class="temi-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="torna" value="<?= e((string) ($torna ?? '/')) ?>">
    <?php foreach (\App\Core\Temi::CATALOGO as $k => $t): [$c0, $c1, $c2, $c3, $c4] = $t['anteprima']; ?>
      <button type="submit" name="tema" value="<?= e($k) ?>" class="tema-scheda<?= $k === $temaInUso ? ' in-uso' : '' ?>"
              aria-pressed="<?= $k === $temaInUso ? 'true' : 'false' ?>"
              style="--t0: <?= e($c0) ?>; --t1: <?= e($c1) ?>; --t2: <?= e($c2) ?>; --t3: <?= e($c3) ?>; --t4: <?= e($c4) ?>;">
        <span class="tema-mini" data-forma="<?= e($k) ?>" aria-hidden="true">
          <span class="tm-barra"></span>
          <span class="tm-corpo">
            <span class="tm-pannello"><span class="tm-riga"></span><span class="tm-riga corta"></span><span class="tm-bottone"></span></span>
            <span class="tm-pannello due"><span class="tm-riga"></span><span class="tm-riga corta"></span></span>
          </span>
        </span>
        <span class="tema-nome"><?= e($t['nome']) ?><?php if ($k === $temaInUso): ?> <span class="pill ok">in uso</span><?php endif; ?></span>
        <span class="tema-descr"><?= e($t['ispirazione']) ?></span>
      </button>
    <?php endforeach; ?>
  </form>
