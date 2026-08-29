// SubSpazio — script di base (Fase 0).
// Progressive enhancement: il sito funziona anche senza JS.
(() => {
  'use strict';

  // Evita doppi invii sui form (approvazioni, login…).
  document.addEventListener('submit', (ev) => {
    const form = ev.target;
    if (!(form instanceof HTMLFormElement)) return;
    const btn = form.querySelector('button[type="submit"], button:not([type])');
    if (btn) {
      setTimeout(() => { btn.disabled = true; btn.dataset.busy = '1'; }, 0);
      setTimeout(() => { btn.disabled = false; delete btn.dataset.busy; }, 4000);
    }
  });
})();
