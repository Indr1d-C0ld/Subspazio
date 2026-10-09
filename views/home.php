<?php
/** @var array<string,mixed>|null $user */
/** @var array<string,mixed> $stats */
?>
<section class="hero">
  <h1><?= e(config('app.name', 'SubSpazio')) ?></h1>
  <p class="lead">
    Una galassia persistente da attraversare a turni, erede della door per BBS
    <em>TradeWars</em>: commercio, combattimento, esplorazione e colonie, con altri
    comandanti in tempo reale. Vicino a Sol si impara il mestiere; più ti spingi verso
    l'Orlo, più i porti pagano, più il bottino è raro — e più è facile non tornare.
  </p>

  <?php if ($user === null): ?>
    <p class="actions">
      <?php if (($stats['registration'] ?? 'verify') !== 'closed'): ?>
        <a class="btn" href="<?= e(url('/registrati')) ?>">Iscriviti</a>
      <?php endif; ?>
      <a class="btn ghost" href="<?= e(url('/login')) ?>">Accedi</a>
    </p>
    <p class="hint">
      <?php if (($stats['registration'] ?? 'verify') === 'closed'): ?>
        Le iscrizioni sono chiuse in questo momento.
      <?php else: ?>
        Iscrizione libera: ricevi un collegamento per confermare l'indirizzo e la nave è pronta allo StarDock.
      <?php endif; ?>
    </p>
  <?php elseif (($user['status'] ?? '') !== 'active'): ?>
    <p class="actions">
      <a class="btn" href="<?= e(url('/attesa')) ?>">Stato del tuo account</a>
    </p>
  <?php else: ?>
    <p class="actions">
      <a class="btn" href="<?= e(url('/gioco')) ?>">Entra in plancia</a>
    </p>
  <?php endif; ?>
</section>

<section class="grid">
  <div class="card">
    <span class="k">Stagione</span>
    <span class="v"><?= $stats['season'] !== null ? e((string) $stats['season']) : '—' ?></span>
  </div>
  <div class="card">
    <span class="k">Settori</span>
    <span class="v"><?= is_numeric($stats['sectors'] ?? null) ? number_format((int) $stats['sectors'], 0, ',', '.') : '—' ?></span>
  </div>
  <div class="card">
    <span class="k">Comandanti attivi</span>
    <span class="v"><?= e($stats['players'] ?? '—') ?></span>
  </div>
</section>

<section class="roadmap">
  <h2>Cosa ti aspetta</h2>
  <ul>
    <li><strong>Una galassia a fasce</strong> — mille settori collegati da warp, alcuni a senso unico. Attorno a Sol e allo StarDock, protetti dalla Federazione, cinque fasce di rischio: dalla Cintura di Sol, dove i predoni ti razziano ma non ti distruggono, fino all'Orlo del Buio, dove le flotte Ferrengi superano i centomila caccia.</li>
    <li><strong>Commercio</strong> — porti con prezzi di domanda e offerta e contrattazione a offerta e controproposta; i porti lontani pagano fino al 20% in più. Banca a interesse, mercato nero, contratti e taglie fra comandanti.</li>
    <li><strong>Combattimento</strong> — scontri fra navi a caccia e scudi, assalti a porti e pianeti, mine e caccia schierati, replay round per round. Predoni, Ferrengi, flotte che combattono insieme e comandanti d'élite con bottino garantito.</li>
    <li><strong>La legge</strong> — chi aggredisce civili accumula notorietà, che cresce coi recidivi: pattuglie federali, squadre d'intercettazione tarate sulla tua nave, StarDock chiuso. E una taglia sulla testa che paga il ricercato stesso: chi lo abbatte la incassa, confiscata dai suoi crediti a bordo e poi dalla sua banca. Un ricercato al verde non rende nulla, e la nave di soccorso gratuita, abbattuta, non vale né esperienza né bottino. I mercantili viaggiano scortati, fuggono e chiamano soccorso.</li>
    <li><strong>Navi e moduli</strong> — dodici scafi e un catalogo di 88 moduli in 18 famiglie (cannoni, hangar, corazza, schermi, interdizione, transponder, fabbriche di caccia…), da Civile a Precursore, con affissi casuali: due moduli uguali non lo sono mai.</li>
    <li><strong>Bottino</strong> — moduli, consumabili monouso, reperti da rivendere o da raccogliere in collezioni, progetti che sbloccano ricette d'Officina speciali. Più lontano da Sol, più ricco e più raro.</li>
    <li><strong>Esplorazione</strong> — scansione di relitti, depositi, anomalie e giacimenti; pericoli ambientali all'ingresso; occultamento con una riserva di energia, che può sempre essere scoperto; un Codex delle scoperte.</li>
    <li><strong>Equipaggio</strong> — ufficiali con sei ruoli, abilità attive, lealtà e missioni a terra con esiti dal trionfo al disastro.</li>
    <li><strong>Pianeti e corporazioni</strong> — siluri Genesi, sette tipi di mondo, coloni, Citadel e cannone Quasar; corporazioni con cassa e pianeti condivisi, alleanze.</li>
    <li><strong>Fazioni</strong> — quattro potenze con reputazione a cinque livelli, rivalità, empori e cacciatori di taglie.</li>
    <li><strong>Stagioni e traguardi</strong> — classifica e albo d'oro a ogni stagione; oltre cento traguardi a livelli, alcuni segreti, con contatori di carriera che restano e titoli onorifici da mostrare accanto al nome.</li>
    <li><strong>Sempre in contatto</strong> — notifiche e mappa in tempo reale, radio a canali, giornale di bordo e rapporto di rientro; si gioca dal telefono come dal computer, anche come app installata.</li>
    <li><strong>La tua plancia</strong> — sei temi grafici ispirati alla fantascienza: dalla plancia LCARS della Flotta Stellare al terminale a fosfori della Nostromo, dall'HUD arancione di un abitacolo al vetro delle navi della Cintura, fino alle insegne al neon di una città sotto la pioggia. Cambiano colori, caratteri, forme e mappa stellare; il gioco resta lo stesso.</li>
  </ul>
</section>

<section class="panel" id="temi">
  <h2><span class="sec-ic">🎨</span> Scegli la tua plancia</h2>
  <p class="hint">Provali subito: un clic e questa pagina cambia aspetto. Da comandante la scelta resta sul tuo account, su ogni dispositivo; si cambia quando vuoi dal Profilo o dal menu in fondo a ogni pagina.</p>
  <?= partial('temi_scelta', ['torna' => '/#temi']) ?>
</section>
