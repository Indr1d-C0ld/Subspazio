// SubSpazio — skin terminale: invia comandi all'API e mostra la risposta.
(() => {
  'use strict';

  const root = document.getElementById('terminal');
  if (!root) return;

  const out = document.getElementById('term-out');
  const form = document.getElementById('term-form');
  const input = document.getElementById('term-cmd');
  const promptEl = document.getElementById('term-prompt');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const cmdUrl = root.dataset.cmdUrl;

  const history = [];
  let hIdx = -1;
  let busy = false;

  function append(text) {
    out.textContent += text;
    out.scrollTop = out.scrollHeight;
    root.scrollIntoView({ block: 'end' });
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (busy) return;
    const cmd = input.value;
    append('\n' + promptEl.textContent + cmd + '\n');
    if (cmd.trim() !== '') { history.push(cmd); }
    hIdx = history.length;
    input.value = '';
    busy = true;

    try {
      const res = await fetch(cmdUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
        body: JSON.stringify({ cmd }),
      });
      const j = await res.json();
      append(j.text || '');
      if (j.prompt) promptEl.textContent = j.prompt;
    } catch (err) {
      append('  Errore di comunicazione con il server.\n');
    } finally {
      busy = false;
      input.focus();
    }
  });

  input.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowUp') {
      if (hIdx > 0) { hIdx--; input.value = history[hIdx]; e.preventDefault(); }
    } else if (e.key === 'ArrowDown') {
      if (hIdx < history.length - 1) { hIdx++; input.value = history[hIdx]; }
      else { hIdx = history.length; input.value = ''; }
      e.preventDefault();
    }
  });

  document.getElementById('terminal').addEventListener('click', () => input.focus());
})();
