/* app.js
   All task data lives right here in the "tasks" array below —
   no API calls, no database. Add, edit, or remove entries in this
   array (or push into it from wherever a supervisor "sends" a task)
   and the dashboard / task page / modal will reflect it automatically. */

let tasks = [
    {
        id: 1,
        who: "Supervisor: Mark Reyes",
        task: "Fix the internet connection on the 3rd floor",
        dept: "IT Department",
        due: "2025-05-10",
        status: "Pending",
        progress: 40,
        description: "The 3rd floor router keeps dropping connection during peak hours. Please inspect the switch and replace the cabling if needed."
    },
    {
        id: 2,
        who: "Supervisor: Angela Cruz",
        task: "Create a website",
        dept: "Marketing Department",
        due: "2026-09-30",
        status: "Pending",
        progress: 15,
        description: "Build a landing page for the new product launch. Include a signup form and a mobile-friendly layout."
    },
    {
        id: 3,
        who: "Supervisor: Mark Reyes",
        task: "Restock the pantry supplies",
        dept: "Admin Department",
        due: "2026-08-20",
        status: "Pending",
        progress: 0,
        description: "Coordinate with the supplier to restock coffee, snacks, and paper goods for the pantry."
    },
    {
        id: 4,
        who: "Supervisor: Angela Cruz",
        task: "Prepare the onboarding kit for new hires",
        dept: "HR Department",
        due: "2026-07-01",
        status: "Completed",
        progress: 100,
        description: "Assemble welcome kits with company handbook, ID, and starter supplies for the incoming batch of employees."
    }
];

let activeTaskId = null; // which task is currently open in the modal

// Recalculates whether a task is Overdue / Pending / Completed based on today's date.
// A task keeps its "Completed" status once marked complete, regardless of due date.
function computeStatus(t) {
    if (t.status === "Completed") return "Completed";
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const dueDate = new Date(t.due);
    return dueDate < today ? "Overdue" : "Pending";
}

// Renders every view that depends on the tasks array.
function refreshAll() {
    updateDashboardStats();
    renderRecentTasks();
    renderTasksByStatus();
}

// to update dashboard statistics
function updateDashboardStats() {
    const withStatus = tasks.map(t => ({ ...t, status: computeStatus(t) }));
    const completed = withStatus.filter(t => t.status === "Completed").length;
    const pending = withStatus.filter(t => t.status === "Pending").length;
    const overdue = withStatus.filter(t => t.status === "Overdue").length;

    document.getElementById("completedCount").textContent = completed;
    document.getElementById("pendingCount").textContent = pending;
    document.getElementById("overdueCount").textContent = overdue;
}

// to render recent tasks in dashboard
function renderRecentTasks() {
    const recentTasksContainer = document.getElementById("recentTasks");

    if (tasks.length === 0) {
        recentTasksContainer.innerHTML = '<div class="empty-state">No tasks assigned yet</div>';
        return;
    }

    const recentTasks = tasks.slice(0, 5);
    recentTasksContainer.innerHTML = recentTasks.map(t => `
        <div class="task-item" onclick="openTaskModal(${t.id})">
            <div class="who">${t.task}</div>
            <div class="what">${t.who}</div>
            <div style="font-size: 12px; color: #666; margin-top: 4px;">
                Status: ${computeStatus(t)} | Due: ${t.due}
            </div>
        </div>
    `).join("");
}

// Builds one task card, including its mini progress bar
function taskCardHTML(t) {
    const progress = t.progress || 0;
    return `
        <div class="task-card" onclick="openTaskModal(${t.id})">
            <div class="task-card-title">${t.task}</div>
            <div class="task-card-meta">${t.who} &nbsp;&middot;&nbsp; ${t.dept} &nbsp;&middot;&nbsp; Due: ${t.due}</div>
            <div class="progress-bar-track mini">
                <div class="progress-bar-fill" style="width:${progress}%"></div>
            </div>
            <div class="task-card-progress-label">${progress}% complete</div>
        </div>
    `;
}

// Function to render tasks by status sections (card style)
function renderTasksByStatus() {
    const withStatus = tasks.map(t => ({ ...t, status: computeStatus(t) }));

    const overdueTasks = withStatus.filter(t => t.status === "Overdue");
    const pendingTasks = withStatus.filter(t => t.status === "Pending");
    const completedTasks = withStatus.filter(t => t.status === "Completed");

    const overdueBody = document.getElementById("overdueTaskBody");
    overdueBody.innerHTML = overdueTasks.length === 0
        ? `<div class="empty-state">No overdue tasks</div>`
        : overdueTasks.map(taskCardHTML).join("");

    const pendingBody = document.getElementById("pendingTaskBody");
    pendingBody.innerHTML = pendingTasks.length === 0
        ? `<div class="empty-state">No pending tasks</div>`
        : pendingTasks.map(taskCardHTML).join("");

    const completedBody = document.getElementById("completedTaskBody");
    completedBody.innerHTML = completedTasks.length === 0
        ? `<div class="empty-state">No completed tasks</div>`
        : completedTasks.map(taskCardHTML).join("");
}

