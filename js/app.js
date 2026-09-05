/* ==========================================================================
   NAVIGATION SYSTEM & PAGE CONTROL (Multi-Page Redirection)
   ========================================================================== */

function showPage(page) {
  // Mapping ng page names patungo sa kani-kanilang PHP module files
  const routes = {
    dashboard: "dashboard.php",
    task: "task.php",
    employee: "employee.php",
    applicants: "applicants.php",
    production: "factory_main.php",
    logistic: "delivery_main.php"
  };

  // Kung umiiral ang target page sa routes, lilipat sa kabilang file
  if (routes[page]) {
    window.location.href = routes[page];
  } else {
    // Fallback kung walang match sa mapping (halimbawa: page + '.php')
    window.location.href = `${page}.php`;
  }
}

// Bind Navigation Buttons (Class-based bindings gamit ang data-page attribute)
document.addEventListener("DOMContentLoaded", () => {
  const navButtons = document.querySelectorAll(".nav-btn, .side-btn");
  
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
   SHARED UI HELPERS, MODALS & FORM SUBMISSIONS
   ========================================================================== */

function statusPillClass(status) {
  if (status === 'Running') return 'status-running';
  if (status === 'Maintenance') return 'status-maintenance';
  if (status === 'In transit') return 'status-transit';
  if (status === 'Delivered') return 'status-delivered';
  if (status === 'Loading') return 'status-loading';
  return 'status-loading';
}

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

  document.querySelectorAll('.modal-overlay').forEach(ov => {
    ov.addEventListener('click', (e) => {
      if (e.target === ov) {
        ov.classList.remove('active');
      }
    });
  });

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