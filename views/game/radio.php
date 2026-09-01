<?php
/** @var array<string,mixed> $player */
/** @var list<array<string,mixed>> $inbox */
/** @var bool $in_fed */
/** @var bool $has_corp */
$chLabel = ['radio' => 'RADIO', 'fedcomm' => 'FED', 'corp' => 'CORP', 'private' => 'PRIV', 'hail' => 'HAIL', 'system' => 'SIST'];
?>
<section class="statusbar">
  <div><span class="k">Comandante</span><span class="v"><?= e($player['handle']) ?></span></div>
  <div><span class="k">Settore</span><span class="v"><?= (int) $player['sector_id'] ?></span></div>
  <div><a href="<?= e(url('/gioco')) ?>">← Plancia</a></div>
</section>

<section class="panel">
  <h1>Radio subspaziale</h1>

  <form method="post" action="<?= e(url('/gioco/radio/invia')) ?>" class="radio-compose">
    <?= csrf_field() ?>
    <div class="row">
      <label>Canale
        <select name="channel" id="rc-channel">
          <option value="radio">Radio (galassia)</option>
          <?php if ($in_fed): ?><option value="fedcomm">Federazione</option><?php endif; ?>
          <?php if ($has_corp): ?><option value="corp">Corporazione</option><?php endif; ?>
          <option value="hail">Hail (settore)</option>
          <option value="private">Privato</option>
        </select>
      </label>
      <label id="rc-target-wrap" hidden>A <input type="text" name="target" placeholder="handle"></label>
    </div>
    <textarea name="body" maxlength="480" rows="2" placeholder="Messaggio…" required></textarea>
    <button class="btn xs" type="submit">Trasmetti</button>
  </form>

  <ul class="radio-log">
    <?php foreach ($inbox as $m): ?>
      <li class="ch-<?= e($m['channel']) ?>">
        <span class="rl-ch"><?= $chLabel[$m['channel']] ?? strtoupper($m['channel']) ?></span>
        <span class="rl-from"><?= e($m['from']) ?><?= $m['to'] ? ' (' . e($m['to']) . ')' : '' ?></span>
        <span class="rl-body"><?= e($m['body']) ?></span>
        <span class="rl-at"><?= e(fmt_dt($m['at'])) ?></span>
      </li>
    <?php endforeach; ?>
    <?php if ($inbox === []): ?><li class="hint">Nessun messaggio.</li><?php endif; ?>
  </ul>
</section>

<script src="<?= e(asset('js/radio.js')) ?>" defer></script>
