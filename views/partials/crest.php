<?php
/**
 * Stemma di flotta. Parametri:
 *   crest  (string)  chiave dello stemma (fallback al predefinito)
 *   color  (string)  hex dell'accento (fallback al predefinito)
 *   size   (int)     lato in px, default 20
 *   title  (string)  tooltip opzionale
 */
use App\Game\Identity;

$k  = in_array($crest ?? '', Identity::CRESTS, true) ? $crest : Identity::DEFAULT_CREST;
$c  = isset(Identity::PALETTE[$color ?? '']) ? $color : Identity::DEFAULT_COLOR;
$sz = max(12, (int) ($size ?? 20));
?><span class="crest" style="--crest-color:<?= e($c) ?>;width:<?= $sz ?>px;height:<?= $sz ?>px"<?= !empty($title) ? ' title="' . e($title) . '"' : '' ?>><svg viewBox="0 0 24 24" width="<?= $sz ?>" height="<?= $sz ?>" aria-hidden="true"><use href="#crest-<?= e($k) ?>"></use></svg></span>
