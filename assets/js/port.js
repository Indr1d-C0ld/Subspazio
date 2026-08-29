// SubSpazio — contrattazione al porto (offerta / controproposta).
(() => {
  'use strict';
  const card = document.querySelector('.port-card');
  if (!card) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const urls = {
    open: card.dataset.haggleUrl,
    offer: card.dataset.offerUrl,
    accept: card.dataset.acceptUrl,
    abort: card.dataset.abortUrl,
  };

  const panel = document.getElementById('haggle-panel');
  const elTitle = document.getElementById('hg-title');
  const elLine = document.getElementById('hg-line');
  const elOffer = document.getElementById('hg-offer');
  const elResult = document.getElementById('hg-result');
  const btnSend = document.getElementById('hg-send');
  const btnAccept = document.getElementById('hg-accept');
  const btnQuit = document.getElementById('hg-quit');

  let token = null;
  let ctx = {};

  const post = async (url, body) => {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
      body: JSON.stringify(body || {}),
    });
    return res.json();
  };

  const fmt = (n) => new Intl.NumberFormat('it-IT').format(n);

  function showPanel() { panel.hidden = false; panel.scrollIntoView({ block: 'nearest' }); }
  function setLine(j) {
    const verb = ctx.action === 'buy' ? 'Chiede' : 'Offre';
    elLine.textContent =
      `Round ${j.round}/${j.max_rounds} · ${ctx.qty}× ${ctx.label} · ` +
      `${verb}: ${fmt(j.port_offer)} cr (${j.port_unit} cr/u) · equo ≈ ${fmt(j.fair_total)} cr` +
      (j.final ? ' · offerta FINALE' : '');
  }

  card.querySelectorAll('.haggle-btn').forEach((b) => {
    b.addEventListener('click', async () => {
      const form = b.closest('form');
      const qty = parseInt(form.querySelector('input[name=qty]').value, 10) || 1;
      ctx = { commodity: b.dataset.commodity, action: b.dataset.action, label: b.dataset.label, qty };
      elResult.textContent = '';
      const j = await post(urls.open, { commodity: ctx.commodity, action: ctx.action, qty });
      if (!j.ok) { elResult.textContent = j.error || 'Impossibile aprire la trattativa.'; showPanel(); return; }
      token = j.token;
      elTitle.textContent = `${ctx.qty}× ${ctx.label} (${ctx.action === 'buy' ? 'acquisto' : 'vendita'})`;
      elOffer.value = j.port_offer;
      setLine(j);
      showPanel();
      elOffer.focus();
    });
  });

  btnSend.addEventListener('click', async () => {
    if (!token) return;
    const offer = parseInt(elOffer.value, 10);
    if (!offer || offer < 1) return;
    const j = await post(urls.offer, { token, offer });
    handleResponse(j);
  });

  btnAccept.addEventListener('click', async () => {
    if (!token) return;
    handleResponse(await post(urls.accept, { token }));
  });

  btnQuit.addEventListener('click', async () => {
    if (token) await post(urls.abort, { token });
    token = null;
    panel.hidden = true;
  });

  function handleResponse(j) {
    if (!j.ok) { elResult.textContent = j.error || 'Errore.'; return; }
    if (j.result === 'accepted') {
      elResult.textContent =
        `Affare fatto: ${fmt(j.qty)}× ${ctx.label} per ${fmt(j.total)} cr ` +
        `(${j.unit} cr/u, equo ${fmt(j.fair_total)}, ${j.rounds} round). Ricarico…`;
      token = null;
      setTimeout(() => location.reload(), 1200);
      return;
    }
    if (j.result === 'walk') {
      elResult.textContent = j.message || 'Il mercante si e\' tirato indietro.';
      token = null;
      setTimeout(() => location.reload(), 1500);
      return;
    }
    // counter | final
    setLine(j);
    elResult.textContent = j.final ? 'Ultimo giro: accetta o lascia.' : 'Il porto ha ritoccato. Rilancia o accetta.';
  }
})();
