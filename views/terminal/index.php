<?php
/** @var array<string,mixed> $player */
/** @var array<string,mixed> $ship */
/** @var string $intro */
?>
<section class="panel term-panel">
  <h1>Terminale — <?= e($player['handle']) ?></h1>
  <p class="hint">Skin door classica. Comandi: <code>L</code> guarda · <code>&lt;numero&gt;</code> muovi ·
     <code>C n</code> rotta · <code>A n</code> autopilota · <code>B testo</code> faro · <code>?</code> aiuto.</p>

  <div id="terminal"
       data-cmd-url="<?= e(url('/api/comando')) ?>"
       data-prompt="<?= e(\App\Game\TerminalRenderer::prompt($player, $ship)) ?>">
    <pre id="term-out"><?= e($intro) ?></pre>
    <form id="term-form" class="term-input">
      <span id="term-prompt"><?= e(\App\Game\TerminalRenderer::prompt($player, $ship)) ?></span>
      <input type="text" id="term-cmd" name="cmd" autocomplete="off" autofocus spellcheck="false">
    </form>
  </div>
  <noscript><p class="alert err">Il terminale richiede JavaScript. Usa la <a href="<?= e(url('/gioco')) ?>">plancia</a>.</p></noscript>
</section>

<script src="<?= e(asset('js/terminal.js')) ?>" defer></script>