// ---------------- Task detail modal ----------------
function openTaskModal(id) {
    const t = tasks.find(x => x.id === id);
    if (!t) return;
    activeTaskId = id;
    const status = computeStatus(t);
    const progress = t.progress || 0;

    document.getElementById("modalTaskTitle").textContent = t.task;
    document.getElementById("modalWho").textContent = t.who;
    document.getElementById("modalDept").textContent = t.dept;
    document.getElementById("modalDue").textContent = t.due;
    document.getElementById("modalDescription").textContent = t.description || "No additional details provided.";

    const pill = document.getElementById("modalStatusPill");
    pill.textContent = status;
    pill.className = "modal-status-pill " + status;

    document.getElementById("modalProgressSlider").value = progress;
    document.getElementById("modalProgressFill").style.width = progress + "%";
    document.getElementById("modalProgressValue").textContent = progress + "%";
    resetProgressConfirm();

    const completeBtn = document.getElementById("modalCompleteBtn");
    const updateBtn = document.getElementById("modalUpdateBtn");
    const alreadyDone = status === "Completed";
    completeBtn.disabled = alreadyDone;
    completeBtn.textContent = alreadyDone ? "Task completed" : "Mark as completed";
    updateBtn.disabled = alreadyDone;

    document.getElementById("taskModalOverlay").classList.add("active");
}

function closeTaskModal() {
    document.getElementById("taskModalOverlay").classList.remove("active");
    activeTaskId = null;
}

// Live-updates the bar and % label as the user drags the slider (before saving)
function onProgressSliderInput(value) {
    document.getElementById("modalProgressFill").style.width = value + "%";
    document.getElementById("modalProgressValue").textContent = value + "%";
    resetProgressConfirm();
}

// Resets the "are you sure" state whenever the slider moves or the modal reopens
let pendingConfirm = false;
function resetProgressConfirm() {
    pendingConfirm = false;
    const warnEl = document.getElementById("modalProgressWarning");
    warnEl.textContent = "";
    warnEl.classList.remove("success");
    document.getElementById("modalUpdateBtn").textContent = "Update progress";
}

// Checks whether the new percentage is good to go before it's saved.
// Returns "" if it's fine, or a message explaining what needs confirming/fixing.
function validateProgressChange(current, next) {
    if (Number.isNaN(next) || next < 0 || next > 100) {
        return "Progress must be a number between 0 and 100.";
    }
    if (next < current) {
        return `This lowers progress from ${current}% to ${next}%. Click "Confirm update" to proceed.`;
    }
    if (next === 100) {
        return 'That\'s 100% — use "Mark as completed" instead so the task moves to Completed.';
    }
    return "";
}

// User clicks "Update progress" — verifies the value first (asking for a second
// click on anything that looks off), then saves it directly onto the tasks array.
function saveTaskProgress() {
    const t = tasks.find(x => x.id === activeTaskId);
    if (!t) return;

    const slider = document.getElementById("modalProgressSlider");
    const nextValue = Number(slider.value);
    const currentValue = t.progress || 0;
    const warnEl = document.getElementById("modalProgressWarning");
    warnEl.classList.remove("success");

    const issue = validateProgressChange(currentValue, nextValue);
    const outOfRange = Number.isNaN(nextValue) || nextValue < 0 || nextValue > 100;

    if (outOfRange) {
        warnEl.textContent = issue;
        return;
    }

    if (issue && !pendingConfirm) {
        warnEl.textContent = issue;
        pendingConfirm = true;
        document.getElementById("modalUpdateBtn").textContent = "Confirm update";
        return;
    }

    t.progress = nextValue;
    refreshAll();
    resetProgressConfirm();
    warnEl.textContent = "Progress saved.";
    warnEl.classList.add("success");
    setTimeout(() => {
        warnEl.textContent = "";
        warnEl.classList.remove("success");
    }, 2000);
}

// User clicks "Mark as completed" — sets status to Completed and progress to 100%
function markTaskComplete() {
    const t = tasks.find(x => x.id === activeTaskId);
    if (!t) return;

    t.status = "Completed";
    t.progress = 100;
    refreshAll();
    openTaskModal(t.id); // refresh modal to reflect the new status/disabled buttons
}

function showPage(page) {
    ["dashboard","task"].forEach(p => {
        document.getElementById(`page-${p}`).classList.toggle("active", p === page);
        document.getElementById(`nav${p.charAt(0).toUpperCase() + p.slice(1)}`).classList.toggle("active", p === page);
    });
}

// Initialize
refreshAll();