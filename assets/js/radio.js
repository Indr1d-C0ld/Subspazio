// SubSpazio — radio: mostra il campo "A" solo per i messaggi privati.
(() => {
  'use strict';
  const ch = document.getElementById('rc-channel');
  const wrap = document.getElementById('rc-target-wrap');
  if (!ch || !wrap) return;
  const sync = () => { wrap.hidden = ch.value !== 'private'; };
  ch.addEventListener('change', sync);
  sync();
})();
