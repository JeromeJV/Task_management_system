const dialog = document.getElementById('taskDialog');
const form   = document.getElementById('taskForm');
const range  = document.getElementById('progressRange');
const value  = document.getElementById('progressValue');
const fill   = document.getElementById('progressFill');

const saveBtn          = document.getElementById('dlgSave');
const confirmBox       = document.getElementById('confirmBox');
const confirmMessage   = document.getElementById('confirmMessage');
const btnCancelConfirm = document.getElementById('btnCancelConfirm');
const btnProceedConfirm= document.getElementById('btnProceedConfirm');

let originalProgress = 0;

function updateProgressVisual(val) {
  value.textContent = val + '%';
  if (fill) fill.style.width = val + '%';
}

function openTask(btn) {
  const d = btn.dataset;

  document.getElementById('taskId').value = d.id;
  document.getElementById('dlgTitle').textContent = d.title;
  document.getElementById('dlgContent').textContent = d.content || 'No description provided.';
  
  document.getElementById('modalDepartment').textContent = d.department || '—';
  document.getElementById('modalDueDate').textContent = d.duedate || '—';
  document.getElementById('modalSupervisor').textContent = d.supervisor ? `#${d.supervisor}` : '—';
  document.getElementById('modalSubmissionDate').textContent = d.submissiondate || '—';
  document.getElementById('modalUpdatedAt').textContent = d.updatedat || '—';
  document.getElementById('modalDataType').textContent = d.datatype ? d.datatype.toUpperCase() : 'TASK';

  const statusBadge = document.getElementById('modalStatusBadge');
  statusBadge.textContent = d.status || 'Pending';
  statusBadge.className = `badge b-${d.state}`;

  const prioBadge = document.getElementById('modalPriorityBadge');
  prioBadge.textContent = d.priority + ' Priority';
  prioBadge.style.backgroundColor = d.priority.toLowerCase() === 'high' ? '#fee2e2' : (d.priority.toLowerCase() === 'medium' ? '#fef3c7' : '#d1fae5');
  prioBadge.style.color = d.priority.toLowerCase() === 'high' ? '#dc2626' : (d.priority.toLowerCase() === 'medium' ? '#d97706' : '#059669');

  originalProgress = parseInt(d.progress, 10);
  range.value = originalProgress;
  updateProgressVisual(originalProgress);

  const done = d.state === 'completed';
  range.disabled = done;
  saveBtn.style.display = done ? 'none' : 'inline-block';
  
  // Reset confirmation state
  confirmBox.classList.add('hidden');

  dialog.showModal();
}

document.querySelectorAll('.btn-act').forEach(btn => btn.addEventListener('click', e => {
  e.stopPropagation();
  openTask(btn);
}));

document.querySelectorAll('.task-row').forEach(row => {
  row.style.cursor = 'pointer';
  row.addEventListener('click', () => {
    const btn = row.querySelector('.btn-act');
    if (btn) openTask(btn);
  });
});

range.addEventListener('input', () => {
  updateProgressVisual(range.value);
  confirmBox.classList.add('hidden');
});

// Show inline confirmation when clicking Save
saveBtn.addEventListener('click', () => {
  const currentVal = parseInt(range.value, 10);
  let text = `Are you sure you want to update progress from <strong>${originalProgress}%</strong> to <strong>${currentVal}%</strong>?`;
  
  if (currentVal === 100) {
    text = `Setting progress to <strong>100%</strong> will mark this task as <strong>Completed</strong>. Are you sure?`;
  }
  
  confirmMessage.innerHTML = text;
  confirmBox.classList.remove('hidden');
});

// Confirm step handlers
btnCancelConfirm.addEventListener('click', () => confirmBox.classList.add('hidden'));
btnProceedConfirm.addEventListener('click', () => form.submit());

document.getElementById('dlgCancel').addEventListener('click', () => dialog.close());

const toast = document.getElementById('toast');
if (toast) setTimeout(() => toast.classList.remove('show'), 2400);