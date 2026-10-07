<?php
// index.php: HTML + PHP combined. Reads tasks straight from the database.
session_start();

/* Name of THIS file, so the form and redirect never point at the login page. */
$self = basename($_SERVER['SCRIPT_NAME']);

/* ---------- Database connection (edit for your setup) ---------- */
mysqli_report(MYSQLI_REPORT_OFF);
$db = new mysqli('127.0.0.1', 'root', '', 'task_management_system');
if ($db->connect_errno) { die('Database connection failed.'); }
$db->set_charset('utf8mb4');

/* ---------- Logged-in employee ----------
   Set these in your login script. The fallbacks are for testing only. */
$employeeId = $_SESSION['employee_id'] ?? 1;
$fullName   = $_SESSION['full_name']   ?? 'Sagon, Bernard Joseph S.';
$department = $_SESSION['department']  ?? 'IT Dept';

$view = $_GET['view'] ?? 'all';               // all | week | history
if (!in_array($view, ['all', 'week', 'history'], true)) $view = 'all';

/* ---------- Update task progress (form POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['task_id'] ?? 0);
    $progress = max(0, min(100, (int)($_POST['progress'] ?? 0)));
    $status   = $progress === 100 ? 'Completed' : 'Pending';

    $stmt = $db->prepare("UPDATE office SET progress = ?, status = ?, updated_at = NOW()
                          WHERE office_id = ? AND employee_id = ?");
    $stmt->bind_param('isii', $progress, $status, $id, $employeeId);
    $stmt->execute();

    header('Location: ' . $self . '?view=' . $view . '&saved=' . ($progress === 100 ? 'done' : 'progress'));
    exit;
}

/* ---------- Fetch this employee's tasks from the database ---------- */
$stmt = $db->prepare("SELECT office_id, title, content, department, status, priority_level,
                             due_date, progress, updated_at
                      FROM office WHERE employee_id = ? ORDER BY due_date ASC");
$stmt->bind_param('i', $employeeId);
$stmt->execute();
$all = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$today = date('Y-m-d');
foreach ($all as &$t) {
    $t['state'] = strtolower($t['status']) === 'completed' ? 'completed'
                : (($t['due_date'] && $t['due_date'] < $today) ? 'overdue' : 'pending');
}
unset($t);

/* ---------- Counts for the three cards ---------- */
$count = ['overdue' => 0, 'pending' => 0, 'completed' => 0];
foreach ($all as $t) $count[$t['state']]++;

/* ---------- Filter list by view ---------- */
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd   = date('Y-m-d', strtotime('sunday this week'));

if ($view === 'history') {
    $tasks = array_filter($all, fn($t) => $t['state'] === 'completed');
    usort($tasks, fn($a, $b) => strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''));
} elseif ($view === 'week') {
    $tasks = array_filter($all, fn($t) => $t['state'] !== 'completed'
                          && $t['due_date'] >= $weekStart && $t['due_date'] <= $weekEnd);
} else {
    $tasks = $all;
}

/* ---------- Helpers ---------- */
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fdate($d) { return $d ? date('D, j M', strtotime($d)) : '—'; }
function plural($n, $one, $many) { return $n === 1 ? $one : $many; }

$initials = '';
foreach (array_slice(preg_split('/[ ,]+/', $fullName, -1, PREG_SPLIT_NO_EMPTY), 0, 2) as $w) $initials .= strtoupper($w[0]);
$parts = explode(',', $fullName);
$firstName = trim(explode(' ', trim($parts[1] ?? $parts[0]))[0]);

