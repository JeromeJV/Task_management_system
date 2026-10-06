/* ==========================================================================
   CHARTS & ANIMATIONS (WAVE & DEPARTMENT PROGRESS)
   ========================================================================== */

const waveMonths = ["JAN","FEB","MAR","APR","MAY","JUN","JUL","AUG","SEP","OCT","NOV","DEC"];
const waveValues = [15, 18, 13, 32, 28, 35, 40, 38, 30, 25, 20, 18];
const VB_W = 700, VB_H = 260, PAD_X = 20, PAD_TOP = 20, PAD_BOTTOM = 20;
const YEAR = "2026";
let waveStartTime = null;
let waveRafId = null;

function waveX(i) {
  return PAD_X + i * ((VB_W - PAD_X * 2) / (waveMonths.length - 1));
}

function waveY(v) {
  const usable = VB_H - PAD_TOP - PAD_BOTTOM;
  return PAD_TOP + usable - (v / 100) * usable;
}

function catmullRomPath(points) {
  if (points.length < 2) return "";
  let d = `M ${points[0].x} ${points[0].y}`;
  for (let i = 0; i < points.length - 1; i++) {
    const p0 = points[i - 1] || points[i];
    const p1 = points[i];
    const p2 = points[i + 1];
    const p3 = points[i + 2] || p2;
    const c1x = p1.x + (p2.x - p0.x) / 6;
    const c1y = p1.y + (p2.y - p0.y) / 6;
    const c2x = p2.x - (p3.x - p1.x) / 6;
    const c2y = p2.y - (p3.y - p1.y) / 6;
    d += ` C ${c1x} ${c1y}, ${c2x} ${c2y}, ${p2.x} ${p2.y}`;
  }
  return d;
}

function drawWave(progress, wobbleT) {
  const lineEl = document.getElementById("waveLine");
  const areaEl = document.getElementById("waveArea");
  if (!lineEl || !areaEl) return;

  const pts = waveValues.map((v, i) => {
    const wobble = Math.sin(wobbleT / 900 + i * 1.3) * 1.6;
    return { x: waveX(i), y: waveY(v + wobble) };
  });

  const linePath = catmullRomPath(pts);
  const areaPath = `${linePath} L ${pts[pts.length - 1].x} ${waveY(0)} L ${pts[0].x} ${waveY(0)} Z`;

  lineEl.setAttribute("d", linePath);
  areaEl.setAttribute("d", areaPath);

  const len = lineEl.getTotalLength();
  lineEl.style.strokeDasharray = len;
  lineEl.style.strokeDashoffset = len * (1 - progress);

  const g = document.getElementById("wavePoints");
  if (!g) return;
  g.innerHTML = "";

  pts.forEach((p, i) => {
    const isPeak = waveValues[i] === Math.max(...waveValues);
    if (isPeak) {
      const ring = document.createElementNS("http://www.w3.org/2000/svg", "circle");
      ring.setAttribute("cx", p.x);
      ring.setAttribute("cy", p.y);
      ring.setAttribute("r", 6);
      ring.setAttribute("class", "wave-pulse-ring");
      g.appendChild(ring);
    }
    const c = document.createElementNS("http://www.w3.org/2000/svg", "circle");
    c.setAttribute("cx", p.x);
    c.setAttribute("cy", p.y);
    c.setAttribute("r", 5);
    c.setAttribute("class", "wave-point");
    c.style.opacity = progress > 0.9 ? 1 : 0;
    c.addEventListener("mouseenter", () => showWaveTooltip(i, p));
    c.addEventListener("mouseleave", hideWaveTooltip);
    g.appendChild(c);
  });
}

function showWaveTooltip(i, p) {
  const tip = document.getElementById("waveTooltip");
  const wrap = document.getElementById("waveWrap");
  if (!tip || !wrap) return;

  const rect = wrap.getBoundingClientRect();
  const px = (p.x / VB_W) * rect.width;
  const py = (p.y / VB_H) * rect.height;

  tip.innerHTML = `
    <div class="t-date">${waveMonths[i]} ${YEAR}</div>
    <div class="t-stat">Progress: ${waveValues[i]}%</div>
  `;
  tip.style.left = px + "px";
  tip.style.top = py + "px";
  tip.classList.add("visible");
}

function hideWaveTooltip() {
  const tip = document.getElementById("waveTooltip");
  if (tip) tip.classList.remove("visible");
}

function waveLoop(now) {
  if (waveStartTime === null) waveStartTime = now;
  const elapsed = now - waveStartTime;
  const progress = Math.min(elapsed / 1400, 1);
  const eased = 1 - Math.pow(1 - progress, 3);
  drawWave(eased, now);
  waveRafId = requestAnimationFrame(waveLoop);
}

function buildChart() {
  if (waveRafId) cancelAnimationFrame(waveRafId);
  waveStartTime = null;
  waveRafId = requestAnimationFrame(waveLoop);
}

function replayChartAnimation() {
  buildChart();
}

function replayDeptChartAnimation() {
  const els = document.querySelectorAll('.dept-grid, .dept-line, .dept-points');
  els.forEach(el => {
    el.style.animation = 'none';
    void el.offsetWidth;
    el.style.animation = '';
  });
}

function animateCount(id, target, duration) {
  const el = document.getElementById(id);
  if (!el) return;
  const start = performance.now();
  function tick(now) {
    const progress = Math.min((now - start) / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3);
    el.textContent = Math.round(eased * target);
    if (progress < 1) requestAnimationFrame(tick);
  }
  requestAnimationFrame(tick);
}

function replayStatCounts() {
  animateCount("statCompleted", 24, 1200);
  animateCount("statPending", 2, 1200);
  animateCount("statOverdue", 0, 1200);
}