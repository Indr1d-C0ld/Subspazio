<?php
/**
 * Marcatore d'aiuto «?» con tendina in CSS puro (hover/focus, niente JS →
 * ok con la CSP). Parametri:
 *   key  (string)  chiave in App\Game\Help::TEXT
 *   text (string)  testo esplicito (alternativa a key)
 * Non rende nulla se non trova né testo né chiave valida.
 */
use App\Game\Help;

$msg = trim((string) ($text ?? ''));
if ($msg === '' && !empty($key)) {
    $msg = (string) (Help::get((string) $key) ?? '');
}
if ($msg === '') {
    return;
}
?><span class="help" tabindex="0" role="note" aria-label="<?= e($msg) ?>"><span class="help-q" aria-hidden="true">?</span><span class="help-pop"><?= e($msg) ?></span></span>
