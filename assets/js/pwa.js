// SubSpazio — PWA: registrazione service worker, prompt di installazione,
// permesso notifiche.
(() => {
  'use strict';
  const base = (document.querySelector('meta[name="app-base"]')?.content || '/').replace(/\/$/, '');

  if ('serviceWorker' in navigator && location.protocol === 'https:') {
    navigator.serviceWorker.register(base + '/sw.js', { scope: base + '/' }).catch(() => {});
  }

  let deferred = null;
  const installBtn = document.getElementById('pwa-install');
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferred = e;
    if (installBtn) installBtn.hidden = false;
  });
  if (installBtn) {
    installBtn.addEventListener('click', async () => {
      if (!deferred) return;
      deferred.prompt();
      await deferred.userChoice;
      deferred = null;
      installBtn.hidden = true;
    });
  }
  window.addEventListener('appinstalled', () => { if (installBtn) installBtn.hidden = true; });

  const notifBtn = document.getElementById('pwa-notif');
  if (notifBtn && 'Notification' in window) {
    const sync = () => { notifBtn.hidden = Notification.permission !== 'default'; };
    sync();
    notifBtn.addEventListener('click', () => Notification.requestPermission().then(sync));
  } else if (notifBtn) {
    notifBtn.hidden = true;
  }
})();
