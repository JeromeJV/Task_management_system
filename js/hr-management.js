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