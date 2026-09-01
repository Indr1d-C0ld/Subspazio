<?php
/** @var array<string,mixed> $stats */
/** @var array<string, list<array<string,mixed>>> $config */
/** @var list<array<string,mixed>> $players */
$C = static fn (string $k = '') => e(url('/admin/gioco')) . ($k ? '#' . $k : '');
?>
<section class="panel">
  <h1>Controllo gioco</h1>
  <nav class="admin-tabs">
    <a href="#stats">Statistiche</a> ·
    <a href="#config">Configurazione</a> ·
    <a href="#eventi">Eventi</a> ·
    <a href="#npc">NPC</a> ·
    <a href="#universo">Universo</a> ·
    <a href="#stagione">Stagione</a> ·
    <a href="#giocatori">Giocatori</a> ·
    <a href="<?= e(url('/admin')) ?>">← Utenti</a>
  </nav>
</section>

<section class="panel" id="stats">
  <h2>Statistiche</h2>
  <div class="grid tight">
    <div class="card"><span class="k">Giocatori</span><span class="v"><?= (int) $stats['players'] ?></span></div>
    <div class="card"><span class="k">Online (10m)</span><span class="v"><?= (int) $stats['online'] ?></span></div>
    <div class="card"><span class="k">Utenti in attesa</span><span class="v"><?= (int) $stats['users_pending'] ?></span></div>
    <div class="card"><span class="k">Corporazioni</span><span class="v"><?= (int) $stats['corps'] ?></span></div>
    <div class="card"><span class="k">Pianeti</span><span class="v"><?= (int) $stats['planets'] ?></span></div>
    <div class="card"><span class="k">Scambi 24h</span><span class="v"><?= (int) $stats['trades_today'] ?></span></div>
    <div class="card"><span class="k">Combattimenti 24h</span><span class="v"><?= (int) $stats['combats_today'] ?></span></div>
    <div class="card"><span class="k">Settori · Porti</span><span class="v"><?= (int) $stats['sectors'] ?> · <?= (int) $stats['ports'] ?></span></div>
  </div>
  <p class="hint">
    NPC:
    <?php foreach ($stats['npc'] as $n): ?><?= e($n['kind']) ?> <?= (int) $n['c'] ?> · <?php endforeach; ?>
    Più ricco: <?= $stats['richest'] ? e($stats['richest']['handle']) . ' (' . number_format((int) $stats['richest']['credits'], 0, ',', '.') . ' cr)' : '—' ?> ·
    Top rating: <?= $stats['top_rating'] ? e($stats['top_rating']['handle']) : '—' ?> ·
    Universo generato: <?= e(fmt_dt($stats['universe_at'])) ?>
  </p>
</section>

<section class="panel" id="eventi">
  <h2>Eventi</h2>
  <p>Attivi:
    <?php if ($stats['events'] === []): ?><span class="hint">nessuno</span><?php endif; ?>
    <?php foreach ($stats['events'] as $ev): ?>
      <span class="pill warn"><?= e($ev['title']) ?>
        <form method="post" action="<?= e(url('/admin/gioco/evento')) ?>" class="inline">
          <?= csrf_field() ?><input type="hidden" name="op" value="end"><input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
          <button class="link" type="submit">×</button>
        </form>
      </span>
    <?php endforeach; ?>
  </p>
  <form method="post" action="<?= e(url('/admin/gioco/evento')) ?>" class="row">
    <?= csrf_field() ?>
    <label>Forza evento
      <select name="kind">
        <option value="market_shock">Shock di mercato</option>
        <option value="solar_flare">Brillamento solare</option>
        <option value="ferrengi_incursion">Incursione Ferrengi</option>
        <option value="pirate_surge">Ondata pirateria</option>
        <option value="bounty_season">Stagione taglie</option>
      </select>
    </label>
    <button class="btn xs" type="submit">Scatena</button>
  </form>
</section>

