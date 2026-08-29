<?php /** @var array<string,mixed> $user */ ?>
<section class="panel narrow">
  <h1>Account in attesa</h1>
  <?php if (($user['status'] ?? '') === 'suspended'): ?>
    <p>Il tuo account (<strong>@<?= e($user['username']) ?></strong>) risulta <strong>sospeso</strong>.
       Contatta un amministratore per il ripristino.</p>
  <?php else: ?>
    <p>Grazie per la registrazione, <strong>@<?= e($user['username']) ?></strong>.</p>
    <p>Il tuo accesso e' in attesa di approvazione. Riceverai conferma appena un
       amministratore avra' validato l'account; nel frattempo l'accesso al gioco
       resta bloccato.</p>
  <?php endif; ?>
  <form method="post" action="<?= e(url('/logout')) ?>" class="stack">
    <?= csrf_field() ?>
    <button type="submit" class="btn ghost">Esci</button>
  </form>
</section>
