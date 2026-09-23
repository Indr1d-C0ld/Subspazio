<?php /** @var array<string,mixed> $user */ ?>
<section class="panel narrow">
  <h1>Account in attesa</h1>
  <?php if (($user['status'] ?? '') === 'suspended'): ?>
    <p>Il tuo account (<strong>@<?= e($user['username']) ?></strong>) risulta <strong>sospeso</strong>.
       Contatta un amministratore per il ripristino.</p>
  <?php else: ?>
    <p>Ci sei quasi, <strong>@<?= e($user['username']) ?></strong>.</p>
    <p>Manca solo la conferma dell'indirizzo e-mail: apri il collegamento che ti
       abbiamo spedito e il tuo account si attiva da sé. Nessuno deve approvarlo.</p>
    <p class="hint">Non lo trovi? Controlla la posta indesiderata, oppure fattelo
       rispedire qui sotto.</p>
    <p class="hint">Hai sbagliato a scrivere l'indirizzo? Non devi fare niente:
       un'iscrizione non confermata decade da sola dopo
       <?= (int) \App\Auth\Auth::pendingTtlDays() ?> giorni dall'ultimo collegamento
       spedito, e il nome utente torna libero. A quel punto puoi iscriverti di nuovo.</p>

    <form method="post" action="<?= e(url('/rinvia-verifica')) ?>" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="email" value="<?= e($user['email'] ?? '') ?>">
      <button type="submit" class="btn">Rispedisci il collegamento</button>
    </form>
  <?php endif; ?>
  <form method="post" action="<?= e(url('/logout')) ?>" class="stack">
    <?= csrf_field() ?>
    <button type="submit" class="btn ghost">Esci</button>
  </form>
</section>
