/* ==========================================================================
   PRODUCTION MODULE
   ========================================================================== */

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

function removeProduction(idx) {
  if (!confirm('Remove ' + productionData[idx].name + ' from production?')) return;
  productionData.splice(idx, 1);
  renderProductionTable();
}