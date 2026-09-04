<?php
/** @var array<string,mixed> $player */
/** @var list<array<string,mixed>> $entries */
/** @var int $unread */
/** @var int $next_before */
?>
<section class="statusbar">
  <div><span class="k">Giornale di bordo</span><span class="v"><?= e($player['handle']) ?></span></div>
  <div><a href="<?= e(url('/gioco')) ?>">← Plancia</a></div>
  <div><a href="<?= e(url('/gioco/rotte')) ?>">Rotte</a></div>
</section>

<section class="panel">
  <h1>Giornale di bordo</h1>
  <p class="hint">Rapporti automatici del computer di bordo: scontri, pericoli ambientali,
     contatti NPC, comunicazioni diplomatiche, colonie e contratti. Aggiornato in tempo reale.</p>

  <?php if ($entries === []): ?>
    <p class="hint">Nessuna voce registrata. Il giornale si popola man mano che qualcosa accade alla nave.</p>
  <?php else: ?>
  <ol class="shiplog-full">
    <?php foreach ($entries as $ev): ?>
      <li class="sl-entry sl-<?= e($ev['severity']) ?><?= $ev['read_at'] === null ? ' sl-new' : '' ?>">
        <div class="sl-meta">
          <span class="sl-chan"><?= e(\App\Game\ShipLog::channel($ev['kind'])) ?></span>
          <?php if (!empty($ev['sector_id'])): ?><span class="sl-sec">settore <?= (int) $ev['sector_id'] ?></span><?php endif; ?>
          <time><?= e(fmt_dt($ev['created_at'])) ?></time>
        </div>
        <p class="sl-title"><?= e($ev['title']) ?></p>
        <?php foreach (preg_split('/\n/', (string) $ev['body']) as $ln): $ln = trim((string) $ln); ?>
          <?php if ($ln !== '' && $ln !== $ev['title']): ?><p class="sl-body"><?= e($ln) ?></p><?php endif; ?>
        <?php endforeach; ?>
      </li>
    <?php endforeach; ?>
  </ol>
  <?php if ($next_before > 0): ?>
    <p><a class="btn xs ghost" href="<?= e(url('/gioco/giornale?before=' . $next_before)) ?>">Voci più vecchie</a></p>
  <?php endif; ?>
  <?php endif; ?>
</section>
