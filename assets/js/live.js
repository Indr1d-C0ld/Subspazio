// SubSpazio — realtime: stream SSE, toast, campanella alert, badge radio.
(() => {
  'use strict';

  const meta = (n) => document.querySelector(`meta[name="${n}"]`)?.content || '';
  const base = (meta('app-base') || '/').replace(/\/$/, '');
  const csrf = meta('csrf-token');
  if (!base) return;

  // --- toast ----------------------------------------------------------
  let host = document.getElementById('toast-host');
  if (!host) {
    host = document.createElement('div');
    host.id = 'toast-host';
    document.body.appendChild(host);
  }
  function toast(kind, title, body, link) {
    const el = document.createElement('div');
    el.className = 'toast t-' + kind;
    el.innerHTML = `<strong></strong><span></span>`;
    el.querySelector('strong').textContent = title || '';
    el.querySelector('span').textContent = body || '';
    if (link) { el.classList.add('clickable'); el.addEventListener('click', () => { location.href = base + link; }); }
    host.appendChild(el);
    const important = ['alert', 'attacked', 'destroyed', 'npc_attack', 'planet_hit', 'entry_combat'].includes(kind);
    setTimeout(() => el.classList.add('leaving'), important ? 9000 : 5500);
    setTimeout(() => el.remove(), important ? 9600 : 6100);
  }

  // --- badge helpers -------------------------------------------------
  function bump(id) {
    const b = document.getElementById(id);
    if (!b) return;
    const n = (parseInt(b.textContent, 10) || 0) + 1;
    b.textContent = n;
    b.hidden = false;
  }

  function notify(title, body) {
    if (document.hidden && window.Notification && Notification.permission === 'granted') {
      try { new Notification(title, { body, tag: 'subspazio', icon: base + '/assets/icon-192.png' }); } catch (e) {}
    }
  }

  // --- stream ------------------------------------------------------
  let es = null;
  function connect() {
    es = new EventSource(base + '/api/stream');
    es.addEventListener('error', () => { /* EventSource ritenta da solo */ });

    const handle = (ev) => {
      let d;
      try { d = JSON.parse(ev.data); } catch (e) { return; }
      const k = d.kind;

      if (['move_in', 'move_out', 'combat', 'npc_spawn'].includes(k)) {
        if (typeof window.__reloadStarmap === 'function') window.__reloadStarmap();
        showSectorPill();
        if (d.body) toast('sector', 'Settore', d.body);
        return;
      }
      if (k === 'radio' || k === 'system') {
        bump('radio-badge');
        toast(k === 'system' ? 'event' : 'radio', d.title || 'Radio', d.body || '');
        return;
      }
      if (k === 'event') {
        toast('event', d.title || 'Evento', d.body || '');
        return;
      }
      if (k === 'alert') {
        bump('alert-count');
        toast('alert', d.title || 'Avviso', d.body || '', d.payload && d.payload.link);
        notify(d.title || 'SubSpazio', d.body || '');
        return;
      }
      // attacked / npc_attack / entry_combat / planet_hit / destroyed
      toast(k, d.title || 'Allerta', d.body || '', null);
      notify(d.title || 'SubSpazio', d.body || '');
      if (typeof window.__reloadStarmap === 'function') window.__reloadStarmap();
    };

    es.onmessage = handle;
    ['move_in', 'move_out', 'combat', 'npc_spawn', 'radio', 'system', 'event', 'alert',
     'attacked', 'npc_attack', 'entry_combat', 'planet_hit', 'destroyed', 'citadel', 'dm']
      .forEach((t) => es.addEventListener(t, handle));
  }

  function showSectorPill() {
    if (document.getElementById('sector-refresh-pill')) return;
    const card = document.querySelector('.sector-card');
    if (!card) return;
    const p = document.createElement('button');
    p.id = 'sector-refresh-pill';
    p.type = 'button';
    p.className = 'btn xs';
    p.textContent = '↻ Novità nel settore — aggiorna';
    p.addEventListener('click', () => location.reload());
    card.prepend(p);
  }

  // --- campanella ------------------------------------------------
  const bell = document.getElementById('alert-bell');
  if (bell) {
    let panel = null;
    bell.addEventListener('click', async (e) => {
      e.preventDefault();
      if (panel) { panel.remove(); panel = null; return; }
      const r = await fetch(base + '/api/alerts', { headers: { Accept: 'application/json' } });
      const j = await r.json();
      panel = document.createElement('div');
      panel.className = 'alert-panel';
      panel.innerHTML = j.items.length
        ? j.items.map((a) => `<a class="ap-item ${a.read_at ? '' : 'unread'}" href="${a.link ? base + a.link : '#'}"><strong>${esc(a.title)}</strong><span>${esc(a.body)}</span><time>${esc(fmtDt(a.created_at))}</time></a>`).join('')
        : '<div class="ap-empty">Nessun avviso.</div>';
      bell.parentElement.appendChild(panel);
      await fetch(base + '/api/alerts/letti', { method: 'POST', headers: { 'X-CSRF-Token': csrf } });
      const c = document.getElementById('alert-count');
      if (c) { c.textContent = '0'; c.hidden = true; }
    });
  }
  function esc(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
  // DATETIME del DB (già ora di Roma) -> "GG/MM/AAAA HH:MM", indipendente dal fuso del browser
  function fmtDt(s) {
    const m = String(s == null ? '' : s).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
    return m ? `${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}` : String(s == null ? '' : s);
  }

  window.SubspazioLive = {
    enableNotifications() {
      if (window.Notification && Notification.permission === 'default') Notification.requestPermission();
    },
  };

  connect();
})();
