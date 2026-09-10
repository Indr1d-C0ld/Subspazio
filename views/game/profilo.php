<?php
/** @var array<string,mixed> $player */
/** @var array<string,mixed> $ship */
/** @var array<string,string> $palette */
/** @var list<string> $crests */
/** @var array<string,array{approved:?array,pending:?array}> $media */
use App\Game\Identity;
use App\Game\MediaAsset;

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

<section class="panel">
  <h2>Immagini caricate</h2>
  <p class="hint">Avatar personale e logo di flotta. Ogni immagine passa da un
     <strong>controllo dell'amministratore</strong> prima di diventare visibile agli
     altri comandanti (stessa coda delle iscrizioni). PNG, JPEG, WebP o GIF, max
     <?= number_format(MediaAsset::maxBytes() / (1024 * 1024), 1, ',', '.') ?> MB; l'immagine
     viene ritagliata e ricodificata (l'avatar in quadrato).</p>

  <div class="media-slots">
    <?php foreach (MediaAsset::KINDS as $kind => $meta):
      $ap = $media[$kind]['approved'] ?? null;
      $pe = $media[$kind]['pending'] ?? null;
      $re = $media[$kind]['rejected'] ?? null;
      $missing = !empty($media[$kind]['missing']);
      $showReject = $re !== null && $pe === null
        && ($ap === null || (int) $re['id'] > (int) $ap['id']);
    ?>
    <div class="media-slot">
      <h3><?= e($meta['label']) ?></h3>

      <div class="media-state">
        <?php if ($ap !== null): ?>
          <figure class="media-preview">
            <img src="<?= e(url('/gioco/profilo/media/' . $kind)) ?>?v=<?= e(substr((string) $ap['sha1'], 0, 8)) ?>"
                 alt="<?= e($meta['label']) ?> attuale" loading="lazy">
            <figcaption><span class="pill ok">approvato</span></figcaption>
          </figure>
        <?php endif; ?>

        <?php if ($pe !== null): ?>
          <figure class="media-preview">
            <img src="<?= e(url('/gioco/profilo/media/' . $kind)) ?>?v=<?= e(substr((string) $pe['sha1'], 0, 8)) ?>"
                 alt="<?= e($meta['label']) ?> in attesa" loading="lazy">
            <figcaption><span class="pill warn">in attesa</span></figcaption>
          </figure>
        <?php endif; ?>

        <?php if ($ap === null && $pe === null && !$missing): ?>
          <p class="hint">Nessuna immagine: al suo posto compare il tuo stemma.</p>
        <?php endif; ?>
      </div>

      <?php if ($missing): ?>
        <p class="media-note"><span class="pill err">non disponibile</span>
           L'immagine approvata non è più sul server. Ricaricala qui sotto.</p>
      <?php endif; ?>

      <?php if ($showReject): ?>
        <p class="media-note"><span class="pill err">respinta</span>
           <?= e((string) ($re['review_note'] ?: 'Non conforme alle linee guida.')) ?></p>
      <?php endif; ?>

      <form method="post" action="<?= e(url('/gioco/profilo/media')) ?>" enctype="multipart/form-data" class="media-up">
        <?= csrf_field() ?>
        <input type="hidden" name="kind" value="<?= e($kind) ?>">
        <input type="file" name="file" accept="image/png,image/jpeg,image/webp,image/gif" required>
        <button type="submit" class="btn xs"><?= $pe !== null ? 'Sostituisci' : 'Carica' ?></button>
      </form>

      <?php if ($pe !== null || $ap !== null): ?>
      <form method="post" action="<?= e(url('/gioco/profilo/media/rimuovi')) ?>" class="inline"
            onsubmit="return confirm('Rimuovere questa immagine?')">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) ($pe['id'] ?? $ap['id']) ?>">
        <button type="submit" class="btn xs ghost">Rimuovi<?= $pe !== null ? ' la richiesta' : '' ?></button>
      </form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<script src="<?= e(asset('js/profile.js')) ?>" defer></script>
