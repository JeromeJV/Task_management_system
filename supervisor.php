<?php
session_start();

include('config/connection.php');
include('config/autoLog.php');
include('config/Supervisor_API.php');

// Authorization check: Siguraduhing tamang role ang naka-login
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'super') {
    header("Location: index.php");
    exit();
}
if (empty($_SESSION['office_task_csrf'])) {
    $_SESSION['office_task_csrf'] = bin2hex(random_bytes(32));
}

// -----------------------------------------------------
// 1. CALCULATE COMPLETED, PENDING, AND OVERDUE TASKS
// -----------------------------------------------------

// Query para sa Production tasks
$prod_counts_query = "
    SELECT 
        SUM(CASE WHEN product_status = 'product done' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN (product_status != 'product done' OR product_status IS NULL) AND due_date >= CURDATE() THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN (product_status != 'product done' OR product_status IS NULL) AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue
    FROM production";

$prod_res = mysqli_query($conn, $prod_counts_query);
$prod_data = $prod_res ? mysqli_fetch_assoc($prod_res) : ['completed' => 0, 'pending' => 0, 'overdue' => 0];

// Query para sa Delivery tasks
$del_counts_query = "
    SELECT 
        SUM(CASE WHEN status = 'Delivered' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN (status != 'Delivered' OR status IS NULL) AND delivery_date >= CURDATE() THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN (status != 'Delivered' OR status IS NULL) AND delivery_date < CURDATE() THEN 1 ELSE 0 END) as overdue
    FROM delivery";

$del_res = mysqli_query($conn, $del_counts_query);
$del_data = $del_res ? mysqli_fetch_assoc($del_res) : ['completed' => 0, 'pending' => 0, 'overdue' => 0];

// Sum/Pagsasamahin ang galing sa Production at Delivery
$total_completed = ($prod_data['completed'] ?? 0) + ($del_data['completed'] ?? 0);
$total_pending   = ($prod_data['pending'] ?? 0) + ($del_data['pending'] ?? 0);
$total_overdue   = ($prod_data['overdue'] ?? 0) + ($del_data['overdue'] ?? 0);

require_once __DIR__ . '/config/production_assignment_schema.php';
ensureProductionAssignmentSchema($conn);

// Active assigned production tasks
$active_tasks = [];
$production_tasks_query = mysqli_query($conn, "
    SELECT CONCAT(p.product_name, ' - ', pa.work_type) AS task_name,
           p.production_id,
           pa.work_type,
           pa.assigned_by,
           e.username AS assigned_to,
           p.due_date AS due_date,
           CASE WHEN pa.completion_review_status = 'pending_review'
                THEN 'Awaiting Approval' ELSE 'In Progress' END AS task_status,
           CONCAT('Production - ', pa.work_type) AS task_type
    FROM production p
    INNER JOIN production_assignments pa
        ON pa.production_id = CAST(p.production_id AS CHAR)
    INNER JOIN employee e ON e.employee_id = pa.employee_id
    WHERE (p.product_status != 'product done' OR p.product_status IS NULL)
      AND pa.work_status = 'Pending'
      AND (pa.work_type = 'Cooking'
           OR NOT EXISTS (
               SELECT 1
               FROM production_assignments legacy_cooking
               WHERE legacy_cooking.production_id = pa.production_id
                 AND legacy_cooking.work_type = 'Cooking'
           )
           OR EXISTS (
          SELECT 1
          FROM production_assignments cooking
          WHERE cooking.production_id = pa.production_id
            AND cooking.work_type = 'Cooking'
            AND cooking.work_status = 'Done'
      ))
    ORDER BY p.due_date ASC, p.production_id DESC
");
if (!$production_tasks_query) {
    throw new RuntimeException('Unable to load active production assignments: ' . mysqli_error($conn));
}
while ($task = mysqli_fetch_assoc($production_tasks_query)) {
    $active_tasks[] = $task;
}

$ready_packaging_query = mysqli_query($conn, "
    SELECT CONCAT(p.product_name, ' - Packaging') AS task_name,
           'Unassigned' AS assigned_to,
           p.due_date AS due_date,
           'Waiting for Packaging' AS task_status,
           'Production - Packaging' AS task_type
    FROM production p
    INNER JOIN production_assignments cooking
        ON cooking.production_id = CAST(p.production_id AS CHAR)
       AND cooking.work_type = 'Cooking'
       AND cooking.work_status = 'Done'
    LEFT JOIN production_assignments packaging
        ON packaging.production_id = CAST(p.production_id AS CHAR)
       AND packaging.work_type = 'Packaging'
    WHERE packaging.production_id IS NULL
      AND (p.product_status != 'product done' OR p.product_status IS NULL)
    ORDER BY p.due_date ASC, p.production_id DESC
");
if (!$ready_packaging_query) {
    throw new RuntimeException('Unable to load production tasks ready for packaging: ' . mysqli_error($conn));
}
while ($task = mysqli_fetch_assoc($ready_packaging_query)) {
    $active_tasks[] = $task;
}

// Active assigned logistics tasks
$logistics_tasks_query = mysqli_query($conn, "
    SELECT CONCAT(COALESCE(NULLIF(p.product_name, ''), CONCAT('Production #', d.production_id)),
                  ' - ', d.route) AS task_name,
           d.delivery_id,
           d.assigned_by,
           u.name AS assigned_to,
           d.delivery_date AS due_date,
           CASE WHEN d.completion_review_status = 'pending_review'
                THEN 'Awaiting Approval'
                ELSE COALESCE(NULLIF(d.status, ''), 'Pending')
           END AS task_status,
           'Logistics' AS task_type
    FROM delivery d
    INNER JOIN users u ON u.id = d.driver_id
    LEFT JOIN production p ON p.production_id = d.production_id
    WHERE d.driver_id IS NOT NULL
      AND (d.status != 'Delivered' OR d.status IS NULL)
    ORDER BY d.delivery_date ASC, d.delivery_id DESC
");
if (!$logistics_tasks_query) {
    throw new RuntimeException('Unable to load active logistics assignments: ' . mysqli_error($conn));
}
while ($task = mysqli_fetch_assoc($logistics_tasks_query)) {
    $active_tasks[] = $task;
}

// Include all employee task submissions waiting for supervisor review.
$office_reviews_query = $conn->prepare(
    "SELECT o.office_id, o.employee_id, o.title AS task_name, e.username AS assigned_to,
            o.due_date, o.supervisor_id AS assigned_by,
            'Awaiting Approval' AS task_status, 'Employee' AS task_type
     FROM office o
     LEFT JOIN employee e ON e.employee_id = o.employee_id
     WHERE o.completion_review_status = 'pending_review'
     ORDER BY o.due_date ASC, o.office_id DESC"
);
if (!$office_reviews_query) {
    throw new RuntimeException('Unable to prepare employee task review queue: ' . $conn->error);
}
if (!$office_reviews_query->execute()) {
    throw new RuntimeException('Unable to load employee task review queue: ' . $office_reviews_query->error);
}
$office_reviews_result = $office_reviews_query->get_result();
while ($task = $office_reviews_result->fetch_assoc()) {
    $active_tasks[] = $task;
}
$office_reviews_query->close();

usort($active_tasks, static function ($first, $second) {
    $first_is_review = ($first['task_status'] ?? '') === 'Awaiting Approval';
    $second_is_review = ($second['task_status'] ?? '') === 'Awaiting Approval';
    if ($first_is_review !== $second_is_review) {
        return $first_is_review ? -1 : 1;
    }

    return strcmp((string) $first['due_date'], (string) $second['due_date']);
});
$awaiting_approval_count = count(array_filter(
    $active_tasks,
    static fn ($task) => ($task['task_status'] ?? '') === 'Awaiting Approval'
));

// Sinisiguro ang iba pang variables
$message = $message ?? '';
$delivery_error = (string) ($_SESSION['delivery_error'] ?? '');
unset($_SESSION['delivery_error']);
$delivery_success = (string) ($_SESSION['delivery_success'] ?? '');
unset($_SESSION['delivery_success']);
$office_task_flash = (string) ($_SESSION['office_task_flash'] ?? '');
unset($_SESSION['office_task_flash']);
$route_err = $route_err ?? '';
$pieces_err = $pieces_err ?? '';
$stock_err = $stock_err ?? '';
$delivery_date_err = $delivery_date_err ?? '';
$records = $records ?? [];
$count = $count ?? count($records);

$product_name_err = $product_name_err ?? '';
$target_pcs_err = $target_pcs_err ?? '';
$due_date_err = $due_date_err ?? '';
$Stock_number_err = $Stock_number_err ?? '';
$quantity_err = $quantity_err ?? '';    
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisor Form</title>
    <link rel="stylesheet" href="css/supervisor.css?v=20261014-review-queue">
    <link rel="stylesheet" href="css/task-review-dialog.css?v=<?= filemtime(__DIR__ . '/css/task-review-dialog.css'); ?>">
    <link rel="stylesheet" href="css/tasktrack_sidebar.css?v=<?= filemtime(__DIR__ . '/css/tasktrack_sidebar.css'); ?>">
    <link rel="stylesheet" href="css/table-scroll.css?v=<?= filemtime(__DIR__ . '/css/table-scroll.css'); ?>">
    <link rel="stylesheet" href="css/select-control.css?v=<?= filemtime(__DIR__ . '/css/select-control.css'); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<?php $tasktrackActive = 'dashboard'; include __DIR__ . '/config/tasktrack_sidebar.php'; ?>

<div class="main">
  <div class="topbar"><h1>Supervisor</h1></div>

  <div class="content">
    <?php if ($delivery_error !== ''): ?>
      <div role="alert" style="margin: 0 0 16px; padding: 12px; border-radius: 8px; color: #842029; background: #f8d7da;"><?= htmlspecialchars($delivery_error); ?></div>
    <?php endif; ?>
    <?php if ($delivery_success !== ''): ?>
      <div role="status" style="margin: 0 0 16px; padding: 12px; border-radius: 8px; color: #155724; background: #d4edda;"><?= htmlspecialchars($delivery_success); ?></div>
    <?php endif; ?>
    <?php if ($office_task_flash !== ''): ?>
      <div role="status" style="margin: 0 0 16px; padding: 12px; border-radius: 8px; color: #155724; background: #d4edda;"><?= htmlspecialchars($office_task_flash); ?></div>
    <?php endif; ?>

    <!-- DASHBOARD -->
    <div id="page-dashboard" class="page active">
      <div class="stat-row">
        <div class="stat-card">
          <div class="num" id="statCompleted"><?= $total_completed ?></div>
          <div class="label">COMPLETED</div>
        </div>
        <div class="stat-card">
          <div class="num" id="statPending"><?= $total_pending ?></div>
          <div class="label">PENDING</div>
        </div>
        <div class="stat-card">
          <div class="num" id="statOverdue"><?= $total_overdue ?></div>
          <div class="label">OVERDUE</div>
        </div>
      </div>
      <div class="dash-lower">
        <div class="charts-grid">
          <div class="chart-panel">
            <div class="dashboard-panel-heading">
              <div>
                <h3>COMPLETED PRODUCT PROGRESS</h3>
                <p>Completed quantity by due month for the selected year</p>
              </div>
              <label class="pie-month-filter">
                <span>Year</span>
                <select id="chartYearFilter" aria-label="Select chart year">
                  <option value="<?= (int) date('Y'); ?>"><?= (int) date('Y'); ?></option>
                </select>
              </label>
            </div>
            <div class="chart-canvas-wrap">
              <canvas id="myChart"></canvas>
            </div>
          </div>

          <div class="chart-panel">
            <div class="dashboard-panel-heading">
              <div>
                <h3>MOST PRODUCED PRODUCTS</h3>
                <p>Production records by product for the selected year and month</p>
              </div>
              <label class="pie-month-filter">
                <span>Month</span>
                <select id="pieMonthFilter" aria-label="Filter most produced products by month">
                  <option value="all">All months</option>
                  <option value="1">January</option>
                  <option value="2">February</option>
                  <option value="3">March</option>
                  <option value="4">April</option>
                  <option value="5">May</option>
                  <option value="6">June</option>
                  <option value="7">July</option>
                  <option value="8">August</option>
                  <option value="9">September</option>
                  <option value="10">October</option>
                  <option value="11">November</option>
                  <option value="12">December</option>
                </select>
              </label>
            </div>
            <div class="chart-canvas-wrap">
              <canvas id="productPieChart"></canvas>
              <p id="pieChartEmpty" class="chart-empty-message" hidden>No product records available.</p>
            </div>
          </div>
        </div>
        <div class="active-task-panel">
          <div class="active-task-heading">
            <div>
              <h3>TASKS &amp; REVIEW REQUESTS</h3>
              <p>Employee, Production, and Logistics submissions waiting for your review appear first.</p>
            </div>
            <div class="active-task-heading-badges">
              <span class="active-task-review-count"><?= $awaiting_approval_count; ?> waiting for approval</span>
              <span class="active-task-count"><?= count($active_tasks); ?> total</span>
            </div>
          </div>

          <div class="active-task-filters">
            <label>
              <span>Status</span>
              <select id="supervisorTaskStatusFilter">
                <option value="all">All statuses</option>
                <option value="awaiting_approval">Waiting for approval</option>
                <option value="in_progress">In progress</option>
                <option value="pending">Pending</option>
                <option value="waiting">Other waiting tasks</option>
              </select>
            </label>
            <label>
              <span>Task type</span>
              <select id="supervisorTaskTypeFilter">
                <option value="all">All task types</option>
                <option value="employee">Employee</option>
                <option value="production">Production</option>
                <option value="logistics">Logistics</option>
              </select>
            </label>
            <span class="active-task-filter-result" id="supervisorTaskFilterResult" aria-live="polite"></span>
          </div>

          <?php if ($active_tasks): ?>
            <div class="active-task-list">
              <?php foreach ($active_tasks as $task): ?>
                <?php
                  $task_type_class = match ($task['task_type'] ?? '') {
                      'Logistics' => 'is-logistics',
                      'Employee' => 'is-employee',
                      default => 'is-production',
                  };
                ?>
                <?php
                  $task_status = (string) ($task['task_status'] ?? 'Pending');
                  $filter_status = match ($task_status) {
                      'Awaiting Approval' => 'awaiting_approval',
                      'In Progress' => 'in_progress',
                      'Pending' => 'pending',
                      default => 'waiting',
                  };
                  $filter_type = match (true) {
                      ($task['task_type'] ?? '') === 'Employee' => 'employee',
                      ($task['task_type'] ?? '') === 'Logistics' => 'logistics',
                      str_starts_with((string) ($task['task_type'] ?? ''), 'Production') => 'production',
                      default => 'other',
                  };
                ?>
                <article
                  class="active-task-card <?= $task_type_class; ?> <?= $filter_status === 'awaiting_approval' ? 'is-awaiting-approval' : ''; ?>"
                  data-task-status="<?= htmlspecialchars($filter_status, ENT_QUOTES, 'UTF-8'); ?>"
                  data-task-type="<?= htmlspecialchars($filter_type, ENT_QUOTES, 'UTF-8'); ?>"
                >
                  <div class="active-task-card-main">
                    <span class="active-task-card-mark" aria-hidden="true">
                      <?= $task_type_class === 'is-logistics' ? '↗' : ($task_type_class === 'is-employee' ? 'E' : '✓'); ?>
                    </span>
                    <div class="active-task-card-copy">
                      <h4><?= htmlspecialchars($task['task_name'] ?? 'N/A'); ?></h4>
                      <p>Assigned to <strong><?= htmlspecialchars($task['assigned_to'] ?? 'N/A'); ?></strong></p>
                    </div>
                  </div>
                  <div class="active-task-card-meta">
                    <span class="active-task-type <?= $task_type_class; ?>"><?= htmlspecialchars($task['task_type']); ?></span>
                    <span class="active-task-date">Due <?= htmlspecialchars($task['due_date'] ?? 'N/A'); ?></span>
                    <span class="active-task-status"><?= htmlspecialchars($task['task_status'] ?? 'Pending'); ?></span>
                  </div>
                  <?php if (
                      $task_status === 'Awaiting Approval'
                      && (
                          ($task['task_type'] ?? '') === 'Employee'
                          || empty($task['assigned_by'])
                          || (int) $task['assigned_by'] === (int) $current_user_id
                      )
                  ): ?>
                      <div class="active-task-review-actions">
                        <?php if (($task['task_type'] ?? '') === 'Logistics'): ?>
                        <button
                          type="button"
                          class="delivery-review-open-button"
                          data-delivery-id="<?= (int) $task['delivery_id']; ?>"
                          data-route="<?= htmlspecialchars($task['task_name'] ?? 'this delivery', ENT_QUOTES, 'UTF-8'); ?>"
                        >Review submission</button>
                        <?php elseif (str_starts_with((string) ($task['task_type'] ?? ''), 'Production')): ?>
                        <button
                          type="button"
                          class="production-review-open-button"
                          data-production-id="<?= htmlspecialchars((string) ($task['production_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                          data-work-type="<?= htmlspecialchars((string) ($task['work_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                          data-product-name="<?= htmlspecialchars((string) ($task['task_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        >Review submission</button>
                        <?php elseif (($task['task_type'] ?? '') === 'Employee'): ?>
                        <button
                          type="button"
                          class="office-review-open-button"
                          data-office-id="<?= (int) $task['office_id']; ?>"
                          data-employee-id="<?= (int) $task['employee_id']; ?>"
                          data-title="<?= htmlspecialchars((string) ($task['task_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        >Review submission</button>
                        <?php endif; ?>
                      </div>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="active-task-empty">No active tasks are currently assigned to Production or Logistics.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- TASK -->
    <div id="page-task" class="page" style="display:none;">
      <h2 class="section-title">TASKS</h2>
      <div class="green-table-panel table-scroll">
        <table class="green-table">
          <thead>
            <tr><th>Employee</th><th>Task</th><th>Task type</th><th>Due date</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php if ($active_tasks): ?>
              <?php foreach ($active_tasks as $task): ?>
                <tr>
                  <td><?= htmlspecialchars($task['assigned_to'] ?? 'N/A'); ?></td>
                  <td><?= htmlspecialchars($task['task_name'] ?? 'N/A'); ?></td>
                  <td><?= htmlspecialchars($task['task_type'] ?? 'N/A'); ?></td>
                  <td><?= htmlspecialchars($task['due_date'] ?? 'N/A'); ?></td>
                  <td><?= htmlspecialchars($task['task_status'] ?? 'Pending'); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="5">No active assigned tasks.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <p class="active-task-empty" id="supervisorTaskFilterEmpty" hidden>No tasks match these filters.</p>
    </div>

  </div>
</div>
  <dialog class="task-review-dialog" id="deliveryReviewDialog" aria-labelledby="deliveryReviewTitle">
    <form method="post" action="supervisor.php#page-dashboard" class="task-review-dialog-card">
      <input type="hidden" name="task_review_csrf" value="<?= htmlspecialchars($_SESSION['task_review_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="delivery_id" id="deliveryReviewId">
      <span class="task-review-dialog-kicker">LOGISTICS TASK</span>
      <h2 id="deliveryReviewTitle">Review submission</h2>
      <p class="task-review-dialog-description" id="deliveryReviewDescription"></p>
      <label class="task-review-note-label" for="deliveryReviewNote">Reason for requesting changes</label>
      <textarea id="deliveryReviewNote" name="review_note" maxlength="1000" rows="4" placeholder="Explain what needs to be changed"></textarea>
      <p class="task-review-dialog-hint">A reason is required only when you request changes.</p>
      <div class="task-review-dialog-actions">
        <button class="task-review-cancel-button" type="button" data-close-review-dialog>Cancel</button>
        <button class="task-reject-button" type="submit" name="review_delivery" value="reject">Request changes</button>
        <button class="task-approve-button" type="submit" name="review_delivery" value="approve">Approve delivery</button>
      </div>
    </form>
  </dialog>
  <dialog class="task-review-dialog" id="productionReviewDialog" aria-labelledby="productionReviewTitle">
    <form method="post" action="factory_main.php" class="task-review-dialog-card">
      <input type="hidden" name="task_review_csrf" value="<?= htmlspecialchars($_SESSION['task_review_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="production_id" id="productionReviewId">
      <input type="hidden" name="work_type" id="productionReviewWorkType">
      <span class="task-review-dialog-kicker">PRODUCTION TASK</span>
      <h2 id="productionReviewTitle">Review submission</h2>
      <p class="task-review-dialog-description" id="productionReviewDescription"></p>
      <label class="task-review-note-label" for="productionReviewNote">Reason for requesting changes</label>
      <textarea id="productionReviewNote" name="review_note" maxlength="1000" rows="4" placeholder="Explain what needs to be changed"></textarea>
      <p class="task-review-dialog-hint">A reason is required only when you request changes.</p>
      <div class="task-review-dialog-actions">
        <button class="task-review-cancel-button" type="button" data-close-review-dialog>Cancel</button>
        <button class="task-reject-button" type="submit" name="review_production_stage" value="reject">Request changes</button>
        <button class="task-approve-button" type="submit" name="review_production_stage" value="approve">Approve</button>
      </div>
    </form>
  </dialog>
  <dialog class="task-review-dialog" id="officeReviewDialog" aria-labelledby="officeReviewTitle">
    <form method="post" action="employee.php" class="task-review-dialog-card">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['office_task_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="review_return" value="supervisor.php">
      <input type="hidden" name="office_id" id="officeReviewId">
      <input type="hidden" name="employee_id" id="officeReviewEmployeeId">
      <span class="task-review-dialog-kicker">EMPLOYEE TASK</span>
      <h2 id="officeReviewTitle">Review submission</h2>
      <p class="task-review-dialog-description" id="officeReviewDescription"></p>
      <label class="task-review-note-label" for="officeReviewNote">Reason for requesting changes</label>
      <textarea id="officeReviewNote" name="review_note" maxlength="1000" rows="4" placeholder="Explain what needs to be changed"></textarea>
      <p class="task-review-dialog-hint">A reason is required only when you request changes.</p>
      <div class="task-review-dialog-actions">
        <button class="task-review-cancel-button" type="button" data-close-review-dialog>Cancel</button>
        <button class="task-reject-button" type="submit" name="review_task" value="reject">Request changes</button>
        <button class="task-approve-button" type="submit" name="review_task" value="approve">Approve</button>
      </div>
    </form>
  </dialog>
  <?php include __DIR__ . '/config/chatbot_widget.php'; ?>
  <script>
    document.querySelectorAll('.delivery-review-open-button').forEach((button) => {
      button.addEventListener('click', () => {
        openTaskReviewDialog('deliveryReviewDialog', 'deliveryReviewNote', {
          deliveryReviewId: button.dataset.deliveryId,
          deliveryReviewDescription: `Choose whether to approve "${button.dataset.route}" or request changes from the driver.`
        });
      });
    });
    document.querySelectorAll('.production-review-open-button').forEach((button) => {
      button.addEventListener('click', () => {
        openTaskReviewDialog('productionReviewDialog', 'productionReviewNote', {
          productionReviewId: button.dataset.productionId,
          productionReviewWorkType: button.dataset.workType,
          productionReviewDescription: `Choose whether to approve the ${button.dataset.workType} stage for "${button.dataset.productName}" or request changes.`
        });
      });
    });
    document.querySelectorAll('.office-review-open-button').forEach((button) => {
      button.addEventListener('click', () => {
        openTaskReviewDialog('officeReviewDialog', 'officeReviewNote', {
          officeReviewId: button.dataset.officeId,
          officeReviewEmployeeId: button.dataset.employeeId,
          officeReviewDescription: `Choose whether to approve "${button.dataset.title}" or request changes from the employee.`
        });
      });
    });
    function openTaskReviewDialog(dialogId, noteId, values) {
      const dialog = document.getElementById(dialogId);
      const note = document.getElementById(noteId);
      Object.entries(values).forEach(([id, value]) => {
        const field = document.getElementById(id);
        if (field.tagName === 'INPUT') field.value = value;
        else field.textContent = value;
      });
      note.value = '';
      note.required = false;
      dialog.showModal();
    }
    document.querySelectorAll('.task-review-dialog').forEach((dialog) => {
      const note = dialog.querySelector('textarea[name="review_note"]');
      dialog.querySelector('[value="reject"]').addEventListener('click', () => { note.required = true; });
      dialog.querySelector('[value="approve"]').addEventListener('click', () => { note.required = false; });
      dialog.querySelector('[data-close-review-dialog]').addEventListener('click', () => dialog.close());
      dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
    });
    const supervisorTaskCards = [...document.querySelectorAll('.active-task-card')];
    const supervisorTaskStatusFilter = document.getElementById('supervisorTaskStatusFilter');
    const supervisorTaskTypeFilter = document.getElementById('supervisorTaskTypeFilter');
    const supervisorTaskFilterResult = document.getElementById('supervisorTaskFilterResult');
    function filterSupervisorTasks() {
      const status = supervisorTaskStatusFilter.value;
      const type = supervisorTaskTypeFilter.value;
      let visibleCount = 0;
      supervisorTaskCards.forEach((card) => {
        const visible = (status === 'all' || card.dataset.taskStatus === status)
          && (type === 'all' || card.dataset.taskType === type);
        card.hidden = !visible;
        if (visible) visibleCount += 1;
      });
      supervisorTaskFilterResult.textContent = `${visibleCount} task${visibleCount === 1 ? '' : 's'} shown`;
      const filterEmpty = document.getElementById('supervisorTaskFilterEmpty');
      if (filterEmpty) filterEmpty.hidden = visibleCount !== 0;
    }
    supervisorTaskStatusFilter.addEventListener('change', filterSupervisorTasks);
    supervisorTaskTypeFilter.addEventListener('change', filterSupervisorTasks);
    filterSupervisorTasks();
  </script>
  <script src="js/supervisor.js?v=20261009-supervisor-fixes"></script>
<script src="js/select-control.js?v=<?= filemtime(__DIR__ . '/js/select-control.js'); ?>"></script>
</body>
</html>