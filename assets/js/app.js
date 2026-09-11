// SubSpazio — script di base (Fase 0).
// Progressive enhancement: il sito funziona anche senza JS.
(() => {
  'use strict';

  // Mostra/nascondi password (campo .pw-wrap nella pagina di login).
  document.addEventListener('click', (ev) => {
    const btn = ev.target instanceof Element ? ev.target.closest('.pw-toggle') : null;
    if (!btn) return;
    const inp = btn.parentElement && btn.parentElement.querySelector('input');
    if (!inp) return;
    const reveal = inp.type === 'password';
    inp.type = reveal ? 'text' : 'password';
    btn.textContent = reveal ? '🙈' : '👁';
    btn.setAttribute('aria-label', reveal ? 'Nascondi password' : 'Mostra password');
    inp.focus();
  });

  // Invio dei form: conferma/prompt dichiarativi + blocco doppi invii.
  // La CSP (script-src 'self') non ammette onsubmit inline: i form che
  // servivano una conferma usano data-confirm="testo"; quelli che
  // raccoglievano un motivo via prompt() usano data-prompt-field="name"
  // (+ data-prompt-message facoltativo).
  document.addEventListener('submit', (ev) => {
    const form = ev.target;
    if (!(form instanceof HTMLFormElement)) return;

    const confirmMsg = form.dataset.confirm;
    if (confirmMsg && !window.confirm(confirmMsg)) {
      ev.preventDefault();
      return;
    }

    const promptField = form.dataset.promptField;
    if (promptField) {
      const input = form.querySelector(`[name="${promptField}"]`);
      if (input) input.value = window.prompt(form.dataset.promptMessage || '') || '';
    }

    // Evita doppi invii (approvazioni, login…).
    const btn = form.querySelector('button[type="submit"], button:not([type])');
    if (btn) {
      setTimeout(() => { btn.disabled = true; btn.dataset.busy = '1'; }, 0);
      setTimeout(() => { btn.disabled = false; delete btn.dataset.busy; }, 4000);
    }
  });
})();
