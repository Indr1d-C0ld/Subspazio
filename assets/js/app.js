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

  // Selettori che si applicano da soli (tema grafico nel piè di pagina):
  // senza JavaScript resta il bottone «Applica» nel <noscript>. Una scelta
  // fatta (clic, tocco, Invio nella tendina aperta) si applica subito. Le
  // frecce e le lettere sul campo chiuso scorrono le voci senza inviare: su
  // alcuni browser ogni tasto genera «change», e si veniva portati via alla
  // prima; quel «change» arriva nello stesso istante del tasto, ed e' cosi'
  // che lo si riconosce. Si conferma con Invio o uscendo dal campo (non
  // passando a un'altra finestra).
  document.querySelectorAll('select[data-autoinvio]').forEach((sel) => {
    const iniziale = sel.value;
    let ultimoTasto = 0;
    let inSospeso = false;
    const invia = () => { if (sel.form && sel.value !== iniziale) sel.form.submit(); };
    sel.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        // dopo che il browser ha fissato la voce, senza impedirglielo
        setTimeout(invia, 0);
      } else if (e.key !== 'Tab' && e.key !== 'Escape') {
        ultimoTasto = Date.now();
      }
    });
    sel.addEventListener('change', () => {
      if (Date.now() - ultimoTasto < 150) {
        inSospeso = true;
      } else {
        invia();
      }
    });
    sel.addEventListener('blur', () => {
      if (inSospeso && document.hasFocus()) invia();
    });
  });
})();
