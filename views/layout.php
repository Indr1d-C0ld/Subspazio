<?php
/** @var string $content */
/** @var string $title */
$u = auth_user();
$errors = flash('errors');
$errors = is_array($errors) ? $errors : [];
?><!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="dark">
<?php $appName = (string) config('app.name', 'SubSpazio'); ?>
<title><?= $title === $appName ? e($appName) : e($title) . ' — ' . e($appName) ?></title>
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="app-base" content="<?= e(root_url('/')) ?>">
<meta name="theme-color" content="#070b12">
<link rel="manifest" href="<?= e(root_url('/manifest.webmanifest')) ?>">
<link rel="apple-touch-icon" href="<?= e(root_url('/assets/icons/icon-192.png')) ?>">
<link rel="icon" type="image/png" href="<?= e(root_url('/assets/icons/icon-192.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<header class="topbar">
  <a class="brand" href="<?= e(url('/')) ?>"><?= e(config('app.name', 'SubSpazio')) ?></a>
  <input type="checkbox" id="nav-toggle" class="nav-toggle">
  <label for="nav-toggle" class="nav-burger" aria-label="Menu">☰</label>
  <nav id="topnav">
    <a href="<?= e(url('/')) ?>">Home</a>
    <?php if ($u !== null): ?>
      <?php if (($u['status'] ?? '') === 'active'): ?>
        <a href="<?= e(url('/gioco')) ?>">Plancia</a>
        <?php
          $unread = 0; $unAlerts = 0; $unLog = 0;
          try {
              $pl = \App\Game\PlayerService::forUser((int) $u['id']);
              if ($pl) {
                  $unread = \App\Game\Radio::unread($pl);
                  $unAlerts = \App\Game\Live::unreadAlerts((int) $pl['id']);
                  $unLog = \App\Game\ShipLog::unread((int) $pl['id']);
              }
          } catch (\Throwable) {}
        ?>
        <a href="<?= e(url('/gioco/radio')) ?>">Radio<span class="badge" id="radio-badge"<?= $unread > 0 ? '' : ' hidden' ?>><?= (int) $unread ?></span></a>
        <a href="<?= e(url('/gioco/giornale')) ?>">Giornale<span class="badge"<?= $unLog > 0 ? '' : ' hidden' ?>><?= (int) $unLog ?></span></a>
        <a href="<?= e(url('/gioco/classifica')) ?>">Classifica</a>
        <a href="<?= e(url('/gioco/guida')) ?>">Guida</a>
        <span class="bell-wrap">
          <a href="#" id="alert-bell" title="Avvisi">🔔<span class="badge" id="alert-count"<?= $unAlerts > 0 ? '' : ' hidden' ?>><?= (int) $unAlerts ?></span></a>
        </span>
      <?php endif; ?>
      <?php if (is_admin()): ?><a href="<?= e(url('/admin')) ?>">Admin</a><?php endif; ?>
      <span class="who">@<?= e($u['username']) ?></span>
      <form method="post" action="<?= e(url('/logout')) ?>" class="inline">
        <?= csrf_field() ?>
        <button type="submit" class="link">Esci</button>
      </form>
    <?php else: ?>
      <a href="<?= e(url('/login')) ?>">Accedi</a>
      <a href="<?= e(url('/registrati')) ?>" class="cta">Registrati</a>
    <?php endif; ?>
  </nav>
</header>

<?php if ($u !== null && ($u['status'] ?? '') === 'active'): ?>
<nav class="game-nav" aria-label="Navigazione di gioco">
  <a href="<?= e(url('/gioco')) ?>">Plancia</a>
  <a href="<?= e(url('/gioco/giornale')) ?>">Giornale</a>
  <a href="<?= e(url('/gioco/porto')) ?>">Porto</a>
  <a href="<?= e(url('/gioco/cantiere')) ?>">Cantiere</a>
  <a href="<?= e(url('/gioco/banca')) ?>">Banca</a>
  <a href="<?= e(url('/gioco/moduli')) ?>">Moduli</a>
  <a href="<?= e(url('/gioco/equipaggio')) ?>">Equipaggio</a>
  <a href="<?= e(url('/gioco/missioni')) ?>">Missioni</a>
  <a href="<?= e(url('/gioco/fazioni')) ?>">Fazioni</a>
  <a href="<?= e(url('/gioco/codex')) ?>">Codex</a>
  <a href="<?= e(url('/gioco/pianeti')) ?>">Pianeti</a>
  <a href="<?= e(url('/gioco/rotte')) ?>">Rotte</a>
  <a href="<?= e(url('/gioco/battaglie')) ?>">Battaglie</a>
  <a href="<?= e(url('/gioco/contratti')) ?>">Contratti</a>
  <a href="<?= e(url('/gioco/mercato-nero')) ?>">Mercato nero</a>
  <a href="<?= e(url('/gioco/corp')) ?>">Corp</a>
  <a href="<?= e(url('/gioco/traguardi')) ?>">Traguardi</a>
</nav>
<?php endif; ?>

<main class="wrap<?= !empty($wide) ? ' wide' : '' ?>">
  <?php if ($m = flash('success')): ?>
    <div class="alert ok"><?= e($m) ?></div>
  <?php endif; ?>
  <?php if ($m = flash('error')): ?>
    <div class="alert err"><?= e($m) ?></div>
  <?php endif; ?>
  <?php if ($errors !== []): ?>
    <div class="alert err">
      <ul><?php foreach ($errors as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <?= $content ?>
</main>

<footer class="foot">
  <span><?= e(config('app.name', 'SubSpazio')) ?> · door TradeWars reimmaginata per il web</span>
  <span class="sep">·</span>
  <a href="<?= e(url('/health')) ?>">stato</a>
  <button type="button" id="pwa-install" class="link" hidden>· Installa app</button>
  <button type="button" id="pwa-notif" class="link" hidden>· Attiva notifiche</button>
</footer>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<script src="<?= e(asset('js/pwa.js')) ?>" defer></script>
<?php if (($u['status'] ?? '') === 'active'): ?><script src="<?= e(asset('js/live.js')) ?>" defer></script><?php endif; ?>
</body>
</html>
