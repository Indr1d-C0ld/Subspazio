<?php
/**
 * Etichetta compatta di un comandante presente (lista "navi qui" / forze).
 * Parametri:
 *   p    (array)  richiede id, handle; opzionali color, crest, has_avatar,
 *                 has_logo, ship_type, protected
 *   size (int)    lato dell'emblema in px, default 28
 *   ship (bool)   mostra "(tipo nave)" accanto al nome (default true)
 */
use App\Game\Identity;

$color = isset(Identity::PALETTE[$p['color'] ?? '']) ? $p['color'] : Identity::DEFAULT_COLOR;
$crest = in_array($p['crest'] ?? '', Identity::CRESTS, true) ? $p['crest'] : Identity::DEFAULT_CREST;
$sz  = (int) ($size ?? 28);
$pid = (int) ($p['id'] ?? 0);
?><span class="who-chip">
  <?php if (!empty($p['has_avatar']) && $pid > 0): ?>
    <?= partial('media_hover', ['id' => $pid, 'kind' => 'avatar', 'size' => $sz, 'frame' => $color, 'alt' => $p['handle'] ?? '']) ?>
  <?php else: ?>
    <?= partial('crest', ['crest' => $crest, 'color' => $color, 'size' => $sz]) ?>
  <?php endif; ?>
  <span class="wc-name" style="color:<?= e($color) ?>"><?= e($p['handle'] ?? '') ?></span>
  <?php if (($ship ?? true) && !empty($p['ship_type'])): ?><small>(<?= e($p['ship_type']) ?>)</small><?php endif; ?>
  <?php if (!empty($p['has_logo']) && $pid > 0): ?>
    <?= partial('media_hover', ['id' => $pid, 'kind' => 'logo', 'size' => 20, 'alt' => 'logo di ' . ($p['handle'] ?? '')]) ?>
  <?php endif; ?>
  <?php if (!empty($p['protected'])): ?><span title="protezione novizio">🛡</span><?php endif; ?>
</span>
