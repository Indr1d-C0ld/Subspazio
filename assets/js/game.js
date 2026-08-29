// SubSpazio — plancia: mappa stellare interattiva + movimento via mappa.
(() => {
  'use strict';

  const host = document.getElementById('starmap');
  if (!host) return;

  const SVGNS = 'http://www.w3.org/2000/svg';
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const mapUrl = host.dataset.mapUrl;
  const moveUrl = host.dataset.moveUrl;

  const view = { x: 0, y: 0, k: 1 };
  let data = null;
  let svg = null;
  let g = null;

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

  function render() {
    host.innerHTML = '';
    const W = host.clientWidth || 600;
    const H = Math.max(360, Math.round(W * 0.72));
    const b = bounds(data.sectors);
    const sx = W / (b.maxx - b.minx);
    const sy = H / (b.maxy - b.miny);
    const scale = Math.min(sx, sy);
    const proj = (px, py) => [(px - b.minx) * scale, (py - b.miny) * scale];

    svg = document.createElementNS(SVGNS, 'svg');
    svg.setAttribute('viewBox', `0 0 ${W} ${H}`);
    svg.setAttribute('class', 'starmap-svg');
    g = document.createElementNS(SVGNS, 'g');
    svg.appendChild(g);

    const byId = {};
    for (const s of data.sectors) byId[s.id] = s;

    // warp
    const seen = new Set();
    for (const [f, t] of data.warps) {
      const a = byId[f], c = byId[t];
      if (!a || !c) continue;
      const key = f < t ? `${f}-${t}` : `${t}-${f}`;
      if (seen.has(key)) continue;
      seen.add(key);
      const [x1, y1] = proj(a.x, a.y);
      const [x2, y2] = proj(c.x, c.y);
      const line = document.createElementNS(SVGNS, 'line');
      line.setAttribute('x1', x1); line.setAttribute('y1', y1);
      line.setAttribute('x2', x2); line.setAttribute('y2', y2);
      line.setAttribute('class', 'warp-line');
      g.appendChild(line);
    }

    // adiacenti al settore corrente (destinazioni di warp da "current")
    const adj = new Set();
    for (const [f, t] of data.warps) if (f === data.current) adj.add(t);

    for (const s of data.sectors) {
      const [x, y] = proj(s.x, s.y);
      const node = document.createElementNS(SVGNS, 'g');
      node.setAttribute('class', 'sector-node');
      node.setAttribute('transform', `translate(${x},${y})`);

      const dot = document.createElementNS(SVGNS, 'circle');
      let cls = s.visited ? 'vis' : 'unk';
      if (s.id === data.current) cls = 'cur';
      if (s.stardock) cls += ' dock';
      dot.setAttribute('r', s.id === data.current ? 7 : (s.stardock ? 6 : 4.5));
      dot.setAttribute('class', 'snode ' + cls);
      dot.setAttribute('fill', s.visited || s.id === data.current ? s.color : 'transparent');
      dot.setAttribute('stroke', s.color);
      node.appendChild(dot);

      if (s.id === data.current || adj.has(s.id) || s.stardock) {
        const label = document.createElementNS(SVGNS, 'text');
        label.setAttribute('class', 'snode-label');
        label.setAttribute('x', 9);
        label.setAttribute('y', 3);
        label.textContent = s.id;
        node.appendChild(label);
      }

      const title = document.createElementNS(SVGNS, 'title');
      title.textContent = `Settore ${s.id} — ${s.name}${adj.has(s.id) ? ' (warp)' : ''}`;
      node.appendChild(title);

      if (adj.has(s.id)) {
        node.classList.add('clickable');
        node.addEventListener('click', () => move(s.id));
      }
      g.appendChild(node);
    }

    applyTransform();
    enablePanZoom();
    host.appendChild(svg);
  }

  function applyTransform() {
    g.setAttribute('transform', `translate(${view.x},${view.y}) scale(${view.k})`);
  }

  function enablePanZoom() {
    const pts = new Map();
    let last = null;      // {x,y} per il pan a un dito
    let pinch = null;     // {dist} per lo zoom a due dita
    const clampK = (k) => Math.max(0.3, Math.min(6, k));
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
        view.x += e.clientX - last.x; view.y += e.clientY - last.y;
        last = { x: e.clientX, y: e.clientY };
        applyTransform();
      } else if (pts.size === 2 && pinch) {
        const [a, b] = [...pts.values()];
        const d = dist(a, b);
        view.k = clampK(view.k * (d / pinch.dist));
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
      view.k = clampK(view.k * (e.deltaY < 0 ? 1.12 : 0.89));
      applyTransform();
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
  window.addEventListener('resize', () => { if (data) render(); });

  // esposto per il realtime (live.js): ricarica la mappa senza refresh pagina
  let reloadTimer = null;
  window.__reloadStarmap = () => {
    clearTimeout(reloadTimer);
    reloadTimer = setTimeout(load, 400);
  };
})();
