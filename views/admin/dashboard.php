<?php
/** @var array<string,mixed> $counts */
/** @var list<array<string,mixed>> $pending */
/** @var list<array<string,mixed>> $recent */
/** @var list<array<string,mixed>> $audit */
$badge = static fn (string $s): string => match ($s) {
    'active' => 'ok', 'pending' => 'warn', 'suspended' => 'err', default => 'mut',
};
?>
<section class="panel">
  <h1>Amministrazione</h1>
  <p><a class="btn xs" href="<?= e(url('/admin/gioco')) ?>">Pannello di controllo del gioco →</a></p>

  <div class="grid tight">
    <div class="card"><span class="k">In attesa</span><span class="v"><?= (int) ($counts['pending'] ?? 0) ?></span></div>
    <div class="card"><span class="k">Attivi</span><span class="v"><?= (int) ($counts['active'] ?? 0) ?></span></div>
    <div class="card"><span class="k">Sospesi</span><span class="v"><?= (int) ($counts['suspended'] ?? 0) ?></span></div>
    <div class="card"><span class="k">Totale</span><span class="v"><?= (int) ($counts['total'] ?? 0) ?></span></div>
  </div>
</section>

<section class="panel">
  <h2>Richieste in attesa</h2>
  <?php if ($pending === []): ?>
    <p class="hint">Nessuna richiesta da valutare.</p>
  <?php else: ?>
    <table class="tbl">
      <thead><tr><th>Utente</th><th>Email</th><th>Richiesta</th><th class="ta-r">Azioni</th></tr></thead>
      <tbody>
      <?php foreach ($pending as $p): ?>
        <tr>
          <td>@<?= e($p['username']) ?></td>
          <td><?= e($p['email']) ?></td>
          <td><?= e(fmt_dt($p['created_at'])) ?></td>
          <td class="ta-r nowrap">
            <form method="post" action="<?= e(url('/admin/utenti/' . $p['id'] . '/approva')) ?>" class="inline">
              <?= csrf_field() ?><button class="btn xs">Approva</button>
            </form>
            <form method="post" action="<?= e(url('/admin/utenti/' . $p['id'] . '/rifiuta')) ?>" class="inline"
                  onsubmit="return confirm('Rifiutare ed eliminare la richiesta di @<?= e($p['username']) ?>?')">
              <?= csrf_field() ?><button class="btn xs danger">Rifiuta</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<section class="panel">
  <h2>Utenti recenti</h2>
  <table class="tbl">
    <thead><tr><th>Utente</th><th>Email</th><th>Stato</th><th>Ruolo</th><th>Ultimo accesso</th><th class="ta-r">Azioni</th></tr></thead>
    <tbody>
    <?php foreach ($recent as $r): ?>
      <tr>
        <td>@<?= e($r['username']) ?></td>
        <td><?= e($r['email']) ?></td>
        <td><span class="pill <?= $badge((string) $r['status']) ?>"><?= e($r['status']) ?></span></td>
        <td><?= e($r['role']) ?></td>
        <td><?= e(fmt_dt($r['last_login_at'])) ?></td>
        <td class="ta-r nowrap">
          <?php if ($r['role'] !== 'admin'): ?>
            <?php if (in_array($r['status'], ['pending', 'suspended'], true)): ?>
              <form method="post" action="<?= e(url('/admin/utenti/' . $r['id'] . '/approva')) ?>" class="inline">
                <?= csrf_field() ?><button class="btn xs">Attiva</button>
              </form>
            <?php endif; ?>
            <?php if (in_array($r['status'], ['active', 'pending'], true)): ?>
              <form method="post" action="<?= e(url('/admin/utenti/' . $r['id'] . '/sospendi')) ?>" class="inline">
                <?= csrf_field() ?><button class="btn xs danger">Sospendi</button>
              </form>
            <?php endif; ?>
          <?php else: ?>
            <span class="hint">—</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>

<section class="panel">
  <h2>Log attivita'</h2>
  <table class="tbl compact">
    <thead><tr><th>#</th><th>Azione</th><th>Attore</th><th>Target</th><th>Quando</th></tr></thead>
    <tbody>
    <?php foreach ($audit as $a): ?>
      <tr>
        <td><?= (int) $a['id'] ?></td>
        <td><code><?= e($a['action']) ?></code></td>
        <td><?= e($a['actor'] ?? 'sistema') ?></td>
        <td><?= e($a['target_id'] ?? '—') ?></td>
        <td><?= e(fmt_dt($a['created_at'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
