<?php
/** @var array<string,mixed>|null $user */
/** @var array<string,mixed> $stats */
?>
<section class="hero">
  <h1><?= e(config('app.name', 'SubSpazio')) ?></h1>
  <p class="lead">
    Una reinterpretazione moderna e multiutente della classica door per BBS
    <em>TradeWars</em>: rotte commerciali, flotte, pianeti e corporazioni in un
    universo persistente con clock interno.
  </p>

  <?php if ($user === null): ?>
    <p class="actions">
      <a class="btn" href="<?= e(url('/registrati')) ?>">Richiedi un accesso</a>
      <a class="btn ghost" href="<?= e(url('/login')) ?>">Accedi</a>
    </p>
    <p class="hint">In <strong>beta testing</strong>. Gli account vengono attivati manualmente da un amministratore.</p>
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
    <span class="k">Stato partita</span>
    <span class="v"><?= e($stats['status'] ?? 'setup') ?></span>
  </div>
  <div class="card">
    <span class="k">Settori previsti</span>
    <span class="v"><?= e($stats['sectors'] ?? '—') ?></span>
  </div>
  <div class="card">
    <span class="k">Comandanti attivi</span>
    <span class="v"><?= e($stats['players'] ?? '—') ?></span>
  </div>
</section>

<section class="roadmap">
  <h2>Cosa c'è nel gioco</h2>
  <p class="hint">Sedici sistemi, tutti già attivi in questa beta.</p>
  <ul>
    <li><strong>Universo &amp; navigazione</strong> — settori con warp (sensi unici, vicoli ciechi), Federazione protetta, StarDock, fog-of-war, autopilota, mappa stellare 3D, turni giornalieri.</li>
    <li><strong>Economia &amp; mercati</strong> — porti con prezzi dinamici domanda/offerta, contrattazione a offerta/controproposta, Banca Intergalattica a interesse composto, mercato nero.</li>
    <li><strong>Navi, hardware &amp; moduli</strong> — cantiere e permuta, potenziamenti, hardware (sonde, mine, scanner, transwarp, occultamento, siluri Genesi, laser minerario); moduli in 5 fasce di rarità da trovare, installare e potenziare.</li>
    <li><strong>Combattimento</strong> — scontri fra navi, assalto ai porti, mine e caccia dispiegati con intercettazione all'ingresso, capsula di salvataggio, gradi e allineamento, replay round per round.</li>
    <li><strong>Equipaggio</strong> — ufficiali con sei ruoli, bonus passivi e abilità attive, esperienza e lealtà, missioni away a prova di abilità.</li>
    <li><strong>Scansione &amp; frontiera</strong> — relitti, depositi, anomalie e pericoli nelle regioni profonde; scansione del settore e dei vicini, hazard e pozzi gravitazionali all'ingresso, Codex delle scoperte.</li>
    <li><strong>Fazioni &amp; reputazione</strong> — quattro potenze, reputazione a cinque livelli con rivalità, empori dedicati allo StarDock, cacciatori di taglie.</li>
    <li><strong>Industria &amp; produzione</strong> — estrazione dagli asteroidi, raffineria, ricette come lavori dell'Officina che maturano nel tempo, pianeti in modalità industria.</li>
    <li><strong>Pianeti &amp; corporazioni</strong> — siluri Genesi, sette tipi di mondo, coloni e produzione, Citadel, cannone Quasar, assalto planetario; corporazioni con cassa e pianeti condivisi e alleanze.</li>
    <li><strong>Mondo vivo</strong> — classifiche, radio subspaziale a canali, NPC Ferrengi/pirati/mercanti, eventi globali annunciati via radio.</li>
    <li><strong>Meta &amp; progressione</strong> — stagioni con ladder e albo d'oro, traguardi, contratti e taglie fra giocatori.</li>
    <li><strong>Giornale di bordo &amp; rientro</strong> — registro degli eventi della nave; al ritorno in plancia, un rapporto di cosa è maturato mentre eri via.</li>
    <li><strong>Primi passi</strong> — obiettivi guidati con ricompensa e una Guida di riferimento a tutti i sistemi.</li>
    <li><strong>Tecnologia</strong> — aggiornamenti in tempo reale (mappa live, avvisi, campanella), interfaccia responsive per telefono, tablet e desktop.</li>
  </ul>
</section>
