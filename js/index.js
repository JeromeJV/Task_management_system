/* ==========================================================================
   GLOBAL DATA DEFINITIONS
   ========================================================================== */

const employees = [
  {
    name: "Dulatre, Dominic",
    displayName: "DULATRE, DOMINIC",
    role: "Back end developer",
    dept: "I.T",
    email: "dominicdulatre@gmail.com",
    phone: "09123456789",
    address: "QC. Street Si Dominic May Ari Neto Gento",
    status: "Active",
    total: 51,
    done: 47,
    pending: 3,
    overdue: 1,
    tasks: [
      { t: "DULATRE, DOMINIC", d: "Design system architecture modules" },
      { t: "DULATRE, DOMINIC", d: "Implement core backend functions" }
    ]
  },
  {
    name: "Garcia, Joshua",
    displayName: "GARCIA, JOSHUA MIGUEL",
    role: "Graphic Designer",
    dept: "Design",
    email: "garciajoshua@gmail.com",
    phone: "09895400001",
    address: "QC. Street Si Dominic May Ari Neto Gento",
    status: "Active",
    total: 38,
    done: 35,
    pending: 2,
    overdue: 1,
    tasks: [
      { t: "GARCIA, JOSHUA", d: "Update design based on feedback" }
    ]
  },
  {
    name: "Valdepena, Harvey",
    displayName: "VALDEPENA, HARVEY JEROME",
    role: "Product Manager",
    dept: "Product",
    email: "valdepenaharvey@gmail.com",
    phone: "09469000216",
    address: "QC. Street Si Dominic May Ari Neto Gento",
    status: "Active",
    total: 58,
    done: 53,
    pending: 3,
    overdue: 2,
    tasks: [
      { t: "VALDEPEÑA, JEROME", d: "Approve database design" },
      { t: "VALDEPEÑA, JEROME", d: "Launch Facebook & Instagram ad campaign" }
    ]
  },
  {
    name: "Rala, Irish",
    displayName: "RALA, IRISH MARIE",
    role: "Marketing Specialist",
    dept: "Marketing",
    email: "ralairish@gmail.com",
    phone: "09783675823",
    address: "QC. Street Si Dominic May Ari Neto Gento",
    status: "Active",
    total: 39,
    done: 35,
    pending: 2,
    overdue: 2,
    tasks: [
      { t: "RALA, IRISH MARIE", d: "Create weekly social media content plan" },
      { t: "RALA, IRISH MARIE", d: "Write blog/article about product/service" }
    ]
  },
  {
    name: "Santos, Maria",
    displayName: "SANTOS, MARIA",
    role: "Front end developer",
    dept: "I.T",
    email: "mariasantos@gmail.com",
    phone: "09204567890",
    address: "QC. Street Si Dominic May Ari Neto Gento",
    status: "Active",
    total: 30,
    done: 27,
    pending: 2,
    overdue: 1,
    tasks: [
      { t: "SANTOS, MARIA", d: "Process new employee onboarding" }
    ]
  }
];

const applicants = [
  { name: "Walton, Herman", role: "Data Analyst", date: "03-30-26", status: "pending" },
  { name: "Singh, Priya", role: "Facility Manager", date: "01-20-26", status: "pending" },
  { name: "Briggs, Franklin", role: "Software Developer", date: "02-04-26", status: "accepted" },
  { name: "Anderson, Melissa", role: "Sales Account Executive", date: "04-02-26", status: "pending" },
  { name: "Johnson, Max", role: "Ux Designer", date: "02-08-26", status: "rejected" }
];

const tasks = [
  { who: "Rala, Irish Marie", task: "Write blog/article about product/service", dept: "Marketing", due: "04-18-26", status: "Pending" },
  { who: "Rala, Irish Marie", task: "Launch Facebook & Instagram ad campaign", dept: "Marketing", due: "04-22-26", status: "Pending" },
  { who: "Dulatre, Dominic", task: "Fix backend API rate limiting bug", dept: "I.T", due: "04-16-26", status: "Completed" },
  { who: "Garcia, Joshua", task: "Design new product packaging mockup", dept: "Design", due: "04-17-26", status: "Completed" },
  { who: "Valdepena, Harvey", task: "Review Q2 product roadmap", dept: "Product", due: "04-20-26", status: "Completed" }
];

let productionData = [
  { id: "GFCLBRZN1", name: "Batangas", dept: "", status: "Running", output: 1249, target: 1500 },
  { id: "GFNCR1", name: "Valenzuela", dept: "", status: "Maintenance", output: 432, target: 1000 },
  { id: "GFRIII1", name: "Bulacan", dept: "", status: "Running", output: 365, target: 800 }
];

