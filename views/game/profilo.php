<?php
/** @var array<string,mixed> $player */
/** @var array<string,mixed> $ship */
/** @var array<string,string> $palette */
/** @var list<string> $crests */
use App\Game\Identity;

$curColor = Identity::color($player);
$curCrest = Identity::crest($player);
$curMotto = (string) ($player['motto'] ?? '');
$isPod    = ($ship['type_key'] ?? '') === 'escape_pod';
$crestLabels = [
    'delta' => 'Delta', 'orbita' => 'Orbita', 'stella' => 'Stella', 'corona' => 'Corona',
    'mirino' => 'Mirino', 'cometa' => 'Cometa', 'sciabole' => 'Sciabole', 'esagono' => 'Esagono',
    'tridente' => 'Tridente', 'occhio' => 'Occhio', 'alloro' => 'Alloro', 'rotta' => 'Rotta',
];
?>
<section class="statusbar">
  <div><span class="k">Profilo</span><span class="v"><?= e($player['handle']) ?></span></div>
  <div><a href="<?= e(url('/gioco')) ?>">← Plancia</a></div>
</section>

<section class="panel" id="profile-panel">
  <h1>Identità del comandante</h1>
  <p class="hint">Colore d'accento, stemma di flotta, motto e registro della nave. Compaiono
     nelle classifiche, sulla plancia e — presto — sulla mappa stellare e nella radio.
     Il <strong>titolo</strong> non si sceglie: lo derivano il tuo grado e i tuoi rapporti con le fazioni.</p>

  <div class="id-preview">
    <?= partial('idchip', ['player' => $player, 'motto' => true, 'size' => 34]) ?>
  </div>

  <form method="post" action="<?= e(url('/gioco/profilo')) ?>" class="id-form">
    <?= csrf_field() ?>

    <fieldset class="id-field">
      <legend>Colore d'accento</legend>
      <div class="swatch-grid">
        <?php foreach ($palette as $hex => $label): ?>
          <label class="swatch" title="<?= e($label) ?>">
            <input type="radio" name="color" value="<?= e($hex) ?>" <?= $hex === $curColor ? 'checked' : '' ?>>
            <span class="swatch-dot" style="background:<?= e($hex) ?>"></span>
            <span class="swatch-label"><?= e($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <fieldset class="id-field">
      <legend>Stemma di flotta</legend>
      <div class="crest-grid">
        <?php foreach ($crests as $key): ?>
          <label class="crest-opt" title="<?= e($crestLabels[$key] ?? $key) ?>">
            <input type="radio" name="crest" value="<?= e($key) ?>" <?= $key === $curCrest ? 'checked' : '' ?>>
            <?= partial('crest', ['crest' => $key, 'color' => $curColor, 'size' => 30]) ?>
            <span class="crest-label"><?= e($crestLabels[$key] ?? $key) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <fieldset class="id-field">
      <legend>Motto / callsign</legend>
      <input type="text" name="motto" maxlength="80" value="<?= e($curMotto) ?>"
             placeholder="Una riga che ti rappresenta (max 80)">
    </fieldset>

    <fieldset class="id-field">
      <legend>Nave</legend>
      <?php if ($isPod): ?>
        <p class="hint">Sei in capsula di salvataggio: nome e registro si assegnano quando torni ai comandi di una nave vera.</p>
      <?php else: ?>
        <label class="id-row">Nome della nave
          <input type="text" name="ship_name" maxlength="40" value="<?= e((string) ($ship['name'] ?? '')) ?>"
                 placeholder="es. Andromeda">
        </label>
        <label class="id-row">Registro <span class="mut">stile NCC-…</span>
          <input type="text" name="registry" maxlength="16" value="<?= e((string) ($ship['registry'] ?? '')) ?>"
                 placeholder="es. SS-1701-D" style="text-transform:uppercase">
        </label>
        <p class="hint">Solo lettere maiuscole, cifre e trattino.</p>
      <?php endif; ?>
    </fieldset>

    <div class="id-actions">
      <button type="submit" class="btn">Salva identità</button>
      <a class="btn ghost" href="<?= e(url('/gioco')) ?>">Annulla</a>
    </div>
  </form>
</section>

<script>
(function () {
  var panel = document.getElementById('profile-panel');
  if (!panel) return;
  var preview = panel.querySelector('.id-preview .idchip');
  function currentColor() {
    var c = panel.querySelector('input[name="color"]:checked');
    return c ? c.value : null;
  }
  panel.addEventListener('change', function (ev) {
    var t = ev.target;
    if (!t || !t.name) return;
    var color = currentColor();
    if (t.name === 'color' && color) {
      if (preview) {
        var dot = preview.querySelector('.crest');
        var name = preview.querySelector('.idchip-name');
        if (dot) dot.style.setProperty('--crest-color', color);
        if (name) name.style.color = color;
      }
      panel.querySelectorAll('.crest-grid .crest').forEach(function (el) {
        el.style.setProperty('--crest-color', color);
      });
    }
    if (t.name === 'crest' && preview) {
      var use = preview.querySelector('use');
      if (use) use.setAttribute('href', '#crest-' + t.value);
    }
  });
  var motto = panel.querySelector('input[name="motto"]');
  if (motto && preview) {
    motto.addEventListener('input', function () {
      var m = preview.querySelector('.idchip-motto');
      var v = motto.value.trim();
      if (v === '') { if (m) m.remove(); return; }
      if (!m) {
        m = document.createElement('span');
        m.className = 'idchip-motto';
        preview.querySelector('.idchip-txt').appendChild(m);
      }
      m.textContent = '“' + v + '”';
    });
  }
})();
</script>
