<?php /** @var string $token */ ?>
<section class="panel narrow">
  <h1>Nuova password</h1>

  <form method="post" action="<?= e(url('/reimposta')) ?>" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <label>
      <span>Password (min. <?= (int) App\Auth\Auth::minPasswordLength() ?> caratteri)</span>
      <span class="pw-wrap">
        <input type="password" name="password" autocomplete="new-password"
               minlength="<?= (int) App\Auth\Auth::minPasswordLength() ?>"
               autocapitalize="none" autocorrect="off" spellcheck="false" required autofocus>
        <button type="button" class="pw-toggle" aria-label="Mostra password" title="Mostra/nascondi">👁</button>
      </span>
    </label>
    <label>
      <span>Ripeti la password</span>
      <input type="password" name="password_confirm" autocomplete="new-password"
             minlength="<?= (int) App\Auth\Auth::minPasswordLength() ?>"
             autocapitalize="none" autocorrect="off" spellcheck="false" required>
    </label>
    <button type="submit" class="btn">Salva la nuova password</button>
  </form>
</section>