<section class="panel" id="npc">
  <h2>NPC</h2>
  <div class="upg-grid">
    <form method="post" action="<?= e(url('/admin/gioco/npc')) ?>" class="row">
      <?= csrf_field() ?>
      <label>Spawn <select name="kind"><option value="ferrengi">Ferrengi</option><option value="pirate">Pirati</option><option value="trader">Mercanti</option></select></label>
      <label>Quanti <input type="number" name="n" min="1" max="50" value="5" class="qty"></label>
      <button class="btn xs" type="submit">Spawn</button>
    </form>
    <form method="post" action="<?= e(url('/admin/gioco/npc')) ?>" class="row" onsubmit="return confirm('Eliminare gli NPC?')">
      <?= csrf_field() ?><input type="hidden" name="op" value="purge">
      <label>Purga <select name="kind"><option value="all">tutti</option><option value="ferrengi">Ferrengi</option><option value="pirate">Pirati</option><option value="trader">Mercanti</option></select></label>
      <button class="btn xs danger" type="submit">Purga</button>
    </form>
  </div>
</section>

<section class="panel" id="universo">
  <h2>Universo</h2>
  <form method="post" action="<?= e(url('/admin/gioco/bigbang')) ?>" class="row"
        onsubmit="return confirm('BIG BANG: rigenera universo e porti, riporta tutti allo StarDock. Procedere?')">
    <?= csrf_field() ?>
    <label>Conferma (digita <code>SUBSPAZIO</code>) <input type="text" name="confirm" autocomplete="off"></label>
    <button class="btn xs danger" type="submit">BIG BANG</button>
  </form>
  <p class="hint">Il drift di mercato e la rigenerazione porti sono anche in <code>bin/console.php</code>.</p>
</section>

<section class="panel" id="stagione">
  <h2>Stagione</h2>
  <p>In corso: <strong>#<?= (int) $season['number'] ?> — <?= e($season['name']) ?></strong> (dal <?= e(fmt_date($season['started_at'])) ?>).</p>
  <p class="hint">Chiudendo la stagione: snapshot della top <?= e((string) \App\Game\GameConfig::int('season.snapshot_top', 25)) ?> nell'albo, poi
     tutti i comandanti ripartono da zero (crediti/turni/esperienza), navi e pianeti azzerati.
     I traguardi restano. Universo rigenerato solo se spunti l'opzione.</p>
  <form method="post" action="<?= e(url('/admin/gioco/stagione')) ?>" class="row"
        onsubmit="return confirm('Chiudere la stagione <?= (int) $season['number'] ?>? Reset globale.')">
    <?= csrf_field() ?>
    <label>Conferma (digita <code>CHIUDI</code>) <input type="text" name="confirm" autocomplete="off"></label>
    <label class="chk"><input type="checkbox" name="regen" value="1"> rigenera anche l'universo</label>
    <button class="btn xs danger" type="submit">Chiudi stagione</button>
  </form>
</section>

