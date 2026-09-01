<?php
/** @var array<string,mixed> $player */
/** @var array<string,mixed> $ship */
/** @var array<string,mixed> $look */
/** @var bool $created */
$holdsUsed = (int) $ship['hold_ore'] + (int) $ship['hold_organics']
    + (int) $ship['hold_equipment'] + (int) $ship['hold_colonists'];
?>
<?php $rank = \App\Game\Ranks::title((int) $player['experience']); $align = \App\Game\Ranks::alignmentLabel((int) $player['alignment']); ?>
<section class="statusbar">
  <div><span class="k">Comandante</span><span class="v"><?= e($player['handle']) ?></span></div>
  <div><span class="k">Grado</span><span class="v"><?= e($rank) ?></span></div>
  <div><span class="k">Turni</span><span class="v" data-bind="turns"><?= (int) $player['turns'] ?></span></div>
  <div><span class="k">Crediti</span><span class="v" data-bind="credits"><?= number_format((int) $player['credits'], 0, ',', '.') ?></span></div>
  <div><span class="k">Nave</span><span class="v"><?= e($ship['type_name']) ?></span></div>
  <div><span class="k">Stive</span><span class="v"><?= $holdsUsed ?>/<?= (int) $ship['holds_total'] ?></span></div>
  <div><span class="k">Caccia</span><span class="v"><?= number_format((int) $ship['fighters'], 0, ',', '.') ?></span></div>
  <div><span class="k">Scudi</span><span class="v"><?= number_format((int) $ship['shields'], 0, ',', '.') ?></span></div>
  <div><span class="k">Allineamento</span><span class="v"><?= e($align) ?></span></div>
  <div><span class="k">Settore</span><span class="v" data-bind="sector"><?= (int) $look['id'] ?></span></div>
</section>

<?php foreach (($events ?? []) as $ev): ?>
  <div class="alert event-banner">⚡ <strong><?= e($ev['title']) ?></strong> — <?= e($ev['body']) ?></div>
<?php endforeach; ?>

<?php if (($ship['type_key'] ?? '') === 'escape_pod'): ?>
<section class="panel pod-notice">
  <h2>🛟 Capsula di salvataggio</h2>
  <p>La tua nave è stata distrutta e sei alla deriva in una capsula: niente armi né scudi,
     solo <strong><?= (int) $ship['holds_total'] ?> stive</strong>. Puoi comunque spostarti e commerciare in piccolo per rimetterti in sesto.</p>
  <p>
    <?php if ($look['is_stardock']): ?>
      Sei allo <strong>StarDock</strong>: al <a class="cta" href="<?= e(url('/gioco/cantiere')) ?>">Cantiere</a>
      compri una nave nuova &mdash; e se sei senza crediti, richiedi una <strong>nave di soccorso</strong> della Federazione.
    <?php else: ?>
      Raggiungi lo <strong>StarDock</strong> (usa il computer di bordo per tracciare la rotta) per rimetterti in sesto al Cantiere.
    <?php endif; ?>
  </p>
</section>
<?php endif; ?>

<?php if ($created): ?>
<section class="panel briefing">
  <h2>Briefing comandante</h2>
  <p>Benvenuto a bordo, <strong><?= e($player['handle']) ?></strong>. Sei attraccato allo
     <strong>StarDock</strong> nel settore <?= (int) $look['id'] ?> (<?= e($look['name']) ?>), a bordo di una
     <em><?= e($ship['type_name']) ?></em> con <?= (int) $ship['holds_total'] ?> stive.</p>
  <p>Hai <strong><?= (int) $player['turns'] ?> turni</strong> al giorno. Ogni salto di warp costa
     <?= (int) $ship['turns_per_warp'] ?> turno/i. Usa i pulsanti <strong>Warp</strong> per spostarti fra
     i settori adiacenti; il <strong>computer di bordo</strong> traccia le rotte verso i settori che hai
     gia' esplorato. La zona di Federazione (settori bassi) e' protetta.</p>
</section>
<?php endif; ?>

