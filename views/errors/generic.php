<?php
/** @var int $status */
/** @var string $message */
?>
<section class="panel narrow ta-c">
  <h1 class="big"><?= (int) $status ?></h1>
  <p><?= e($message) ?></p>
  <p><a class="btn ghost" href="<?= e(url('/')) ?>">Torna alla plancia</a></p>
</section>
