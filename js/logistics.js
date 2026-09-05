/* ==========================================================================
   LOGISTICS MODULE
   ========================================================================== */

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

function removeLogistic(idx) {
  if (!confirm('Remove shipment to ' + logisticData[idx].dest + '?')) return;
  logisticData.splice(idx, 1);
  renderLogisticTable();
}