let logisticData = [
  { id: "G20260501", dest: "Calamba, Laguna", status: "In transit", driver: "Marlon Francisco", eta: "2h 30m" },
  { id: "G20260503", dest: "Caloocan City", status: "Loading", driver: "David Larzon", eta: "4h 15m" },
  { id: "G20260408", dest: "Valenzuela", status: "Delivered", driver: "Larry Lee", eta: "Completed" }
];

let editingProductionIndex = null;
let editingLogisticIndex = null;


/* ==========================================================================
   NAVIGATION SYSTEM & PAGE CONTROL
   ========================================================================== */

function showPage(page) {
  const pages = ["dashboard", "task", "employee", "applicants", "production", "logistic"];
  
  pages.forEach(p => {
    const pageEl = document.getElementById(`page-${p}`);
    if (pageEl) pageEl.classList.toggle("active", p === page);

    const navBtn = document.getElementById(`nav${p.charAt(0).toUpperCase() + p.slice(1)}`);
    if (navBtn) navBtn.classList.toggle("active", p === page);
  });

  // Handle page-specific animations/renders
  if (page === "dashboard") {
    replayStatCounts();
    replayChartAnimation();
    replayDeptChartAnimation();
  }
}

// Bind Navigation Buttons (Class-based bindings)
document.addEventListener("DOMContentLoaded", () => {
  const navButtons = document.querySelectorAll(".nav-btn");
  navButtons.forEach(btn => {
    btn.addEventListener("click", () => {
      const pageTarget = btn.dataset.page;
      if (pageTarget) {
        showPage(pageTarget);
      }
    });
  });
});


/* ==========================================================================
   HR & APPLICANT MANAGEMENT RENDERERS
   ========================================================================== */

function renderEmployees() {
  const searchInput = document.getElementById("employeeSearch");
  const q = searchInput ? searchInput.value.toLowerCase() : "";
  const body = document.getElementById("employeeBody");

  const filtered = employees.filter(e =>
    e.name.toLowerCase().includes(q) || e.dept.toLowerCase().includes(q) || e.role.toLowerCase().includes(q)
  );

  if (body) {
    body.innerHTML = filtered.map((e) => `
      <tr onclick="showEmployeeDetail(${employees.indexOf(e)})">
        <td>
          <div class="emp-name">${e.name.toUpperCase()}</div>
          <div class="emp-role">${e.role}</div>
        </td>
        <td>${e.dept}</td>
      </tr>
    `).join("");
  }

  renderEmployeeCards();
}

function renderEmployeeCards() {
  const scrollContainer = document.getElementById("employeeScroll");
  const cardList = document.getElementById("employeeCards");

  if (scrollContainer) {
    scrollContainer.innerHTML = "";
    employees.forEach((emp, idx) => {
      const card = document.createElement("div");
      card.className = "employee-card";
      card.innerHTML = `<img src="" alt=""><div class="ename">${emp.name.toUpperCase()}</div>`;
      card.addEventListener("click", () => selectScrollEmployee(idx, card));
      scrollContainer.appendChild(card);
    });
    if (employees.length > 0) selectScrollEmployee(0, scrollContainer.firstChild);
  }

  if (cardList) {
    cardList.innerHTML = employees.map((e, i) => `
      <div class="employee-card ${i === 0 ? 'active' : ''}" id="emp-card-${i}" onclick="selectEmployee(${i})">
        <div class="avatar">🐐</div>
        <div class="ename">${e.name.toUpperCase()}</div>
      </div>
    `).join("");
    selectEmployee(0);
  }
}

function selectEmployee(i) {
  employees.forEach((_, idx) => {
    const card = document.getElementById(`emp-card-${idx}`);
    if (card) card.classList.toggle("active", idx === i);
  });
  showEmployeeDetail(i);
}

