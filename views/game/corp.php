<?php
/** @var array<string,mixed> $player */
/** @var array<string,mixed>|null $corp */
/** @var list<array<string,mixed>> $members */
/** @var int $cost */
/** @var bool $at_dock */
?>
<section class="panel narrow">
  <h1>Corporazione</h1>

  <?php if ($corp === null): ?>
    <p class="hint">Non sei in nessuna corporazione. Fondarne una costa <?= number_format($cost, 0, ',', '.') ?> cr.</p>
    <form method="post" action="<?= e(url('/gioco/corp/crea')) ?>" class="stack">
      <?= csrf_field() ?>
      <label><span>Nome</span><input type="text" name="name" maxlength="48" required></label>
      <label><span>Sigla (2-6)</span><input type="text" name="tag" maxlength="6" pattern="[A-Za-z0-9]{2,6}" required></label>
      <label><span>Password</span><input type="password" name="password" minlength="4" required></label>
      <button class="btn" type="submit">Fonda</button>
    </form>
    <hr>
    <form method="post" action="<?= e(url('/gioco/corp/entra')) ?>" class="stack">
      <?= csrf_field() ?>
      <label><span>Nome o sigla</span><input type="text" name="name" required></label>
      <label><span>Password</span><input type="password" name="password" required></label>
      <button class="btn ghost" type="submit">Entra</button>
    </form>
  <?php else: ?>
    <p><strong><?= e($corp['name']) ?></strong> [<?= e($corp['tag']) ?>] — ruolo: <?= e($corp['role']) ?></p>
    <div class="grid tight">
      <div class="card"><span class="k">Cassa corp</span><span class="v"><?= number_format((int) $corp['treasury'], 0, ',', '.') ?></span></div>
      <div class="card"><span class="k">Membri</span><span class="v"><?= count($members) ?></span></div>
    </div>
    <ul>
      <?php foreach ($members as $m): ?>
        <li><?= e($m['handle']) ?> <?= $m['role'] === 'ceo' ? '<span class="pill ok">CEO</span>' : '' ?></li>
      <?php endforeach; ?>
    </ul>

    <?php if ($at_dock): ?>
      <form method="post" action="<?= e(url('/gioco/corp/deposita')) ?>" class="row">
        <?= csrf_field() ?><label>Deposita <input type="number" name="amount" min="1" value="10000" class="qty"></label>
        <button class="btn xs" type="submit">Deposita</button>
      </form>
      <?php if ($corp['role'] === 'ceo'): ?>
      <form method="post" action="<?= e(url('/gioco/corp/preleva')) ?>" class="row">
        <?= csrf_field() ?><label>Preleva <input type="number" name="amount" min="1" value="10000" class="qty"></label>
        <button class="btn xs ghost" type="submit">Preleva</button>
      </form>
      <?php endif; ?>
    <?php else: ?>
      <p class="hint">Operazioni di cassa solo allo StarDock.</p>
    <?php endif; ?>

    <h2>Alleanze</h2>
    <?php if (empty($alliances)): ?>
      <p class="hint">Nessuna alleanza.</p>
    <?php else: ?>
      <ul class="note-list">
        <?php foreach ($alliances as $a): ?>
          <li>
            <strong><?= e($a['name']) ?></strong> [<?= e($a['tag']) ?>]
            <span class="pill <?= $a['status'] === 'active' ? 'ok' : 'warn' ?>"><?= e($a['status'] === 'active' ? 'alleata' : 'in attesa') ?></span>
            <?php if ($corp['role'] === 'ceo'): ?>
              <?php if ($a['status'] === 'proposed' && (int) $a['proposed_by'] !== (int) $corp['id']): ?>
                <form method="post" action="<?= e(url('/gioco/corp/alleanza')) ?>" class="inline">
                  <?= csrf_field() ?><input type="hidden" name="tag" value="<?= e($a['tag']) ?>">
                  <button class="btn xs" type="submit">Accetta</button>
                </form>
              <?php endif; ?>
              <form method="post" action="<?= e(url('/gioco/corp/alleanza')) ?>" class="inline">
                <?= csrf_field() ?><input type="hidden" name="op" value="dissolve"><input type="hidden" name="corp" value="<?= (int) $a['other_id'] ?>">
                <button class="link" type="submit">rompi</button>
              </form>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <?php if ($corp['role'] === 'ceo'): ?>
    <form method="post" action="<?= e(url('/gioco/corp/alleanza')) ?>" class="row">
      <?= csrf_field() ?>
      <label>Proponi alleanza a <input type="text" name="tag" placeholder="sigla o nome corp"></label>
      <button class="btn xs" type="submit">Proponi</button>
    </form>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/gioco/corp/esci')) ?>" class="inline" style="margin-top:1rem"
          onsubmit="return confirm('Lasciare la corporazione?')">
      <?= csrf_field() ?><button class="btn xs danger" type="submit">Lascia la corporazione</button>
    </form>
  <?php endif; ?>

  <p style="margin-top:1rem"><a class="btn ghost" href="<?= e(url('/gioco')) ?>">← Plancia</a></p>
</section>
