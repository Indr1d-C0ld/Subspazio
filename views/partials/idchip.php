<?php
/**
 * Targhetta d'identità del comandante: avatar o stemma + nome colorato + titolo.
 * Parametri:
 *   player (array)   richiede id, handle, experience; opzionali color, crest, motto
 *   motto  (bool)    se true mostra anche il motto sotto il titolo (default false)
 *   size   (int)     lato dell'emblema in px, default 22
 *   avatar (mixed)   string = URL avatar da usare; false = forza lo stemma
 *                    (nessuna query); assente/null = risolvi da MediaAsset
 */
use App\Game\Identity;
use App\Game\MediaAsset;

$id = Identity::forPlayer($player);
$sz = (int) ($size ?? 22);
$pid = (int) ($player['id'] ?? 0);

$avatarUrl = null;
if (isset($avatar)) {                         // passato esplicitamente (URL o false)
    $avatarUrl = $avatar === false ? null : (string) $avatar;
} elseif ($pid > 0 && MediaAsset::current('player', $pid, 'avatar') !== null) {
    $avatarUrl = url('/media/c/' . $pid . '/avatar');
}
?><span class="idchip">
  <?php if ($avatarUrl !== null): ?>
    <img class="idchip-avatar" style="--crest-color:<?= e($id['color']) ?>;width:<?= $sz ?>px;height:<?= $sz ?>px"
         src="<?= e($avatarUrl) ?>" alt="" loading="lazy">
  <?php else: ?>
    <?= partial('crest', ['crest' => $id['crest'], 'color' => $id['color'], 'size' => $sz]) ?>
  <?php endif; ?>
  <span class="idchip-txt">
    <span class="idchip-name" style="color:<?= e($id['color']) ?>"><?= e($player['handle']) ?></span>
    <span class="idchip-title"><?= e($id['title']) ?></span>
    <?php if (!empty($motto) && $id['motto'] !== ''): ?>
      <span class="idchip-motto">“<?= e($id['motto']) ?>”</span>
    <?php endif; ?>
  </span>
</span>