<div class="game-grid plancia-grid">
  <section class="panel sector-card">
    <header class="sector-head">
      <h1>Settore <?= (int) $look['id'] ?></h1>
      <span class="sector-name"><?= e($look['name']) ?></span>
      <?php if ($look['is_stardock']): ?><span class="tag dock">StarDock</span><?php endif; ?>
      <?php if ($look['is_fedspace']): ?><span class="tag fed">Federazione</span><?php endif; ?>
      <?php if ($look['has_port']): ?><span class="tag port">Porto</span><?php endif; ?>
    </header>

    <dl class="sector-meta">
      <div><dt>Regione</dt><dd><?= e($look['region'] ?? '—') ?></dd></div>
      <?php if (!empty($look['nebula'])): ?><div><dt>Nebulosa</dt><dd><?= e($look['nebula']) ?></dd></div><?php endif; ?>
      <div><dt>Faro</dt><dd><?= $look['beacon'] ? '“' . e($look['beacon']) . '”' : '—' ?></dd></div>
      <?php if (!empty($look['note']['label']) || !empty($look['note']['note'])): ?>
        <div><dt>Nota</dt><dd><?= $look['note']['pinned'] ? '★ ' : '' ?><strong><?= e($look['note']['label'] ?? '') ?></strong> <?= e($look['note']['note'] ?? '') ?></dd></div>
      <?php endif; ?>
    </dl>

    <?php if (!empty($look['pinned'])): ?>
      <div class="fav-nav">Preferiti:
        <?php foreach ($look['pinned'] as $f): ?>
          <a class="pill" href="<?= e(url('/gioco/rotta?to=' . $f['sector'])) ?>">★ <?= e($f['label'] ?? ('#' . $f['sector'])) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($look['players_here'])): ?>
      <p class="ships-here">Altre navi qui:
        <?= e(implode(', ', array_map(fn ($p) => $p['handle'] . ' (' . $p['ship_type'] . ')', $look['players_here']))) ?>
      </p>
    <?php endif; ?>

    <h2>Warp</h2>
    <div class="warps">
      <?php foreach ($look['warps'] as $w): ?>
        <form method="post" action="<?= e(url('/gioco/muovi')) ?>" class="inline warp-form">
          <?= csrf_field() ?>
          <input type="hidden" name="to" value="<?= (int) $w['to'] ?>">
          <button type="submit" class="btn warp<?= $w['visited'] ? '' : ' unknown' ?>"
                  title="<?= $w['visited'] ? 'Settore esplorato' : 'Settore mai visitato' ?><?= $w['return_known'] ? '' : ' · nessun warp di ritorno noto' ?>">
            → <?= (int) $w['to'] ?><?= $w['visited'] ? '' : ' *' ?>
          </button>
        </form>
      <?php endforeach; ?>
      <?php if (empty($look['warps'])): ?><p class="hint">Nessun warp in uscita.</p><?php endif; ?>
    </div>

    <?php if (!empty($look['port'])): $p = $look['port']; ?>
    <div class="port-teaser">
      <div class="port-teaser-head">
        <strong><?= e($p['name']) ?></strong>
        <span class="tag port">Classe <?= (int) $p['class'] ?> · <?= e($p['code']) ?></span>
      </div>
      <ul class="port-teaser-list">
        <?php foreach ($p['commodities'] as $c): ?>
          <li>
            <span class="cl"><?= e($c['label']) ?></span>
            <?php if ($c['mode'] === 'sell'): ?><span class="pill ok">vende</span>
            <?php else: ?><span class="pill warn">compra</span><?php endif; ?>
            <span class="pr"><?= number_format((float) $c['unit'], 2, ',', '.') ?> cr</span>
            <span class="st"><?= (int) $c['pct'] ?>%</span>
          </li>
        <?php endforeach; ?>
      </ul>
      <a class="btn xs" href="<?= e(url('/gioco/porto')) ?>">Entra nel porto</a>
      <?php if ($look['is_stardock']): ?>
        <a class="btn xs ghost" href="<?= e(url('/gioco/banca')) ?>">Banca</a>
        <a class="btn xs ghost" href="<?= e(url('/gioco/cantiere')) ?>">Cantiere</a>
        <a class="btn xs ghost" href="<?= e(url('/gioco/corp')) ?>">Corp</a>
        <a class="btn xs ghost" href="<?= e(url('/gioco/pianeti')) ?>">Coloni</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php $planets = $look['planets'] ?? []; ?>
    <?php if ($planets !== [] || (int) $ship['genesis'] > 0): ?>
    <div class="port-teaser">
      <div class="port-teaser-head"><strong>Pianeti</strong>
        <?php if ($planets !== []): ?><span class="tag">×<?= count($planets) ?></span><?php endif; ?>
      </div>
      <?php foreach ($planets as $pl): ?>
        <p class="force-line">
          <a href="<?= e(url('/gioco/pianeta/' . $pl['id'])) ?>"><?= e($pl['name']) ?></a>
          <small>(<?= e($pl['type']) ?><?= $pl['citadel'] ? ', Citadel ' . (int) $pl['citadel'] : '' ?><?= $pl['quasar'] ? ', Quasar ' . (int) $pl['quasar'] : '' ?>)</small>
          <?= $pl['mine'] ? '<span class="pill ok">tuo</span>' : ('· ' . e($pl['owner'] ?? 'disabitato')) ?>
        </p>
      <?php endforeach; ?>
      <a class="btn xs" href="<?= e(url('/gioco/pianeti')) ?>">Pianeti del settore</a>
    </div>
    <?php endif; ?>

    <?php
      $forces = $look['forces'] ?? ['fighters' => [], 'mines' => []];
      $others = $look['players_here'] ?? [];
      $npcs = $look['npcs'] ?? [];
      $hasForces = $forces['fighters'] !== [] || $forces['mines'] !== [];
    ?>
    <?php if ($hasForces || $others !== [] || $npcs !== []): ?>
    <div class="forces-box">
      <h2>Forze nel settore</h2>
      <?php foreach ($npcs as $n): ?>
        <p class="force-line">
          <span class="pill <?= $n['kind'] === 'ferrengi' ? 'err' : ($n['kind'] === 'pirate' ? 'warn' : 'mut') ?>"><?= e($n['kind']) ?></span>
          <strong><?= e($n['name']) ?></strong> — <?= number_format($n['fighters'], 0, ',', '.') ?> caccia
          <?php if (!$look['is_fedspace']): ?>
          <form method="post" action="<?= e(url('/gioco/attacca/npc')) ?>" class="inline">
            <?= csrf_field() ?><input type="hidden" name="npc" value="<?= (int) $n['id'] ?>">
            <input type="number" name="fighters" min="0" value="0" class="qty" title="0 = tutti">
            <button class="btn xs danger" type="submit">Attacca</button>
          </form>
          <?php endif; ?>
        </p>
      <?php endforeach; ?>
      <?php if ($others !== []): ?>
        <p class="ships-here">Navi:
          <?php foreach ($others as $o): ?>
            <span class="who-chip"><?= e($o['handle']) ?> <small>(<?= e($o['ship_type']) ?>)</small><?= $o['protected'] ? ' 🛡' : '' ?></span>
          <?php endforeach; ?>
        </p>
      <?php endif; ?>
      <?php foreach ($forces['fighters'] as $f): ?>
        <p class="force-line">
          <?= number_format($f['qty'], 0, ',', '.') ?> caccia
          <span class="pill <?= $f['mode'] === 'toll' ? 'warn' : ($f['mode'] === 'offensive' ? 'err' : 'ok') ?>"><?= e($f['mode']) ?></span>
          di <?= $f['mine'] ? '<strong>te</strong>' : e($f['handle']) ?>
          <?= $f['mode'] === 'toll' && $f['toll'] > 0 ? '· pedaggio ' . number_format($f['toll'], 0, ',', '.') . ' cr' : '' ?>
        </p>
      <?php endforeach; ?>
      <?php foreach ($forces['mines'] as $m): ?>
        <p class="force-line">💣 <?= number_format($m['qty'], 0, ',', '.') ?> mine <?= e($m['type']) ?> di <?= $m['mine'] ? '<strong>te</strong>' : e('#' . $m['owner_id']) ?></p>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!$look['is_fedspace']): ?>
    <details class="tools combat-tools">
      <summary>Armi e dispiegamento</summary>

      <?php if (!empty($look['can_attack'])): ?>
      <form method="post" action="<?= e(url('/gioco/attacca/nave')) ?>" class="row">
        <?= csrf_field() ?>
        <label>Attacca nave
          <select name="target" required>
            <?php foreach ($others as $o): ?>
              <option value="<?= (int) $o['id'] ?>"<?= $o['protected'] ? ' disabled' : '' ?>>
                <?= e($o['handle']) ?><?= $o['protected'] ? ' (protetto)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Caccia <input type="number" name="fighters" min="0" value="0" class="qty" title="0 = tutti"></label>
        <button class="btn xs danger" type="submit">Attacca</button>
      </form>
      <?php endif; ?>

      <?php if (!empty($look['port']) && (int) $look['port']['class'] !== 0): ?>
      <form method="post" action="<?= e(url('/gioco/attacca/porto')) ?>" class="row"
            onsubmit="return confirm('Assaltare il porto? Crollo di allineamento garantito.')">
        <?= csrf_field() ?>
        <label>Assalta il porto · Caccia <input type="number" name="fighters" min="0" value="0" class="qty" title="0 = tutti"></label>
        <button class="btn xs danger" type="submit">Assalta</button>
      </form>
      <?php endif; ?>

      <form method="post" action="<?= e(url('/gioco/dispiega/fighter')) ?>" class="row">
        <?= csrf_field() ?>
        <label>Dispiega caccia <input type="number" name="qty" min="1" value="0" class="qty"></label>
        <label>Modo
          <select name="mode">
            <option value="defensive">difensivo</option>
            <option value="offensive">offensivo</option>
            <option value="toll">pedaggio</option>
          </select>
        </label>
        <label>Pedaggio <input type="number" name="toll" min="0" value="0" class="qty"></label>
        <button class="btn xs" type="submit">Dispiega</button>
      </form>
      <form method="post" action="<?= e(url('/gioco/recupera/fighter')) ?>" class="row">
        <?= csrf_field() ?>
        <button class="btn xs ghost" type="submit">Recupera i tuoi caccia</button>
      </form>
      <form method="post" action="<?= e(url('/gioco/dispiega/mine')) ?>" class="row">
        <?= csrf_field() ?>
        <label>Mine
          <select name="type"><option value="armid">Armid</option><option value="limpet">Limpet</option></select>
        </label>
        <label>Qta <input type="number" name="qty" min="1" value="0" class="qty"></label>
        <button class="btn xs" type="submit">Dispiega</button>
      </form>
    </details>
    <?php endif; ?>

  </section>

  <aside class="game-side">
    <section class="panel side-panel">
      <h2>Comandi</h2>
      <div class="side-links">
        <?php if ($look['is_stardock']): ?>
          <a class="btn xs" href="<?= e(url('/gioco/porto')) ?>">Porto</a>
          <a class="btn xs ghost" href="<?= e(url('/gioco/banca')) ?>">Banca</a>
          <a class="btn xs ghost" href="<?= e(url('/gioco/cantiere')) ?>">Cantiere</a>
          <a class="btn xs ghost" href="<?= e(url('/gioco/pianeti')) ?>">Coloni</a>
        <?php elseif (!empty($look['port'])): ?>
          <a class="btn xs" href="<?= e(url('/gioco/porto')) ?>">Porto</a>
        <?php endif; ?>
        <a class="btn xs ghost" href="<?= e(url('/gioco/rotte')) ?>">Rotte</a>
        <a class="btn xs ghost" href="<?= e(url('/gioco/battaglie')) ?>">Battaglie</a>
        <a class="btn xs ghost" href="<?= e(url('/gioco/contratti')) ?>">Contratti</a>
        <a class="btn xs ghost" href="<?= e(url('/gioco/mercato-nero')) ?>">Mercato nero</a>
        <a class="btn xs ghost" href="<?= e(url('/gioco/corp')) ?>">Corp</a>
        <a class="btn xs ghost" href="<?= e(url('/gioco/traguardi')) ?>">Traguardi</a>
        <a class="btn xs ghost" href="<?= e(url('/gioco/classifica')) ?>">Classifica</a>
        <a class="btn xs ghost" href="<?= e(url('/gioco/albo')) ?>">Albo</a>
        <a class="btn xs ghost" href="<?= e(url('/terminale')) ?>">Terminale</a>
      </div>
    </section>

    <details class="tools" open>
      <summary>Computer di bordo</summary>
      <form method="get" action="<?= e(url('/gioco/rotta')) ?>" class="row">
        <label>Traccia rotta verso settore
          <input type="number" name="to" min="1" required>
        </label>
        <button class="btn xs" type="submit">Traccia</button>
      </form>
      <form method="post" action="<?= e(url('/gioco/faro')) ?>" class="row">
        <?= csrf_field() ?>
        <label>Faro del settore
          <input type="text" name="text" maxlength="80" value="<?= e($look['beacon'] ?? '') ?>" placeholder="testo del faro">
        </label>
        <button class="btn xs ghost" type="submit">Imposta</button>
      </form>
    </details>

    <details class="tools">
      <summary>Nota / preferito su questo settore</summary>
      <form method="post" action="<?= e(url('/gioco/settore/nota')) ?>" class="row">
        <?= csrf_field() ?>
        <input type="hidden" name="sector" value="<?= (int) $look['id'] ?>">
        <input type="hidden" name="back" value="/gioco">
        <label>Etichetta <input type="text" name="label" maxlength="32" value="<?= e($look['note']['label'] ?? '') ?>"></label>
        <label>Nota <input type="text" name="note" maxlength="255" value="<?= e($look['note']['note'] ?? '') ?>"></label>
        <label class="chk"><input type="checkbox" name="pinned" value="1" <?= !empty($look['note']['pinned']) ? 'checked' : '' ?>> preferito</label>
        <button class="btn xs" type="submit">Salva</button>
      </form>
    </details>
  </aside>

  <section class="panel map-card map-3d">
    <h2>Mappa stellare</h2>
    <div class="map-controls">
      <label>Etichette
        <select id="map-labels">
          <option value="none">solo qui</option>
          <option value="adj">qui + vicini</option>
          <option value="known">conosciute + #</option>
        </select>
      </label>
      <label class="chk"><input type="checkbox" id="map-routes" checked> rotte</label>
      <label class="chk"><input type="checkbox" id="map-explored"> solo esplorati</label>
      <label class="chk"><input type="checkbox" id="map-2d"> vista 2D</label>
      <span class="spacer"></span>
      <button type="button" class="btn xs ghost" id="map-zoom-out" aria-label="Riduci zoom">−</button>
      <button type="button" class="btn xs ghost" id="map-zoom-in" aria-label="Aumenta zoom">+</button>
      <button type="button" class="btn xs ghost" id="map-fit">Adatta</button>
    </div>
    <div class="map-orbit">
      <label>Rotazione <input type="range" id="map-yaw" min="-180" max="180" step="1" value="26" aria-label="Rotazione orizzontale"></label>
      <label>Inclinazione <input type="range" id="map-pitch" min="2" max="88" step="1" value="32" aria-label="Inclinazione verticale"></label>
      <label>Spaziatura <input type="range" id="map-spread" min="0.5" max="2.6" step="0.05" value="1" aria-label="Spaziatura fra i punti"></label>
    </div>
    <div id="starmap"
         data-map-url="<?= e(url('/api/mappa')) ?>"
         data-move-url="<?= e(url('/api/muovi')) ?>"
         data-state-url="<?= e(url('/api/stato')) ?>">
      <p class="hint">Carico la mappa…</p>
    </div>
    <p class="legend">
      <span class="dot cur"></span> qui
      <span class="dot vis"></span> esplorato
      <span class="dot unk"></span> noto
      <span class="dot dock"></span> StarDock
    </p>
    <p class="hint map-help">Trascina per ruotare · rotella per lo zoom · Shift+trascina (o due dita) per spostare · clic su un settore adiacente per muoverti · doppio clic per centrarlo.</p>
  </section>
</div>

<script src="<?= e(asset('js/game.js')) ?>" defer></script>
