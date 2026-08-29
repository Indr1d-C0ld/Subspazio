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
      <a class="btn ghost" href="<?= e(url('/terminale')) ?>">Modalita' terminale</a>
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
  <h2>Roadmap</h2>
  <ol>
    <li><strong>Fase 0 — Fondamenta.</strong> Account, approvazione admin, clock. <em>In corso.</em></li>
    <li>Fase 1 — Universo: settori, warp, navigazione, turni.</li>
    <li>Fase 2 — Economia: porti, commercio, banca.</li>
    <li>Fase 3 — Navi, hardware, combattimento.</li>
    <li>Fase 4 — Pianeti, Genesi, Citadel.</li>
    <li>Fase 5 — Corporazioni, radio subspaziale, classifiche, Ferrengi.</li>
  </ol>
</section>