function selectScrollEmployee(i, cardElement) {
  document.querySelectorAll(".employee-card").forEach(c => c.classList.remove("selected"));
  if (cardElement) cardElement.classList.add("selected");

  const emp = employees[i];
  const profileCard = document.getElementById("empProfileCard");
  if (profileCard) {
    profileCard.innerHTML = `
      <div class="profile-head">
        <img src="" alt="">
        <div class="pname">${emp.displayName || emp.name}</div>
      </div>
      <hr class="profile-divider">
      <div class="profile-contact">
        <div class="crow"><span class="icon">&#9993;</span>${emp.email}</div>
        <div class="crow"><span class="icon">&#9742;</span>${emp.phone}</div>
        <div class="crow"><span class="icon">&#9679;</span>${emp.address}</div>
      </div>
      <hr class="profile-divider">
      <div class="profile-stats">
        <div><div class="stat-num">${emp.total}</div><div class="stat-lbl">TOTAL</div></div>
        <div><div class="stat-num">${emp.done}</div><div class="stat-lbl">DONE</div></div>
        <div><div class="stat-num">${emp.pending}</div><div class="stat-lbl">PENDING</div></div>
        <div><div class="stat-num">${emp.overdue}</div><div class="stat-lbl">OVERDUE</div></div>
      </div>
    `;
  }

  const taskCard = document.getElementById("empTaskCard");
  if (taskCard) {
    const taskItemsHtml = (emp.tasks && emp.tasks.length)
      ? emp.tasks.map(tk => `
          <div class="task-item">
            <div class="tname">${tk.t}</div>
            <div class="tdesc">${tk.d}</div>
          </div>`).join("")
      : `<div class="empty">No tasks assigned yet.</div>`;
    taskCard.innerHTML = `<h3>TASK</h3>${taskItemsHtml}`;
  }
}

function showEmployeeDetail(i) {
  const e = employees[i];
  const detailPanel = document.getElementById("employeeDetail");
  if (!detailPanel) return;

  detailPanel.innerHTML = `
    <div class="panel">
      <h3>${e.name}</h3>
      <div class="row"><span>Position</span><span>${e.role}</span></div>
      <div class="row"><span>Department</span><span>${e.dept}</span></div>
      <div class="row"><span>Status</span><span>${e.status || 'Active'}</span></div>
    </div>
    <div class="panel">
      <h3>Contact</h3>
      <div class="row"><span>Email</span><span>${e.email || 'N/A'}</span></div>
    </div>
  `;
}

function renderTasks() {
  const body = document.getElementById("taskBody");
  if (!body) return;
  body.innerHTML = tasks.map(t => `
    <tr>
      <td>${t.who}</td>
      <td>${t.task}</td>
      <td>${t.dept}</td>
      <td>${t.due}</td>
      <td>${t.status}</td>
    </tr>
  `).join("");
}

function renderApplicants() {
  const searchInput = document.getElementById("applicantSearch");
  const q = searchInput ? searchInput.value.toLowerCase() : "";
  const body = document.getElementById("applicantBody");

  const filtered = applicants.filter(a =>
    a.name.toLowerCase().includes(q) || a.role.toLowerCase().includes(q)
  );

  if (body) {
    body.innerHTML = filtered.map((a) => `
      <tr onclick="showApplicantDetail(${applicants.indexOf(a)})">
        <td>
          <div class="emp-name">${a.name.toUpperCase()}</div>
          <div class="emp-role">${a.role}</div>
        </td>
        <td>${a.date}</td>
        <td><span class="status-pill status-${a.status}">${a.status.toUpperCase()}</span></td>
      </tr>
    `).join("");
  }
}

function showApplicantDetail(i) {
  const a = applicants[i];
  const detailPanel = document.getElementById("applicantDetail");
  if (!detailPanel) return;

  detailPanel.innerHTML = `
    <div style="font-size:20px;font-weight:700;margin-bottom:4px;">${a.name}</div>
    <div style="font-size:13px;opacity:0.9;margin-bottom:16px;">${a.role}</div>
    <div style="font-size:13px;font-weight:700;letter-spacing:0.5px;">APPLIED</div>
    <div style="font-size:15px;margin-bottom:12px;">${a.date}</div>
    <div style="display:flex;gap:8px;margin-top:16px;">
      <button style="flex:1;padding:10px;border:none;border-radius:6px;background:white;color:#1f5c1f;font-weight:700;cursor:pointer;">ACCEPT</button>
      <button style="flex:1;padding:10px;border:none;border-radius:6px;background:white;color:#6b1f1f;font-weight:700;cursor:pointer;">REJECT</button>
    </div>
  `;
}


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


/* ==========================================================================
   PRODUCTION & LOGISTICS MODULES
   ========================================================================== */

function statusPillClass(status) {
  if (status === 'Running') return 'status-running';
  if (status === 'Maintenance') return 'status-maintenance';
  if (status === 'In transit') return 'status-transit';
  if (status === 'Delivered') return 'status-delivered';
  if (status === 'Loading') return 'status-loading';
  return 'status-loading';
}

