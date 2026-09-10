/* SubSpazio — editor del profilo: anteprima live di colore/stemma/motto.
   Progressive enhancement: senza JS il form funziona comunque (salva al submit). */
(function () {
  'use strict';
  var panel = document.getElementById('profile-panel');
  if (!panel) return;
  var preview = panel.querySelector('.id-preview .idchip');

  function currentColor() {
    var c = panel.querySelector('input[name="color"]:checked');
    return c ? c.value : null;
  }

  panel.addEventListener('change', function (ev) {
    var t = ev.target;
    if (!t || !t.name) return;
    var color = currentColor();
    if (t.name === 'color' && color) {
      if (preview) {
        var dot = preview.querySelector('.crest');
        var name = preview.querySelector('.idchip-name');
        if (dot) dot.style.setProperty('--crest-color', color);
        if (name) name.style.color = color;
      }
      panel.querySelectorAll('.crest-grid .crest').forEach(function (el) {
        el.style.setProperty('--crest-color', color);
      });
    }
    if (t.name === 'crest' && preview) {
      var use = preview.querySelector('use');
      if (use) use.setAttribute('href', '#crest-' + t.value);
    }
  });

  var motto = panel.querySelector('input[name="motto"]');
  if (motto && preview) {
    motto.addEventListener('input', function () {
      var m = preview.querySelector('.idchip-motto');
      var v = motto.value.trim();
      if (v === '') { if (m) m.remove(); return; }
      if (!m) {
        m = document.createElement('span');
        m.className = 'idchip-motto';
        preview.querySelector('.idchip-txt').appendChild(m);
      }
      m.textContent = '“' + v + '”';
    });
  }
})();
