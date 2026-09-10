<?php
/**
 * Avatar o logo di un comandante, con anteprima ingrandita al passaggio del
 * mouse o al focus (CSS puro: `.media-hover::after` con `--pop`). Stessa
 * immagine, solo mostrata più grande — nessuna richiesta in più.
 *
 * Parametri:
 *   id    (int)             id del comandante proprietario
 *   kind  ('avatar'|'logo') default 'avatar'
 *   size  (int)             lato in px dell'anteprima piccola (per il logo è l'altezza)
 *   frame (string)          colore accento del comandante (bordo avatar), opzionale
 *   alt   (string)          testo alternativo, opzionale
 */
use App\Game\Identity;

$id   = (int) ($id ?? 0);
$kind = (($kind ?? 'avatar') === 'logo') ? 'logo' : 'avatar';
$sz   = max(12, (int) ($size ?? ($kind === 'logo' ? 22 : 24)));
$url  = url('/media/c/' . $id . '/' . $kind);
$frameCol = isset(Identity::PALETTE[$frame ?? '']) ? $frame : null;
?><span class="media-hover<?= $kind === 'logo' ? ' is-logo' : '' ?>" tabindex="0"
      style="--pop:url('<?= e($url) ?>')">
  <?php if ($kind === 'logo'): ?>
    <img class="fleet-logo" src="<?= e($url) ?>" alt="<?= e($alt ?? 'logo di flotta') ?>"
         style="height:<?= $sz ?>px" loading="lazy">
  <?php else: ?>
    <img class="idchip-avatar" src="<?= e($url) ?>" alt="<?= e($alt ?? '') ?>"
         style="width:<?= $sz ?>px;height:<?= $sz ?>px<?= $frameCol ? ';--crest-color:' . e($frameCol) : '' ?>"
         loading="lazy">
  <?php endif; ?>
</span>
