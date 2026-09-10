<?php
/**
 * Indicatore del modello di nave posseduto. Mostra l'illustrazione
 * assets/ships/<type>.png se presente, altrimenti ripiega sulla sagoma SVG
 * vettoriale (partials/crest con chiave "nave:<type>").
 * Parametri:
 *   type  (string)  ship_types.ckey
 *   size  (int)     lato in px, default 26
 *   color (string)  accento per la sagoma SVG di fallback
 *   title (string)  tooltip (di solito il nome del modello)
 */
use App\Game\Identity;

$type = preg_replace('/[^a-z0-9_]/', '', (string) ($type ?? '')) ?? '';
$sz   = max(12, (int) ($size ?? 26));
$ttl  = trim((string) ($title ?? ''));
$art  = $type !== '' ? ship_art($type) : null;

if ($art !== null): ?>
<img class="ship-art" src="<?= e($art) ?>" alt="<?= e($ttl) ?>" loading="lazy"
     style="width:<?= $sz ?>px;height:<?= $sz ?>px"<?= $ttl !== '' ? ' title="' . e($ttl) . '"' : '' ?>>
<?php elseif (in_array($type, Identity::SHIP_MARKS, true)): ?>
<?= partial('crest', ['crest' => 'nave:' . $type, 'color' => $color ?? null, 'size' => $sz, 'title' => $ttl]) ?>
<?php endif;