function renderProductionTable() {
  const tbody = document.getElementById('productionTableBody');
  if (!tbody) return;

  tbody.innerHTML = '';
  productionData.forEach((item, idx) => {
    const row = document.createElement('tr');
    row.innerHTML = `
      <td>${item.id}</td>
      <td>${item.name}${item.dept ? ' <span style="opacity:0.6;font-size:10px;">(' + item.dept + ')</span>' : ''}</td>
      <td><span class="status-pill ${statusPillClass(item.status)}">${item.status}</span></td>
      <td>${item.output}</td>
      <td>${item.target || '-'}</td>
      <td class="action-cell">
        <button class="edit-btn" onclick="editProduction(${idx})">Edit</button>
        <button class="remove-btn" onclick="removeProduction(${idx})">Remove</button>
      </td>
    `;
    tbody.appendChild(row);
  });

  const runningCount = productionData.filter(p => p.status === 'Running').length;
  const factoryValueEl = document.querySelectorAll('#page-production .metric-card .mvalue')[1];
  if (factoryValueEl) factoryValueEl.textContent = runningCount;
}

function renderLogisticTable() {
  const tbody = document.getElementById('logisticTableBody');
  if (!tbody) return;

  tbody.innerHTML = '';
  logisticData.forEach((item, idx) => {
    const row = document.createElement('tr');
    row.innerHTML = `
      <td>${item.id}</td>
      <td>${item.dest}</td>
      <td><span class="status-pill ${statusPillClass(item.status)}">${item.status}</span></td>
      <td>${item.driver}</td>
      <td>${item.eta}</td>
      <td class="action-cell">
        <button class="track-btn" onclick="editLogistic(${idx})">Edit</button>
        <button class="remove-btn" onclick="removeLogistic(${idx})">Remove</button>
      </td>
    `;
    tbody.appendChild(row);
  });

  const total = logisticData.length;
  const active = logisticData.filter(l => l.status !== 'Delivered').length;
  
  const totalValEl = document.getElementById('totalDeliveryVal');
  const activeValEl = document.getElementById('activeShipmentsVal');
  const activeSubEl = document.getElementById('activeShipmentsSub');

  if (totalValEl) totalValEl.textContent = total;
  if (activeValEl) activeValEl.textContent = active;
  if (activeSubEl) activeSubEl.textContent = active + ' total shipments';
}


/* ==========================================================================
   MODAL DIALOG CONTROLS & FORM SUBMISSIONS
   ========================================================================== */

function openModal(type) {
  if (type === 'production') {
    editingProductionIndex = null;
    const title = document.getElementById('productionModalTitle');
    const btn = document.getElementById('productionSubmitBtn');
    if (title) title.textContent = 'Production';
    if (btn) btn.textContent = 'SUBMIT';
    clearForm('production');
  } else {
    editingLogisticIndex = null;
    const title = document.getElementById('logisticModalTitle');
    const btn = document.getElementById('logisticSubmitBtn');
    if (title) title.textContent = 'Logistics';
    if (btn) btn.textContent = 'SUBMIT';
    clearForm('logistic');
  }
  const modal = document.getElementById(type + 'Modal');
  if (modal) modal.classList.add('active');
}

function closeModal(type) {
  const modal = document.getElementById(type + 'Modal');
  if (modal) modal.classList.remove('active');
}

