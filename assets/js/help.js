/* SubSpazio — tendine d'aiuto «?»: riposiziona .help-pop come position:fixed
   così non viene mai tagliata dai bordi/overflow di una sezione. Progressive
   enhancement: senza JS resta il fallback CSS (hover/focus in-flow). */
(function () {
  'use strict';
  var root = document.documentElement;
  root.classList.add('js-help');

  var openEl = null;

  function place(help) {
    var pop = help.querySelector('.help-pop');
    if (!pop) return;
    // reset per misurare a dimensione naturale
    pop.style.left = '0px';
    pop.style.top = '0px';
    var m = 12;                       // margine minimo dal bordo viewport
    var r = help.getBoundingClientRect();
    var pw = pop.offsetWidth;
    var ph = pop.offsetHeight;
    var vw = window.innerWidth;
    var vh = window.innerHeight;

    // orizzontale: centra sul «?», poi clampa
    var left = r.left + r.width / 2 - pw / 2;
    left = Math.max(m, Math.min(left, vw - pw - m));

    // verticale: sotto se c'è spazio, altrimenti sopra
    var below = r.bottom + 8;
    var top = (below + ph <= vh - m) ? below : Math.max(m, r.top - ph - 8);

    pop.style.left = Math.round(left) + 'px';
    pop.style.top = Math.round(top) + 'px';
  }

  function open(help) {
    if (openEl && openEl !== help) close();
    openEl = help;
    help.classList.add('is-open');
    place(help);
  }

  function close() {
    if (!openEl) return;
    openEl.classList.remove('is-open');
    var pop = openEl.querySelector('.help-pop');
    if (pop) { pop.style.left = ''; pop.style.top = ''; }
    openEl = null;
  }

  function nodeOf(t) {
    return (t && t.closest) ? t.closest('.help') : null;
  }

  // pointerover/out bubblano: delega affidabile
  document.addEventListener('pointerover', function (e) {
    var h = nodeOf(e.target);
    if (h) open(h);
  });
  document.addEventListener('pointerout', function (e) {
    var h = nodeOf(e.target);
    if (h && h === openEl && !h.contains(e.relatedTarget)) close();
  });
  // fallback per browser senza Pointer Events
  document.addEventListener('mouseover', function (e) {
    var h = nodeOf(e.target);
    if (h) open(h);
  });
  document.addEventListener('mouseout', function (e) {
    var h = nodeOf(e.target);
    if (h && h === openEl && !h.contains(e.relatedTarget)) close();
  });

  document.addEventListener('focusin', function (e) {
    var h = nodeOf(e.target);
    if (h) open(h); else if (openEl) close();
  });
  document.addEventListener('focusout', function (e) {
    var h = nodeOf(e.target);
    if (h && h === openEl) close();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && openEl) close();
  });

  window.addEventListener('scroll', close, true);
  window.addEventListener('resize', close);
})();
