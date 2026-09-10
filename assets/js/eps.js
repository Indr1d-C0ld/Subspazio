/* SubSpazio — griglia di potenza (EPS): enhancement dei campi numerici con
   +/- , budget live, anteprima effetti. Funziona anche senza JS: i campi
   restano <input type=number> e il server valida la somma. */
(function () {
  'use strict';
  var form = document.getElementById('eps-form');
  if (!form) return;

  var TOTAL = +form.dataset.total || 8;
  var NOMINAL = +form.dataset.nominal || 2;
  var MAX = +form.dataset.max || 4;
  var STEP = +form.dataset.step || 12.5;

  var chs = [].slice.call(form.querySelectorAll('.eps-ch'));
  var usedEl = document.getElementById('eps-used');
  var applyBtn = document.getElementById('eps-apply');
  var warn = document.getElementById('eps-warn');
  var balanceBtn = document.getElementById('eps-balance');

  function s(p) { return (p > 0 ? '+' : '') + p + '%'; }
  function pct(pips) { return Math.round((pips - NOMINAL) * STEP); }

  var effects = {
    shields: function (p) { return p === 0 ? 'nominale' : 'capacità e carica scudi ' + s(p); },
    weapons: function (p) { return p === 0 ? 'nominale' : 'potenza di fuoco ' + s(p); },
    engines: function (p) {
      return p >= 24 ? 'salti −1 turno (scafi da 2+ turni/warp)'
        : (p <= -24 ? 'salti +1 turno' : 'nessun effetto sui salti (serve ±2 tacche)');
    },
    sensors: function (p) {
      return p >= 24 ? 'scanner potenziato di un livello'
        : (p <= -24 ? 'scanner declassato di un livello' : 'nessun effetto sui sensori (serve ±2 tacche)');
    }
  };

  function clampInput(inp) {
    var v = parseInt(inp.value, 10);
    if (isNaN(v)) v = NOMINAL;
    v = Math.max(0, Math.min(MAX, v));
    inp.value = v;
    return v;
  }

  function render() {
    var vals = chs.map(function (c) { return clampInput(c.querySelector('[data-role=input]')); });
    var used = vals.reduce(function (a, b) { return a + b; }, 0);
    chs.forEach(function (c, i) {
      var p = vals[i], pc = pct(p);
      var m = c.querySelector('[data-role=mult]');
      m.textContent = s(pc);
      m.className = 'eps-mult' + (pc > 0 ? ' up' : (pc < 0 ? ' down' : ''));
      var fn = effects[c.dataset.key] || function () { return ''; };
      c.querySelector('[data-role=effect]').textContent = fn(pc);
      var st = c.querySelector('.eps-stepper');
      if (st) {
        st.querySelector('button:first-child').disabled = p <= 0;
        st.querySelector('button:last-child').disabled = p >= MAX || used >= TOTAL;
      }
    });
    usedEl.textContent = used;
    usedEl.parentElement.classList.toggle('over', used !== TOTAL);
    var ok = used === TOTAL;
    applyBtn.disabled = !ok;
    if (warn) warn.hidden = ok;
  }

  // +/- affiancati a ogni campo
  chs.forEach(function (c) {
    var inp = c.querySelector('[data-role=input]');
    var wrap = document.createElement('div');
    wrap.className = 'eps-stepper';
    var dec = document.createElement('button');
    dec.type = 'button'; dec.className = 'btn xs'; dec.textContent = '−'; dec.setAttribute('aria-label', 'Meno');
    var inc = document.createElement('button');
    inc.type = 'button'; inc.className = 'btn xs'; inc.textContent = '+'; inc.setAttribute('aria-label', 'Più');
    inp.parentNode.insertBefore(wrap, inp);
    wrap.appendChild(dec); wrap.appendChild(inp); wrap.appendChild(inc);
    dec.addEventListener('click', function () { inp.value = clampInput(inp) - 1; render(); });
    inc.addEventListener('click', function () { inp.value = clampInput(inp) + 1; render(); });
  });

  form.addEventListener('input', render);
  if (balanceBtn) {
    balanceBtn.addEventListener('click', function () {
      chs.forEach(function (c) { c.querySelector('[data-role=input]').value = NOMINAL; });
      render();
    });
  }
  render();
})();