function clearForm(type) {
  if (type === 'production') {
    ['p_department', 'p_date', 'p_name', 'p_id', 'p_qty', 'p_pio', 'p_output'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
    const status = document.getElementById('p_status');
    if (status) status.value = 'Running';
  } else {
    ['l_dest', 'l_name', 'l_id', 'l_qty', 'l_pio', 'l_date', 'l_driver', 'l_eta'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
    const status = document.getElementById('l_status');
    if (status) status.value = 'Loading';
  }
}

function editProduction(idx) {
  editingProductionIndex = idx;
  const item = productionData[idx];

  document.getElementById('p_department').value = item.dept || '';
  document.getElementById('p_date').value = '';
  document.getElementById('p_name').value = item.name;
  document.getElementById('p_id').value = item.id;
  document.getElementById('p_qty').value = item.target;
  document.getElementById('p_pio').value = '';
  document.getElementById('p_output').value = item.output;
  document.getElementById('p_status').value = item.status;

  document.getElementById('productionModalTitle').textContent = 'Edit Production';
  document.getElementById('productionSubmitBtn').textContent = 'SAVE CHANGES';
  document.getElementById('productionModal').classList.add('active');
}

function editLogistic(idx) {
  editingLogisticIndex = idx;
  const item = logisticData[idx];

  document.getElementById('l_dest').value = item.dest;
  document.getElementById('l_name').value = '';
  document.getElementById('l_id').value = item.id;
  document.getElementById('l_qty').value = '';
  document.getElementById('l_pio').value = '';
  document.getElementById('l_driver').value = item.driver;
  document.getElementById('l_eta').value = item.eta;
  document.getElementById('l_date').value = '';
  document.getElementById('l_status').value = item.status;

  document.getElementById('logisticModalTitle').textContent = 'Edit Shipment';
  document.getElementById('logisticSubmitBtn').textContent = 'SAVE CHANGES';
  document.getElementById('logisticModal').classList.add('active');
}

function removeProduction(idx) {
  if (!confirm('Remove ' + productionData[idx].name + ' from production?')) return;
  productionData.splice(idx, 1);
  renderProductionTable();
}

function removeLogistic(idx) {
  if (!confirm('Remove shipment to ' + logisticData[idx].dest + '?')) return;
  logisticData.splice(idx, 1);
  renderLogisticTable();
}

function submitForm(type) {
  if (type === 'production') {
    const dept = document.getElementById('p_department').value.trim();
    const name = document.getElementById('p_name').value.trim();
    const id = document.getElementById('p_id').value.trim() || ('PID' + Math.floor(Math.random() * 9000 + 1000));
    const qty = document.getElementById('p_qty').value.trim();
    const output = document.getElementById('p_output').value.trim();
    const status = document.getElementById('p_status').value;

    if (!name) { alert('Please enter a Product Name.'); return; }

    const target = parseInt(String(qty).replace(/\D/g, '')) || 0;
    const outputVal = parseInt(String(output).replace(/\D/g, '')) || 0;

    if (editingProductionIndex !== null) {
      productionData[editingProductionIndex] = { id, name, dept, status, output: outputVal, target };
    } else {
      productionData.push({ id, name, dept, status, output: outputVal, target });
    }

    renderProductionTable();
    closeModal('production');
    clearForm('production');
    editingProductionIndex = null;
  } else {
    const dest = document.getElementById('l_dest').value.trim();
    const id = document.getElementById('l_id').value.trim() || ('G' + (20260600 + Math.floor(Math.random() * 99)));
    const driver = document.getElementById('l_driver').value.trim() || 'Unassigned';
    const eta = document.getElementById('l_eta').value.trim() || 'TBD';
    const status = document.getElementById('l_status').value;

    if (!dest) { alert('Please enter a Destination.'); return; }

    if (editingLogisticIndex !== null) {
      logisticData[editingLogisticIndex] = { id, dest, status, driver, eta };
    } else {
      logisticData.push({ id, dest, status, driver, eta });
    }

    renderLogisticTable();
    closeModal('logistic');
    clearForm('logistic');
    editingLogisticIndex = null;
  }
}


/* ==========================================================================
   INITIALIZATION & EVENT LISTENERS
   ========================================================================== */

window.addEventListener("load", () => {
  renderTasks();
  renderEmployees();
  renderApplicants();
  renderProductionTable();
  renderLogisticTable();

  buildChart();
  replayStatCounts();

  // Close modals on overlay background click
  document.querySelectorAll('.modal-overlay').forEach(ov => {
    ov.addEventListener('click', (e) => {
      if (e.target === ov) {
        ov.classList.remove('active');
      }
    });
  });

  // Department Tooltip Interactions
  const deptTooltip = document.getElementById('deptTooltip');
  const deptChartWrap = document.querySelector('.dept-chart-wrap');

  if (deptTooltip && deptChartWrap) {
    document.querySelectorAll('.dept-point').forEach(point => {
      const showTip = () => {
        const circle = point.querySelector('circle');
        const svg = point.closest('svg');
        const wrapRect = deptChartWrap.getBoundingClientRect();
        const svgRect = svg.getBoundingClientRect();
        const cx = parseFloat(circle.getAttribute('cx'));
        const cy = parseFloat(circle.getAttribute('cy'));
        const vb = svg.viewBox.baseVal;
        const scaleX = svgRect.width / vb.width;
        const scaleY = svgRect.height / vb.height;
        const left = (svgRect.left - wrapRect.left) + cx * scaleX;
        const top = (svgRect.top - wrapRect.top) + cy * scaleY;
        deptTooltip.textContent = point.dataset.label;
        deptTooltip.style.left = left + 'px';
        deptTooltip.style.top = top + 'px';
        deptTooltip.style.display = 'block';
      };

      const hideTip = () => { deptTooltip.style.display = 'none'; };

      point.addEventListener('mouseenter', showTip);
      point.addEventListener('mouseleave', hideTip);
      point.addEventListener('click', (e) => {
        e.stopPropagation();
        if (deptTooltip.style.display === 'block' && deptTooltip.textContent === point.dataset.label) {
          hideTip();
        } else {
          showTip();
        }
      });
    });

    document.addEventListener('click', () => {
      deptTooltip.style.display = 'none';
    });
  }
});