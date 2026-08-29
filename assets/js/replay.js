// SubSpazio — replay round-per-round di una battaglia.
(() => {
  'use strict';
  const root = document.getElementById('replay');
  if (!root) return;

  let trace;
  try { trace = JSON.parse(root.dataset.trace); } catch (e) { return; }
  if (!Array.isArray(trace) || trace.length === 0) return;

  const att0 = Math.max(1, parseInt(root.dataset.att0, 10) || trace[0].aF || 1);
  const def0 = Math.max(1, parseInt(root.dataset.def0, 10) || trace[0].dF || 1);
  const fmt = (n) => new Intl.NumberFormat('it-IT').format(n);

  document.getElementById('rp-att-name').textContent = root.dataset.att || 'Attaccante';
  document.getElementById('rp-def-name').textContent = root.dataset.def || 'Difensore';

  const el = {
    af: document.getElementById('rp-att-f'), as: document.getElementById('rp-att-s'), abar: document.getElementById('rp-att-ftr'),
    df: document.getElementById('rp-def-f'), ds: document.getElementById('rp-def-s'), dbar: document.getElementById('rp-def-ftr'),
    round: document.getElementById('rp-round'), log: document.getElementById('rp-log'),
  };

  let i = 0;
  let timer = null;

  function apply(idx) {
    const s = trace[idx];
    el.af.textContent = fmt(s.aF); el.as.textContent = fmt(s.aS);
    el.df.textContent = fmt(s.dF); el.ds.textContent = fmt(s.dS);
    el.abar.style.width = Math.max(0, Math.min(100, (s.aF / att0) * 100)) + '%';
    el.dbar.style.width = Math.max(0, Math.min(100, (s.dF / def0) * 100)) + '%';
    el.round.textContent = 'Round ' + s.r;
    if (idx > 0) {
      const li = document.createElement('li');
      li.textContent = `Round ${s.r}: l'attaccante colpisce −${fmt(s.dHit || 0)}, il difensore colpisce −${fmt(s.aHit || 0)}`;
      el.log.appendChild(li);
      el.log.scrollTop = el.log.scrollHeight;
    }
  }

  function step() {
    if (i >= trace.length - 1) { stop(); return; }
    i++;
    apply(i);
  }
  function play() {
    if (timer) { stop(); return; }
    document.getElementById('rp-play').textContent = '⏸ Pausa';
    timer = setInterval(step, 850);
  }
  function stop() {
    clearInterval(timer); timer = null;
    document.getElementById('rp-play').textContent = '▶ Play';
  }
  function reset() {
    stop(); i = 0; el.log.innerHTML = ''; apply(0);
  }

  document.getElementById('rp-play').addEventListener('click', play);
  document.getElementById('rp-step').addEventListener('click', () => { stop(); step(); });
  document.getElementById('rp-reset').addEventListener('click', reset);
  apply(0);
})();
