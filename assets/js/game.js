// SubSpazio — plancia: mappa stellare interattiva + movimento via mappa.
(() => {
  'use strict';

  const host = document.getElementById('starmap');
  if (!host) return;

  const SVGNS = 'http://www.w3.org/2000/svg';
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const mapUrl = host.dataset.mapUrl;
  const moveUrl = host.dataset.moveUrl;

  const LS = {
    get labels() { return localStorage.getItem('sz_map_labels') || 'known'; },
    set labels(v) { try { localStorage.setItem('sz_map_labels', v); } catch (e) {} },
    get spread() { return parseFloat(localStorage.getItem('sz_map_spread')) || 1; },
    set spread(v) { try { localStorage.setItem('sz_map_spread', String(v)); } catch (e) {} },
  };

  const view = { x: 0, y: 0, k: 1 };
  let spread = clampSpread(LS.spread);
  let labelMode = LS.labels;               // none | adj | known
  let data = null;
  let svg = null, gRoot = null;
  let W = 600, H = 440;
  let baseProj = null;                     // (x,y) -> [px,py] con spread = 1
  let cx = 0, cy = 0;                      // centro del box, per lo "spread"

  function clampK(k) { return Math.max(0.35, Math.min(8, k)); }
  function clampSpread(s) { s = parseFloat(s); if (!isFinite(s)) s = 1; return Math.max(0.6, Math.min(3, Math.round(s * 10) / 10)); }

  // --- controlli (nel markup, non dentro host che viene svuotato) -----------
  const elLabels = document.getElementById('map-labels');
  const elSpread = document.getElementById('map-spread');
  if (elLabels) {
    elLabels.value = labelMode;
    elLabels.addEventListener('change', () => { labelMode = elLabels.value; LS.labels = labelMode; if (data) render(); });
  }
  if (elSpread) {
    elSpread.value = String(spread);
    elSpread.addEventListener('input', () => { spread = clampSpread(elSpread.value); LS.spread = spread; if (data) render(); });
  }
  document.getElementById('map-zoom-in')?.addEventListener('click', () => zoomAt(W / 2, H / 2, 1.25));
  document.getElementById('map-zoom-out')?.addEventListener('click', () => zoomAt(W / 2, H / 2, 0.8));
  document.getElementById('map-fit')?.addEventListener('click', () => { view.x = 0; view.y = 0; view.k = 1; if (data) render(); });

  async function load() {
    try {
      const res = await fetch(mapUrl, { headers: { 'Accept': 'application/json' } });
      data = await res.json();
      render();
    } catch (e) {
      host.innerHTML = '<p class="hint">Mappa non disponibile.</p>';
    }
  }

  function bounds(sectors) {
    let minx = Infinity, miny = Infinity, maxx = -Infinity, maxy = -Infinity;
    for (const s of sectors) {
      if (s.x < minx) minx = s.x; if (s.y < miny) miny = s.y;
      if (s.x > maxx) maxx = s.x; if (s.y > maxy) maxy = s.y;
    }
    if (!isFinite(minx)) { minx = miny = -10; maxx = maxy = 10; }
    const padx = (maxx - minx) * 0.08 + 20;
    const pady = (maxy - miny) * 0.08 + 20;
    return { minx: minx - padx, miny: miny - pady, maxx: maxx + padx, maxy: maxy + pady };
  }

  // posizione finale di un settore: proiezione base "allargata" attorno al centro
  function pos(s) {
    const p = baseProj(s.x, s.y);
    return [cx + (p[0] - cx) * spread, cy + (p[1] - cy) * spread];
  }

  function render() {
    host.innerHTML = '';
    W = host.clientWidth || 600;
    H = Math.max(380, Math.round(W * 0.78));

    const b = bounds(data.sectors);
    const scale = Math.min(W / (b.maxx - b.minx), H / (b.maxy - b.miny));
    baseProj = (px, py) => [(px - b.minx) * scale, (py - b.miny) * scale];
    cx = W / 2; cy = H / 2;

    svg = document.createElementNS(SVGNS, 'svg');
    svg.setAttribute('viewBox', `0 0 ${W} ${H}`);
    svg.setAttribute('class', 'starmap-svg');
    gRoot = document.createElementNS(SVGNS, 'g');
    svg.appendChild(gRoot);

    const byId = {};
    for (const s of data.sectors) byId[s.id] = s;

    // adiacenti al settore corrente (destinazioni di warp da "current")
    const adj = new Set();
    for (const [f, t] of data.warps) if (f === data.current) adj.add(t);

    // --- warp (dietro) ---
    const seen = new Set();
    for (const [f, t] of data.warps) {
      const a = byId[f], c = byId[t];
      if (!a || !c) continue;
      const key = f < t ? `${f}-${t}` : `${t}-${f}`;
      if (seen.has(key)) continue;
      seen.add(key);
      const [x1, y1] = pos(a);
      const [x2, y2] = pos(c);
      const line = document.createElementNS(SVGNS, 'line');
      line.setAttribute('x1', x1); line.setAttribute('y1', y1);
      line.setAttribute('x2', x2); line.setAttribute('y2', y2);
      const near = (f === data.current || t === data.current);
      line.setAttribute('class', near ? 'warp-line adj' : 'warp-line');
      gRoot.appendChild(line);
    }

    // sotto una certa scala effettiva ripieghiamo su "solo vicini" per non
    // impastare la mappa di etichette
    const declutter = (view.k * spread) < 0.75;

    // --- nodi (davanti) ---
    for (const s of data.sectors) {
      const [x, y] = pos(s);
      const node = document.createElementNS(SVGNS, 'g');
      node.setAttribute('class', 'sector-node');
      node.setAttribute('transform', `translate(${x},${y})`);

      const isCur = s.id === data.current;
      const isAdj = adj.has(s.id);

      const dot = document.createElementNS(SVGNS, 'circle');
      let cls = s.visited ? 'vis' : 'unk';
      if (isCur) cls = 'cur';
      if (s.stardock) cls += ' dock';
      dot.setAttribute('r', isCur ? 7 : (s.stardock ? 6 : 4.5));
      dot.setAttribute('class', 'snode ' + cls);
      dot.setAttribute('fill', s.visited || isCur ? s.color : 'transparent');
      dot.setAttribute('stroke', s.color);
      node.appendChild(dot);

      // cosa etichettare
      let showLabel = false;
      if (isCur) showLabel = true;
      else if (labelMode === 'adj') showLabel = isAdj;
      else if (labelMode === 'known') showLabel = declutter ? isAdj : true;
      // 'none' -> solo il corrente

      if (showLabel) {
        const known = s.visited || isCur;
        const label = document.createElementNS(SVGNS, 'text');
        label.setAttribute('class', 'snode-label' + (isCur ? ' cur' : (known ? '' : ' dim')));
        label.setAttribute('x', 9);
        label.setAttribute('y', 3);
        label.textContent = known && s.name ? s.name : ('#' + s.id);
        node.appendChild(label);
      }

      const title = document.createElementNS(SVGNS, 'title');
      title.textContent = `Settore ${s.id} — ${s.name}${isAdj ? ' (warp)' : ''}`;
      node.appendChild(title);

      if (isAdj) {
        node.classList.add('clickable');
        node.addEventListener('click', () => move(s.id));
      }
      gRoot.appendChild(node);
    }

    applyTransform();
    enablePanZoom();
    host.appendChild(svg);
  }

  function applyTransform() {
    // clamp morbido: tieni sempre un po' di mappa dentro la cornice
    const span = Math.max(W, H) * view.k;
    const m = 60;
    view.x = Math.max(-span + m, Math.min(W - m, view.x));
    view.y = Math.max(-span + m, Math.min(H - m, view.y));
    gRoot.setAttribute('transform', `translate(${view.x},${view.y}) scale(${view.k})`);
  }

  // zoom mantenendo fermo il punto (px,py) in coordinate viewBox
  function zoomAt(px, py, factor) {
    const k2 = clampK(view.k * factor);
    const f = k2 / view.k;
    view.x = px - (px - view.x) * f;
    view.y = py - (py - view.y) * f;
    view.k = k2;
    applyTransform();
  }

  function toViewBox(clientX, clientY) {
    const r = svg.getBoundingClientRect();
    return [(clientX - r.left) * (W / r.width), (clientY - r.top) * (H / r.height)];
  }

  function enablePanZoom() {
    const pts = new Map();
    let last = null;
    let pinch = null;   // {dist}
    const dist = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);

    svg.addEventListener('pointerdown', (e) => {
      svg.setPointerCapture(e.pointerId);
      pts.set(e.pointerId, { x: e.clientX, y: e.clientY });
      if (pts.size === 1) { last = { x: e.clientX, y: e.clientY }; }
      else if (pts.size === 2) { const [a, b] = [...pts.values()]; pinch = { dist: dist(a, b) }; last = null; }
    });
    svg.addEventListener('pointermove', (e) => {
      if (!pts.has(e.pointerId)) return;
      pts.set(e.pointerId, { x: e.clientX, y: e.clientY });
      if (pts.size === 1 && last) {
        view.x += e.clientX - last.x;
        view.y += e.clientY - last.y;
        last = { x: e.clientX, y: e.clientY };
        applyTransform();
      } else if (pts.size === 2 && pinch) {
        const [a, b] = [...pts.values()];
        const d = dist(a, b);
        const [mx, my] = toViewBox((a.x + b.x) / 2, (a.y + b.y) / 2);
        const k2 = clampK(view.k * (d / pinch.dist));
        const f = k2 / view.k;
        view.x = mx - (mx - view.x) * f;
        view.y = my - (my - view.y) * f;
        view.k = k2;
        pinch.dist = d;
        applyTransform();
      }
    });
    const up = (e) => {
      pts.delete(e.pointerId);
      if (pts.size === 1) { const [p] = [...pts.values()]; last = { x: p.x, y: p.y }; pinch = null; }
      else if (pts.size === 0) { last = null; pinch = null; }
    };
    svg.addEventListener('pointerup', up);
    svg.addEventListener('pointercancel', up);

    svg.addEventListener('wheel', (e) => {
      e.preventDefault();
      const [px, py] = toViewBox(e.clientX, e.clientY);
      zoomAt(px, py, e.deltaY < 0 ? 1.12 : 0.893);
    }, { passive: false });
  }

  async function move(to) {
    try {
      const res = await fetch(moveUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
        body: JSON.stringify({ to }),
      });
      const j = await res.json();
      if (j.ok) {
        location.reload();
      } else {
        flash(j.error || 'Movimento non riuscito.');
      }
    } catch (e) {
      flash('Errore di rete.');
    }
  }

  function flash(msg) {
    let el = document.getElementById('map-flash');
    if (!el) {
      el = document.createElement('div');
      el.id = 'map-flash';
      el.className = 'alert err';
      host.parentElement.insertBefore(el, host);
    }
    el.textContent = msg;
    setTimeout(() => el.remove(), 4000);
  }

  load();
  let rt = null;
  window.addEventListener('resize', () => { if (data) { clearTimeout(rt); rt = setTimeout(render, 150); } });

  // esposto per il realtime (live.js): ricarica la mappa senza refresh pagina
  let reloadTimer = null;
  window.__reloadStarmap = () => {
    clearTimeout(reloadTimer);
    reloadTimer = setTimeout(load, 400);
  };
})();
