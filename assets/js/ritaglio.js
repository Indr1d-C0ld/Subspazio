/* Inquadratura dell'immagine prima di caricarla.
 *
 * Il server sa gia' fare un ritaglio quadrato centrato (MediaAsset::reencode).
 * Indovina bene quasi sempre, ma e' pur sempre un indovinare: su una foto di
 * gruppo, o su una inquadrata storta, taglia la persona sbagliata.
 *
 * Qui la si fa scegliere. Si apre l'immagine nel browser, la si mostra dentro
 * il riquadro delle misure esatte dell'avatar, e la si sposta e ingrandisce
 * finche' non e' dove deve stare. Al momento di inviare, il riquadro prende il
 * posto del file scelto: quello che parte e' gia' ritagliato.
 *
 * SENZA JAVASCRIPT non cambia niente: parte il file com'e' e il server fa il
 * suo ritaglio centrato, come ha sempre fatto. Vale anche per i browser senza
 * canvas o senza DataTransfer.
 *
 * Portato da Atlantik (assets/js/ritaglio.js), senza il filtro d'epoca.
 */
(function () {
  'use strict';

  if (typeof HTMLCanvasElement === 'undefined' || !window.DataTransfer) { return; }

  Array.prototype.forEach.call(
    document.querySelectorAll('input[type=file][data-ritaglio]'),
    function (campo) { collega(campo, parseInt(campo.dataset.ritaglio, 10) || 512); }
  );

  function collega(campo, LATO) {
    var modulo = campo.form;
    if (!modulo) { return; }

    var immagine = null;
    var scala = 1, minScala = 1, offX = 0, offY = 0;
    var inCorso = false;

    var scatola = document.createElement('div');
    scatola.className = 'ritaglio';
    scatola.hidden = true;
    scatola.innerHTML =
      '<div class="ritaglio-tela">' +
        '<canvas width="' + LATO + '" height="' + LATO + '"></canvas>' +
      '</div>' +
      '<div class="ritaglio-cmd">' +
        '<label class="lab">ingrandisci ' +
          '<input type="range" min="100" max="300" value="100" data-zoom aria-label="Ingrandimento">' +
        '</label>' +
        '<button type="button" class="btn xs ghost" data-centra>Ricentra</button>' +
      '</div>' +
      '<p class="hint">Trascina per spostare, la barra per ingrandire. Viene caricato ' +
        'esattamente quello che vedi nel riquadro.</p>';

    // Il riquadro va in fondo al modulo, sotto il bottone: il file si sceglie
    // prima, si inquadra dopo.
    modulo.appendChild(scatola);

    var tela = scatola.querySelector('canvas');
    var ctx  = tela.getContext('2d');
    var zoom = scatola.querySelector('[data-zoom]');

    /** Il riquadro non deve mai mostrare bordi vuoti: l'immagine lo copre tutto. */
    function limita() {
      var l = immagine.width * scala, h = immagine.height * scala;
      offX = Math.min(0, Math.max(LATO - l, offX));
      offY = Math.min(0, Math.max(LATO - h, offY));
    }

    function disegna() {
      if (!immagine) { return; }
      // Si pulisce invece di riempire: se l'immagine ha trasparenze, restano
      // trasparenti anche nel file che parte.
      ctx.clearRect(0, 0, LATO, LATO);
      limita();
      ctx.drawImage(immagine, offX, offY, immagine.width * scala, immagine.height * scala);
    }

    function centra() {
      // Il riquadro copre il lato corto; si parte alzati, come fa il server:
      // in un ritratto la testa sta in alto.
      minScala = Math.max(LATO / immagine.width, LATO / immagine.height);
      scala = minScala;
      zoom.value = 100;
      offX = (LATO - immagine.width * scala) / 2;
      offY = Math.min(0, (LATO - immagine.height * scala) * 0.18);
      disegna();
    }

    campo.addEventListener('change', function () {
      campo.dataset.pronto = '';
      var f = campo.files && campo.files[0];
      if (!f) { scatola.hidden = true; immagine = null; return; }
      var lettore = new FileReader();
      lettore.onload = function () {
        var im = new Image();
        im.onload = function () {
          immagine = im;
          scatola.hidden = false;
          centra();
        };
        // Un file illeggibile non e' un errore da mostrare qui: si lascia
        // partire com'e' e sara' il server a dire che non va bene.
        im.onerror = function () { immagine = null; scatola.hidden = true; };
        im.src = lettore.result;
      };
      lettore.readAsDataURL(f);
    });

    zoom.addEventListener('input', function () {
      if (!immagine) { return; }
      var prima = scala;
      scala = minScala * (parseInt(zoom.value, 10) / 100);
      // Si ingrandisce attorno al centro del riquadro, non attorno all'angolo.
      offX = LATO / 2 - (LATO / 2 - offX) * (scala / prima);
      offY = LATO / 2 - (LATO / 2 - offY) * (scala / prima);
      disegna();
    });

    scatola.querySelector('[data-centra]').addEventListener('click', centra);

    var trascino = false, px = 0, py = 0;
    function giu(e) {
      if (!immagine) { return; }
      trascino = true;
      px = (e.touches ? e.touches[0] : e).clientX;
      py = (e.touches ? e.touches[0] : e).clientY;
    }
    function muovi(e) {
      if (!trascino || !immagine) { return; }
      var p = e.touches ? e.touches[0] : e;
      var r = tela.getBoundingClientRect();
      var k = r.width ? LATO / r.width : 1;   // la tela e' disegnata piu' piccola del suo bitmap
      offX += (p.clientX - px) * k;
      offY += (p.clientY - py) * k;
      px = p.clientX; py = p.clientY;
      disegna();
      if (e.cancelable) { e.preventDefault(); }
    }
    function su() { trascino = false; }

    tela.addEventListener('mousedown', giu);
    window.addEventListener('mousemove', muovi);
    window.addEventListener('mouseup', su);
    tela.addEventListener('touchstart', giu, { passive: true });
    tela.addEventListener('touchmove', muovi, { passive: false });
    tela.addEventListener('touchend', su);

    // All'invio, il riquadro prende il posto del file scelto.
    modulo.addEventListener('submit', function (e) {
      if (!immagine || campo.dataset.pronto === '1') { return; }
      e.preventDefault();
      if (inCorso) { return; }            // doppio clic mentre il blob si forma
      inCorso = true;
      tela.toBlob(function (blob) {
        if (blob) {
          // Il nome non conta: il server riconosce il formato dal contenuto.
          var est = blob.type === 'image/webp' ? 'webp' : 'png';
          var dt = new DataTransfer();
          dt.items.add(new File([blob], 'inquadratura.' + est, { type: blob.type }));
          campo.files = dt.files;
        }
        campo.dataset.pronto = '1';
        inCorso = false;
        modulo.submit();
      }, 'image/webp', 0.92);
    });
  }
})();