$colors = [
  'pending'   => ['#d97706', '#fef3c7'],
  'completed' => ['#10b981', '#d1fae5'],
  'overdue'   => ['#dc2626', '#fee2e2'],
];
$prioColors = ['high' => '#ef4444', 'medium' => '#f59e0b', 'low' => '#10b981'];
$firstPending = null;
foreach ($tasks as $k => $t) { if ($t['state'] === 'pending') { $firstPending = $k; break; } }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Tasks</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./css/empl.css">
</head>
<body>
  <header class="topbar">
    <div class="user">
      <div class="avatar avatar-lg"><?= e($initials) ?></div>
      <div>
        <div class="user-name"><?= e($fullName) ?></div>
        <div class="user-dept"><?= e(strtoupper($department)) ?></div>
      </div>
    </div>
    <a class="logout" href="logout.php" title="Log out" aria-label="Log out">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
    </a>
  </header>

  <main class="container">
    <section class="stats">
      <article class="stat stat-overdue">
        <div class="stat-head"><span class="icon-box">⏰</span><span>Overdue</span></div>
        <div class="stat-num"><?= $count['overdue'] ?></div>
        <span class="chip"><?= plural($count['overdue'], 'task', 'tasks') ?></span>
        <p class="stat-note"><?= $count['overdue'] ?> urgent <?= plural($count['overdue'], 'task is', 'tasks are') ?> currently past <?= plural($count['overdue'], 'its', 'their') ?> due date.</p>
      </article>
      <article class="stat stat-pending">
        <div class="stat-head"><span class="icon-box">❗</span><span>Pending</span></div>
        <div class="stat-num"><?= $count['pending'] ?></div>
        <span class="chip"><?= plural($count['pending'], 'task', 'tasks') ?></span>
        <p class="stat-note"><?= $count['pending'] ?> <?= plural($count['pending'], 'task is', 'tasks are') ?> waiting for your review and action.</p>
      </article>
      <article class="stat stat-done">
        <div class="stat-head"><span class="icon-box">✔</span><span>Completed</span></div>
        <div class="stat-num"><?= $count['completed'] ?></div>
        <span class="chip"><?= plural($count['completed'], 'task', 'tasks') ?></span>
        <p class="stat-note"><?= $count['completed'] ?> <?= plural($count['completed'], 'task has', 'tasks have') ?> been finished and <?= plural($count['completed'], 'is', 'are') ?> ready for review.</p>
      </article>
    </section>

    <section class="panel">
      <div class="panel-head">
        <div>
          <h1><?= $view === 'history' ? 'Task history' : 'Recent tasks' ?></h1>
          <p class="sub"><?= $view === 'history' ? 'All tasks you have completed, newest first.' : 'Clear list of all tasks with emphasis on pending deadlines.' ?></p>
        </div>
        <nav class="filters">
          <a class="btn-filter <?= $view === 'week' ? 'active' : '' ?>" href="?view=week">📅 This week</a>
          <a class="btn-filter <?= $view === 'all' ? 'active' : '' ?>" href="?view=all">⚲ All tasks</a>
          <a class="btn-filter <?= $view === 'history' ? 'active' : '' ?>" href="?view=history">🕘 History</a>
        </nav>
      </div>

      <div class="table-wrap">
        <div class="row row-head">
          <div>TASK</div><div><?= $view === 'history' ? 'COMPLETED' : 'DUE DATE' ?></div><div>STATUS</div><div>PRIORITY</div><div>OWNER</div><div>PROGRESS</div><div>ACTION</div>
        </div>

        <?php foreach ($tasks as $k => $t):
          $s = $t['state'];
          [$fg, $track] = $colors[$s];
          $prio = strtolower($t['priority_level']);
          $avatarBg = $s === 'overdue' ? '#ef4444' : ($s === 'completed' ? '#10b981' : '#3b82f6');
          $focus = ($k === $firstPending && $view !== 'history');
          [$label, $cls] = $s === 'completed' ? ['Done ✓', 'done']
                         : ($s === 'overdue' ? ['Urgent!', 'urgent']
                         : ($focus ? ['Open →', 'primary'] : ['Open', '']));
        ?>
        <div class="row task-row <?= $s ?><?= $focus ? ' focus' : '' ?>">
          <div>
            <div class="task-title"><?= e($t['title']) ?></div>
            <div class="task-dept"><i class="dot" style="background:<?= $fg ?>"></i><?= e($t['department']) ?></div>
          </div>
          <div class="due"><?= $s === 'overdue' ? '⚠' : '📅' ?> <?= e($view === 'history' ? fdate($t['updated_at']) : fdate($t['due_date'])) ?></div>
          <div><span class="badge b-<?= $s ?>"><i class="dot" style="background:<?= $fg ?>"></i><?= ucfirst($s) ?></span></div>
          <div class="prio"><i class="dot" style="background:<?= $prioColors[$prio] ?? '#94a3b8' ?>"></i><?= e($t['priority_level']) ?></div>
          <div class="owner"><span class="avatar avatar-sm" style="background:<?= $avatarBg ?>"><?= e($initials) ?></span><?= e($firstName) ?></div>
          <div>
            <div class="prog-top" style="color:<?= $fg ?>"><?= (int)$t['progress'] ?>%<span>complete</span></div>
            <div class="bar" style="background:<?= $track ?>"><i style="width:<?= (int)$t['progress'] ?>%;background:<?= $fg ?>"></i></div>
          </div>
          <div>
            <button type="button" class="btn-act <?= $cls ?>"
              data-id="<?= (int)$t['office_id'] ?>"
              data-title="<?= e($t['title']) ?>"
              data-content="<?= e($t['content']) ?>"
              data-meta="<?= e($t['department'] . ' • Due ' . fdate($t['due_date']) . ' • ' . $t['priority_level'] . ' priority') ?>"
              data-progress="<?= (int)$t['progress'] ?>"
              data-state="<?= $s ?>"><?= $label ?></button>
          </div>
        </div>
        <?php endforeach; ?>

        <?php if (!$tasks): ?>
          <div class="empty"><?= $view === 'history'
            ? 'No completed tasks yet. Finished tasks will appear here.'
            : 'No tasks to show. New tasks from your supervisor will appear here.' ?></div>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <!-- Update progress dialog -->
  <dialog id="taskDialog">
    <form method="post" action="<?= e($self) ?>?view=<?= e($view) ?>">
      <input type="hidden" name="task_id" id="taskId">
      <h2 id="dlgTitle"></h2>
      <p class="dlg-meta" id="dlgMeta"></p>
      <p class="dlg-content" id="dlgContent"></p>
      <label for="progressRange">Progress: <strong id="progressValue">0%</strong></label>
      <input type="range" name="progress" id="progressRange" min="0" max="100" step="5">
      <p class="dlg-hint">Setting progress to 100% marks the task as completed.</p>
      <div class="dlg-actions">
        <button type="button" class="btn-ghost" id="dlgCancel">Close</button>
        <button type="submit" class="btn-primary" id="dlgSave">Save progress</button>
      </div>
    </form>
  </dialog>

  <?php if (isset($_GET['saved'])): ?>
    <div class="toast show" id="toast" role="status"><?= $_GET['saved'] === 'done' ? 'Task completed' : 'Progress saved' ?></div>
  <?php endif; ?>
  <script src="./js/empl.js"></script>
</body>
</html>