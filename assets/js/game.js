// SubSpazio — plancia: mappa stellare 3D (canvas, senza dipendenze) + movimento.
(() => {
  'use strict';

  const host = document.getElementById('starmap');
  if (!host) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const mapUrl = host.dataset.mapUrl;
  const moveUrl = host.dataset.moveUrl;

  const FOCAL = 760;
  const CAM_DIST = 640;
  const TARGET_R = 230;          // raggio "utile" del layout dopo normalizzazione
  const DEF = { yaw: 26, pitch: 32 };
  const DEG = Math.PI / 180;

  // ---- preferenze persistenti -------------------------------------------------
  const P = {
    read(k, d) { const v = localStorage.getItem('sz_map_' + k); return v === null ? d : v; },
    write(k, v) { try { localStorage.setItem('sz_map_' + k, String(v)); } catch (e) {} },
  };
  const clamp = (v, a, b) => Math.max(a, Math.min(b, v));

  const cam = {
    yaw: (+P.read('yaw', DEF.yaw)) * DEG,
    pitch: clamp(+P.read('pitch', DEF.pitch), 2, 88) * DEG,
    zoom: clamp(+P.read('zoom', 1), 0.25, 6),
    panX: 0, panY: 0,
  };
  let spread = clamp(+P.read('spread', 1), 0.5, 2.6);
  let labelMode = P.read('labels', 'known');            // none | adj | known
  let showRoutes = P.read('routes', '1') !== '0';
  let exploredOnly = P.read('explored', '0') === '1';
  let twoD = P.read('twod', '0') === '1';

  // ---- stato mappa ----------------------------------------------------------
  let data = null;
  let nodes = [];               // {id,name,color,visited,stardock,port,fed,adj, ux,uy,uz, px,py,pz, proj}
  let byId = new Map();
  let edges = [];               // [a,b] indici in nodes (non orientati, dedup)
  let currentIdx = -1;

  let canvas = null, ctx = null, dpr = 1;
  let W = 900, H = 480;
  let raf = 0;

  // ---- controlli esterni --------------------------------------------------
  const $ = (id) => document.getElementById(id);
  const elYaw = $('map-yaw'), elPitch = $('map-pitch'), elSpread = $('map-spread');
  const elLabels = $('map-labels'), elRoutes = $('map-routes'),
        elExplored = $('map-explored'), el2d = $('map-2d');

  if (elLabels) { elLabels.value = labelMode; elLabels.addEventListener('change', () => { labelMode = elLabels.value; P.write('labels', labelMode); schedule(); }); }
  if (elRoutes) { elRoutes.checked = showRoutes; elRoutes.addEventListener('change', () => { showRoutes = elRoutes.checked; P.write('routes', showRoutes ? 1 : 0); schedule(); }); }
  if (elExplored) { elExplored.checked = exploredOnly; elExplored.addEventListener('change', () => { exploredOnly = elExplored.checked; P.write('explored', exploredOnly ? 1 : 0); schedule(); }); }
  if (el2d) { el2d.checked = twoD; el2d.addEventListener('change', () => { twoD = el2d.checked; P.write('twod', twoD ? 1 : 0); syncOrbitInputs(); schedule(); }); }
  if (elYaw) { elYaw.value = String(Math.round(cam.yaw / DEG)); elYaw.addEventListener('input', () => { cam.yaw = (+elYaw.value) * DEG; P.write('yaw', elYaw.value); schedule(); }); }
  if (elPitch) { elPitch.value = String(Math.round(cam.pitch / DEG)); elPitch.addEventListener('input', () => { cam.pitch = clamp(+elPitch.value, 2, 88) * DEG; P.write('pitch', elPitch.value); schedule(); }); }
  if (elSpread) { elSpread.value = String(spread); elSpread.addEventListener('input', () => { spread = clamp(+elSpread.value, 0.5, 2.6); P.write('spread', spread); applySpread(); schedule(); }); }
  $('map-zoom-in')?.addEventListener('click', () => zoomBy(1.25));
  $('map-zoom-out')?.addEventListener('click', () => zoomBy(0.8));
  $('map-fit')?.addEventListener('click', fit);

  function syncOrbitInputs() {
    const dis = twoD;
    [elYaw, elPitch].forEach((el) => { if (el) el.disabled = dis; });
  }
  syncOrbitInputs();

  // ---- caricamento ------------------------------------------------------------
  async function load() {
    try {
      const res = await fetch(mapUrl, { headers: { Accept: 'application/json' } });
      data = await res.json();
      build();
      ensureCanvas();
      resize();
      layout();
      applySpread();
      draw();
    } catch (e) {
      host.innerHTML = '<p class="hint">Mappa non disponibile.</p>';
    }
  }

  function build() {
    byId = new Map();
    nodes = (data.sectors || []).map((s, i) => {
      const n = {
        id: s.id, name: s.name, color: s.color || '#5b6b8c',
        visited: !!s.visited, stardock: !!s.stardock, port: !!s.has_port, fed: !!s.fedspace,
        adj: false, idx: i,
        ux: 0, uy: 0, uz: 0, px: 0, py: 0, pz: 0, proj: null,
      };
      byId.set(s.id, n);
      return n;
    });
    currentIdx = nodes.findIndex((n) => n.id === data.current);

    // archi non orientati dedup + set adiacenti al corrente
    const seen = new Set();
    edges = [];
    for (const [f, t] of (data.warps || [])) {
      if (f === data.current) { const nt = byId.get(t); if (nt) nt.adj = true; }
      if (t === data.current) { const nf = byId.get(f); if (nf) nf.adj = true; }
      const a = byId.get(f), b = byId.get(t);
      if (!a || !b) continue;
      const key = f < t ? f + '-' + t : t + '-' + f;
      if (seen.has(key)) continue;
      seen.add(key);
      edges.push([a.idx, b.idx]);
    }
  }

  // ---- layout force-directed 3D --------------------------------------------
  function hash01(n) { n = (n ^ 61) ^ (n >>> 16); n = n + (n << 3); n = n ^ (n >>> 4); n = Math.imul(n, 0x27d4eb2d); n = n ^ (n >>> 15); return ((n >>> 0) % 100000) / 100000; }

  function layout() {
    const n = nodes.length;
    if (!n) return;
    // seme: x/y dal payload (normalizzati), z pseudo-casuale stabile per id
    let mnx = Infinity, mny = Infinity, mxx = -Infinity, mxy = -Infinity;
    for (const s of data.sectors) { mnx = Math.min(mnx, s.x); mny = Math.min(mny, s.y); mxx = Math.max(mxx, s.x); mxy = Math.max(mxy, s.y); }
    const sx = (mxx - mnx) || 1, sy = (mxy - mny) || 1;
    nodes.forEach((no, i) => {
      const s = data.sectors[i];
      no.ux = ((s.x - mnx) / sx - 0.5) * 300 + (hash01(no.id * 7 + 1) - 0.5) * 20;
      no.uy = ((s.y - mny) / sy - 0.5) * 300 + (hash01(no.id * 13 + 3) - 0.5) * 20;
      no.uz = (hash01(no.id * 131 + 9) - 0.5) * 240;
    });

    const iters = n > 500 ? 90 : n > 260 ? 150 : 240;
    const kRep = 26000;          // repulsione
    const rest = 46;             // lunghezza a riposo delle molle
    const kSpring = 0.045;
    const kGrav = 0.020;
    const maxStep = 16;
    const dx = new Float64Array(n), dy = new Float64Array(n), dz = new Float64Array(n);

    for (let it = 0; it < iters; it++) {
      dx.fill(0); dy.fill(0); dz.fill(0);
      const cool = 1 - it / iters * 0.92;

      for (let i = 0; i < n; i++) {
        const a = nodes[i];
        for (let j = i + 1; j < n; j++) {
          const b = nodes[j];
          let ex = a.ux - b.ux, ey = a.uy - b.uy, ez = a.uz - b.uz;
          let d2 = ex * ex + ey * ey + ez * ez;
          if (d2 < 0.01) { ex = (hash01(i * 31 + j) - 0.5); ey = (hash01(i + j * 31) - 0.5); ez = (hash01(i * 7 + j * 3) - 0.5); d2 = 0.5; }
          const d = Math.sqrt(d2);
          const f = kRep / d2;
          const ux = ex / d, uy = ey / d, uz = ez / d;
          dx[i] += ux * f; dy[i] += uy * f; dz[i] += uz * f;
          dx[j] -= ux * f; dy[j] -= uy * f; dz[j] -= uz * f;
        }
        dx[i] -= a.ux * kGrav; dy[i] -= a.uy * kGrav; dz[i] -= a.uz * kGrav;
      }
      for (const [ia, ib] of edges) {
        const a = nodes[ia], b = nodes[ib];
        const ex = b.ux - a.ux, ey = b.uy - a.uy, ez = b.uz - a.uz;
        const d = Math.hypot(ex, ey, ez) || 0.001;
        const f = (d - rest) * kSpring;
        const ux = ex / d, uy = ey / d, uz = ez / d;
        dx[ia] += ux * f; dy[ia] += uy * f; dz[ia] += uz * f;
        dx[ib] -= ux * f; dy[ib] -= uy * f; dz[ib] -= uz * f;
      }
      for (let i = 0; i < n; i++) {
        const st = maxStep * cool;
        const L = Math.hypot(dx[i], dy[i], dz[i]) || 1;
        const c = Math.min(1, st / L);
        nodes[i].ux += dx[i] * c; nodes[i].uy += dy[i] * c; nodes[i].uz += dz[i] * c;
      }
    }

    // centra e normalizza il raggio (90° percentile) a TARGET_R
    let cxs = 0, cys = 0, czs = 0;
    for (const no of nodes) { cxs += no.ux; cys += no.uy; czs += no.uz; }
    cxs /= n; cys /= n; czs /= n;
    const rad = [];
    for (const no of nodes) { no.ux -= cxs; no.uy -= cys; no.uz -= czs; rad.push(Math.hypot(no.ux, no.uy, no.uz)); }
    rad.sort((a, b) => a - b);
    const r90 = rad[Math.floor(rad.length * 0.9)] || rad[rad.length - 1] || 1;
    const k = TARGET_R / r90;
    for (const no of nodes) { no.ux *= k; no.uy *= k; no.uz *= k; }
  }

  function applySpread() {
    for (const no of nodes) { no.px = no.ux * spread; no.py = no.uy * spread; no.pz = no.uz * spread; }
  }

  // ---- proiezione 3D -> 2D --------------------------------------------------
  function project(no) {
    const yaw = twoD ? 0 : cam.yaw;
    const pitch = twoD ? 88 * DEG : cam.pitch;
    const cyaw = Math.cos(yaw), syaw = Math.sin(yaw);
    const x1 = no.px * cyaw + no.pz * syaw;
    const z1 = -no.px * syaw + no.pz * cyaw;
    const cp = Math.cos(pitch), sp = Math.sin(pitch);
    const y2 = no.py * cp - z1 * sp;
    const z2 = no.py * sp + z1 * cp;
    const zc = z2 + CAM_DIST;
    if (zc <= 1) return null;
    const s = (FOCAL / zc) * cam.zoom;
    return { x: W / 2 + cam.panX + x1 * s, y: H / 2 + cam.panY + y2 * s, s, depth: zc };
  }

  // ---- disegno ------------------------------------------------------------
  function schedule() { if (!raf) raf = requestAnimationFrame(() => { raf = 0; draw(); }); }

  function draw() {
    if (!ctx) return;
    ctx.clearRect(0, 0, W, H);

    for (const no of nodes) no.proj = project(no);

    // fascia di profondità per lo shading
    let dmin = Infinity, dmax = -Infinity;
    for (const no of nodes) if (no.proj) { dmin = Math.min(dmin, no.proj.depth); dmax = Math.max(dmax, no.proj.depth); }
    const dspan = (dmax - dmin) || 1;
    const fade = (d) => 0.35 + 0.65 * (1 - (d - dmin) / dspan);

    const hidden = (no) => exploredOnly && !no.visited && !no.adj && no.idx !== currentIdx;

    // archi
    if (showRoutes) {
      ctx.lineWidth = 1;
      for (const [ia, ib] of edges) {
        const a = nodes[ia], b = nodes[ib];
        if (!a.proj || !b.proj || hidden(a) || hidden(b)) continue;
        const near = a.idx === currentIdx || b.idx === currentIdx;
        const al = near ? 0.6 : 0.16 * fade((a.proj.depth + b.proj.depth) / 2) + 0.05;
        ctx.strokeStyle = near ? 'rgba(107,226,255,' + al + ')' : 'rgba(130,160,210,' + al.toFixed(3) + ')';
        ctx.lineWidth = near ? 1.6 : 1;
        ctx.beginPath();
        ctx.moveTo(a.proj.x, a.proj.y);
        ctx.lineTo(b.proj.x, b.proj.y);
        ctx.stroke();
      }
    }

    // nodi (dal più lontano al più vicino)
    const order = nodes.filter((no) => no.proj && !hidden(no)).sort((x, y) => y.proj.depth - x.proj.depth);
    for (const no of order) {
      const p = no.proj;
      const isCur = no.idx === currentIdx;
      let base = isCur ? 5.5 : no.stardock ? 4.6 : no.port ? 4 : 3.2;
      const r = clamp(base * p.s, 1.2, 15);
      const a = fade(p.depth);
      ctx.beginPath();
      ctx.arc(p.x, p.y, r, 0, Math.PI * 2);
      if (no.visited || isCur) {
        ctx.fillStyle = hexA(no.color, a);
        ctx.fill();
      } else {
        ctx.fillStyle = 'rgba(10,17,32,' + (0.5 * a) + ')';
        ctx.fill();
      }
      ctx.lineWidth = isCur ? 2.5 : no.adj ? 2 : 1;
      ctx.strokeStyle = isCur ? '#6be2ff' : hexA(no.color, Math.min(1, a + 0.2));
      if (isCur) { ctx.shadowColor = '#6be2ff'; ctx.shadowBlur = 14; }
      ctx.stroke();
      ctx.shadowBlur = 0;
      if (no.adj) {
        ctx.beginPath();
        ctx.arc(p.x, p.y, r + 3.5, 0, Math.PI * 2);
        ctx.strokeStyle = 'rgba(107,226,255,' + (0.5 * a) + ')';
        ctx.lineWidth = 1;
        ctx.stroke();
      }
    }

    drawLabels(order, fade);
  }

  function drawLabels(order, fade) {
    if (labelMode === 'none' && currentIdx < 0) return;
    ctx.font = '11px ui-monospace, "DejaVu Sans Mono", monospace';
    ctx.textBaseline = 'middle';

    const rank = (no) => {
      if (no.idx === currentIdx) return 0;
      if (no.adj) return 1;
      if (no.stardock || no.port) return 2;
      if (no.visited) return 3;
      return 4;
    };
    const maxRank = labelMode === 'none' ? 0 : labelMode === 'adj' ? 1 : 4;
    const cand = order.filter((no) => no.proj && rank(no) <= maxRank && no.proj.s > 0.28)
      .sort((x, y) => rank(x) - rank(y) || y.proj.depth - x.proj.depth);

    const placed = [];
    const overlaps = (r) => placed.some((q) => !(r.x2 < q.x1 || r.x1 > q.x2 || r.y2 < q.y1 || r.y1 > q.y2));
    let budget = labelMode === 'known' ? 55 : 999;

    for (const no of cand) {
      if (budget <= 0) break;
      const known = no.visited || no.idx === currentIdx;
      const text = known && no.name ? no.name : ('#' + no.id);
      const p = no.proj;
      const w = ctx.measureText(text).width;
      const bx = p.x + 9, by = p.y;
      const box = { x1: bx - 2, y1: by - 7, x2: bx + w + 2, y2: by + 7 };
      if (box.x1 < 2 || box.x2 > W - 2 || box.y1 < 2 || box.y2 > H - 2) continue;
      if (rank(no) > 1 && overlaps(box)) continue;
      placed.push(box);
      budget--;
      const a = clamp(fade(p.depth) + 0.15, 0.4, 1);
      ctx.lineWidth = 3; ctx.strokeStyle = 'rgba(5,10,20,' + a + ')';
      ctx.lineJoin = 'round';
      ctx.strokeText(text, bx, by);
      ctx.fillStyle = no.idx === currentIdx ? '#6be2ff' : known ? 'rgba(226,234,246,' + a + ')' : 'rgba(166,183,206,' + a + ')';
      ctx.fillText(text, bx, by);
    }
  }

  function hexA(hex, a) {
    const m = /^#?([0-9a-f]{6})$/i.exec(hex || '');
    if (!m) return 'rgba(120,150,200,' + a + ')';
    const n = parseInt(m[1], 16);
    return 'rgba(' + (n >> 16 & 255) + ',' + (n >> 8 & 255) + ',' + (n & 255) + ',' + a + ')';
  }

  // ---- canvas / resize --------------------------------------------------
  function ensureCanvas() {
    if (canvas) return;
    host.innerHTML = '';
    canvas = document.createElement('canvas');
    host.appendChild(canvas);
    ctx = canvas.getContext('2d');
    wireEvents();
  }

  function resize() {
    W = host.clientWidth || 900;
    H = Math.max(460, Math.round(W * 0.6));
    host.style.height = H + 'px';
    dpr = Math.min(2, window.devicePixelRatio || 1);
    canvas.width = W * dpr; canvas.height = H * dpr;
    canvas.style.width = W + 'px'; canvas.style.height = H + 'px';
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  }

  function zoomBy(f) {
    cam.zoom = clamp(cam.zoom * f, 0.25, 6);
    P.write('zoom', cam.zoom);
    schedule();
  }

  function fit() {
    cam.yaw = DEF.yaw * DEG;
    cam.pitch = DEF.pitch * DEG;
    cam.zoom = 1; cam.panX = 0; cam.panY = 0;
    if (elYaw) elYaw.value = String(DEF.yaw);
    if (elPitch) elPitch.value = String(DEF.pitch);
    P.write('yaw', DEF.yaw); P.write('pitch', DEF.pitch); P.write('zoom', 1);
    schedule();
  }

  // ---- interazione -----------------------------------------------------
  function wireEvents() {
    const pts = new Map();
    let last = null, pinch = null, moved = 0, panMode = false;

    canvas.addEventListener('pointerdown', (e) => {
      canvas.setPointerCapture(e.pointerId);
      pts.set(e.pointerId, { x: e.clientX, y: e.clientY });
      moved = 0;
      panMode = e.shiftKey || e.button === 1 || e.button === 2 || twoD;
      host.classList.add('dragging');
      if (pts.size === 1) last = { x: e.clientX, y: e.clientY };
      else if (pts.size === 2) { const [a, b] = [...pts.values()]; pinch = { d: Math.hypot(a.x - b.x, a.y - b.y) }; last = null; }
    });

    canvas.addEventListener('pointermove', (e) => {
      if (!pts.has(e.pointerId)) return;
      pts.set(e.pointerId, { x: e.clientX, y: e.clientY });
      if (pts.size === 1 && last) {
        const dx = e.clientX - last.x, dy = e.clientY - last.y;
        moved += Math.abs(dx) + Math.abs(dy);
        last = { x: e.clientX, y: e.clientY };
        if (panMode) {
          cam.panX += dx; cam.panY += dy;
        } else {
          cam.yaw += dx * 0.01;
          cam.pitch = clamp(cam.pitch - dy * 0.01, 2 * DEG, 88 * DEG);
          if (elYaw) elYaw.value = String(Math.round(((cam.yaw / DEG) % 360 + 540) % 360 - 180));
          if (elPitch) elPitch.value = String(Math.round(cam.pitch / DEG));
        }
        schedule();
      } else if (pts.size === 2 && pinch) {
        const [a, b] = [...pts.values()];
        const d = Math.hypot(a.x - b.x, a.y - b.y);
        const mid = midToCanvas((a.x + b.x) / 2, (a.y + b.y) / 2);
        const f = d / (pinch.d || d);
        cam.panX = mid.x - W / 2 - (mid.x - W / 2 - cam.panX) * f;
        cam.panY = mid.y - H / 2 - (mid.y - H / 2 - cam.panY) * f;
        cam.zoom = clamp(cam.zoom * f, 0.25, 6);
        pinch.d = d;
        schedule();
      }
    });

    const end = (e) => {
      const wasQuick = moved < 6;
      pts.delete(e.pointerId);
      if (pts.size === 0) {
        host.classList.remove('dragging');
        last = null; pinch = null;
        P.write('yaw', Math.round(cam.yaw / DEG)); P.write('pitch', Math.round(cam.pitch / DEG));
        P.write('zoom', cam.zoom);
        if (wasQuick) hit(e, false);
      } else if (pts.size === 1) {
        const [p] = [...pts.values()]; last = { x: p.x, y: p.y }; pinch = null;
      }
    };
    canvas.addEventListener('pointerup', end);
    canvas.addEventListener('pointercancel', (e) => { pts.delete(e.pointerId); host.classList.remove('dragging'); last = null; pinch = null; });
    canvas.addEventListener('dblclick', (e) => hit(e, true));
    canvas.addEventListener('contextmenu', (e) => e.preventDefault());

    canvas.addEventListener('wheel', (e) => {
      e.preventDefault();
      const r = canvas.getBoundingClientRect();
      const mx = e.clientX - r.left, my = e.clientY - r.top;
      const f = e.deltaY < 0 ? 1.12 : 0.893;
      cam.panX = mx - W / 2 - (mx - W / 2 - cam.panX) * f;
      cam.panY = my - H / 2 - (my - H / 2 - cam.panY) * f;
      cam.zoom = clamp(cam.zoom * f, 0.25, 6);
      P.write('zoom', cam.zoom);
      schedule();
    }, { passive: false });
  }

  function midToCanvas(cx, cy) {
    const r = canvas.getBoundingClientRect();
    return { x: cx - r.left, y: cy - r.top };
  }

  function hit(e, recenter) {
    const r = canvas.getBoundingClientRect();
    const mx = e.clientX - r.left, my = e.clientY - r.top;
    let best = null, bd = 18;
    for (const no of nodes) {
      if (!no.proj) continue;
      const d = Math.hypot(no.proj.x - mx, no.proj.y - my);
      if (d < bd) { bd = d; best = no; }
    }
    if (!best) return;
    if (recenter) {
      cam.panX -= (best.proj.x - W / 2);
      cam.panY -= (best.proj.y - H / 2);
      schedule();
      return;
    }
    if (best.adj) move(best.id);
  }

  // ---- movimento -----------------------------------------------------
  async function move(to) {
    try {
      const res = await fetch(moveUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, Accept: 'application/json' },
        body: JSON.stringify({ to }),
      });
      const j = await res.json();
      if (j.ok) location.reload();
      else flash(j.error || 'Movimento non riuscito.');
    } catch (e) { flash('Errore di rete.'); }
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

  // ---- ciclo di vita ----------------------------------------------------
  load();
  let rt = null;
  window.addEventListener('resize', () => {
    if (!canvas) return;
    clearTimeout(rt);
    rt = setTimeout(() => { resize(); draw(); }, 150);
  });

  let reloadTimer = null;
  window.__reloadStarmap = () => {
    clearTimeout(reloadTimer);
    reloadTimer = setTimeout(load, 400);
  };
})();