<section class="panel" id="config">
  <h2>Configurazione di gioco</h2>
  <?php foreach ($config as $group => $rows): ?>
    <details class="cfg-group">
      <summary><?= e($group) ?> <span class="hint">(<?= count($rows) ?>)</span></summary>
      <table class="tbl compact">
        <tbody>
        <?php foreach ($rows as $r): $changed = $r['default_value'] !== null && $r['cvalue'] !== $r['default_value']; ?>
          <tr<?= $changed ? ' class="row-current"' : '' ?>>
            <td><code><?= e($r['ckey']) ?></code></td>
            <td>
              <form method="post" action="<?= e(url('/admin/gioco/config')) ?>" class="inline">
                <?= csrf_field() ?><input type="hidden" name="key" value="<?= e($r['ckey']) ?>">
                <?php if ($r['ctype'] === 'bool'): ?>
                  <select name="value">
                    <option value="1"<?= in_array(strtolower((string) $r['cvalue']), ['1','true','yes','on'], true) ? ' selected' : '' ?>>true</option>
                    <option value="0"<?= in_array(strtolower((string) $r['cvalue']), ['1','true','yes','on'], true) ? '' : ' selected' ?>>false</option>
                  </select>
                <?php elseif (in_array($r['ctype'], ['int','float'], true)): ?>
                  <input type="number" step="<?= $r['ctype'] === 'float' ? 'any' : '1' ?>" name="value" value="<?= e($r['cvalue']) ?>" class="qty">
                <?php else: ?>
                  <input type="text" name="value" value="<?= e($r['cvalue']) ?>">
                <?php endif; ?>
                <button class="btn xs" type="submit">Salva</button>
              </form>
              <?php if ($changed): ?>
                <form method="post" action="<?= e(url('/admin/gioco/config')) ?>" class="inline">
                  <?= csrf_field() ?><input type="hidden" name="op" value="reset"><input type="hidden" name="key" value="<?= e($r['ckey']) ?>">
                  <button class="btn xs ghost" type="submit" title="default: <?= e($r['default_value']) ?>">↺</button>
                </form>
              <?php endif; ?>
            </td>
            <td class="hint"><?= e($r['ctype']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </details>
  <?php endforeach; ?>
</section>

<section class="panel" id="giocatori">
  <h2>Giocatori</h2>
  <table class="tbl compact">
    <thead><tr><th>Handle</th><th>Utente</th><th>Stato</th><th class="ta-r">Crediti</th><th class="ta-r">Turni</th><th class="ta-r">Sett.</th><th class="ta-r">Rating</th><th>Ultimo accesso</th><th>Azioni</th></tr></thead>
    <tbody>
    <?php foreach ($players as $p): ?>
      <tr>
        <td><strong><?= e($p['handle']) ?></strong></td>
        <td>@<?= e($p['username']) ?><?= $p['role'] === 'admin' ? ' <span class="pill mut">admin</span>' : '' ?></td>
        <td><span class="pill <?= $p['status'] === 'active' ? 'ok' : 'err' ?>"><?= e($p['status']) ?></span></td>
        <td class="ta-r"><?= number_format((int) $p['credits'], 0, ',', '.') ?></td>
        <td class="ta-r"><?= (int) $p['turns'] ?></td>
        <td class="ta-r"><?= (int) $p['sector_id'] ?></td>
        <td class="ta-r"><?= number_format((int) $p['rating'], 0, ',', '.') ?></td>
        <td class="nowrap"><?= e(fmt_dt($p['last_seen_at'])) ?></td>
        <td class="nowrap">
          <?php if ($p['role'] !== 'admin'): ?>
          <form method="post" action="<?= e(url('/admin/gioco/giocatore')) ?>" class="inline">
            <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $p['user_id'] ?>"><input type="hidden" name="op" value="kick">
            <button class="btn xs" type="submit">Kick</button>
          </form>
          <?php if ($p['status'] === 'active'): ?>
            <form method="post" action="<?= e(url('/admin/gioco/giocatore')) ?>" class="inline">
              <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $p['user_id'] ?>"><input type="hidden" name="op" value="suspend">
              <button class="btn xs danger" type="submit">Sospendi</button>
            </form>
          <?php else: ?>
            <form method="post" action="<?= e(url('/admin/gioco/giocatore')) ?>" class="inline">
              <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $p['user_id'] ?>"><input type="hidden" name="op" value="activate">
              <button class="btn xs" type="submit">Riattiva</button>
            </form>
          <?php endif; ?>
          <details class="inline-details">
            <summary class="btn xs ghost">Altro</summary>
            <div class="mod-more">
              <form method="post" action="<?= e(url('/admin/gioco/giocatore')) ?>" class="row">
                <?= csrf_field() ?><input type="hidden" name="player_id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="op" value="teleport">
                <label>Teletrasporta a <input type="number" name="sector" value="1" class="qty"></label>
                <button class="btn xs" type="submit">Vai</button>
              </form>
              <form method="post" action="<?= e(url('/admin/gioco/giocatore')) ?>" class="row">
                <?= csrf_field() ?><input type="hidden" name="player_id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="op" value="adjust">
                <label>Δ crediti <input type="number" name="credits" value="0" class="qty"></label>
                <label>turni (vuoto=lascia) <input type="number" name="turns" value="" class="qty"></label>
                <button class="btn xs" type="submit">Applica</button>
              </form>
              <form method="post" action="<?= e(url('/admin/gioco/giocatore')) ?>" class="row" onsubmit="return confirm('Azzerare il comandante <?= e($p['handle']) ?>? (verra' ricreato al prossimo accesso)')">
                <?= csrf_field() ?><input type="hidden" name="player_id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="op" value="reset">
                <button class="btn xs danger" type="submit">Azzera comandante</button>
              </form>
            </div>
          </details>
          <?php else: ?><span class="hint">—</span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
