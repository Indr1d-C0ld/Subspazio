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
    <p class="hint">Gli account vengono attivati manualmente da un amministratore.</p>
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
  <ul>
    <li><strong>Universo &amp; navigazione</strong> — settori con warp (sensi unici, vicoli ciechi), Federazione protetta, StarDock, fog-of-war, autopilota, turni giornalieri.</li>
    <li><strong>Economia</strong> — porti con prezzi dinamici domanda/offerta, contrattazione a offerta/controproposta, banca IGB, mercato nero.</li>
    <li><strong>Combattimento</strong> — cantiere e hardware, scontri fra navi, assalto ai porti, mine e caccia dispiegati, capsula di salvataggio, gradi e allineamento.</li>
    <li><strong>Pianeti</strong> — siluri Genesi, tipi M/K/O/L/C/H/U, coloni e produzione, Citadel, cannone Quasar, assalto planetario.</li>
    <li><strong>Mondo vivo</strong> — classifiche, radio subspaziale, NPC Ferrengi/pirati/mercanti, eventi globali.</li>
    <li><strong>Meta</strong> — stagioni con ladder e albo d'oro, traguardi, corporazioni e alleanze, contratti e taglie fra giocatori.</li>
    <li><strong>Tecnologia</strong> — aggiornamenti in tempo reale (mappa live, avvisi), installabile come app, interfaccia web moderna.</li>
  </ul>
</section>
