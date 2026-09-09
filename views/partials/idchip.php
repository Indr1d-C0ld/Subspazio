<?php
/**
 * Targhetta d'identità del comandante: stemma + nome colorato + titolo derivato.
 * Parametri:
 *   player (array)  richiede id, handle, experience; opzionali color, crest, motto
 *   motto  (bool)   se true mostra anche il motto sotto il titolo (default false)
 *   size   (int)    lato dello stemma in px, default 22
 */
use App\Game\Identity;

$id = Identity::forPlayer($player);
$sz = (int) ($size ?? 22);
?><span class="idchip">
  <?= partial('crest', ['crest' => $id['crest'], 'color' => $id['color'], 'size' => $sz]) ?>
  <span class="idchip-txt">
    <span class="idchip-name" style="color:<?= e($id['color']) ?>"><?= e($player['handle']) ?></span>
    <span class="idchip-title"><?= e($id['title']) ?></span>
    <?php if (!empty($motto) && $id['motto'] !== ''): ?>
      <span class="idchip-motto">“<?= e($id['motto']) ?>”</span>
    <?php endif; ?>
  </span>
</span>
