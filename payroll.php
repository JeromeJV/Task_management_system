<?php
include('config/connection.php');
session_start();
require_once __DIR__ . '/config/account_access.php';
requireActiveAccount($conn);

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'payroll') {
    header("Location: index.php");
    exit();
}   
if (empty($_SESSION['payroll_csrf'])) {
    $_SESSION['payroll_csrf'] = bin2hex(random_bytes(32));
}
$initialPayrollTab = $_GET['tab'] ?? 'dashboard';
if (!in_array($initialPayrollTab, ['dashboard', 'employees', 'calculator'], true)) {
    $initialPayrollTab = 'dashboard';
}

$currentPayrollYear = (int) date('Y');
$availablePayrollYears = [$currentPayrollYear];
$payrollYearResult = $conn->query('SELECT DISTINCT YEAR(pay_date) AS payroll_year FROM payroll ORDER BY payroll_year DESC');
if (!$payrollYearResult) {
    throw new RuntimeException('Unable to load payroll filter years: ' . $conn->error);
}
while ($yearRow = $payrollYearResult->fetch_assoc()) {
    $availablePayrollYears[] = (int) $yearRow['payroll_year'];
}
$availablePayrollYears = array_values(array_unique(array_filter(
    $availablePayrollYears,
    static fn (int $year): bool => $year >= 2000 && $year <= $currentPayrollYear + 1
)));
rsort($availablePayrollYears, SORT_NUMERIC);

$selectedPayrollYear = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT);
if ($selectedPayrollYear === false || $selectedPayrollYear === null || $selectedPayrollYear < 2000 || $selectedPayrollYear > $currentPayrollYear + 1) {
    $selectedPayrollYear = $currentPayrollYear;
}
if (!in_array($selectedPayrollYear, $availablePayrollYears, true)) {
    $availablePayrollYears[] = $selectedPayrollYear;
    rsort($availablePayrollYears, SORT_NUMERIC);
}

$selectedPayrollMonth = (string) ($_GET['month'] ?? date('n'));
if (!preg_match('/^(?:all|[1-9]|1[0-2])$/', $selectedPayrollMonth)) {
    $selectedPayrollMonth = date('n');
}
$selectedPayrollCutoff = (string) ($_GET['cutoff'] ?? 'all');
if (!in_array($selectedPayrollCutoff, ['all', 'Kinsenas', 'Katapusan'], true)) {
    $selectedPayrollCutoff = 'all';
}

$payrollDateStart = $selectedPayrollMonth === 'all'
    ? sprintf('%04d-01-01', $selectedPayrollYear)
    : sprintf('%04d-%02d-01', $selectedPayrollYear, (int) $selectedPayrollMonth);
$payrollDateEnd = $selectedPayrollMonth === 'all'
    ? sprintf('%04d-12-31', $selectedPayrollYear)
    : (new DateTimeImmutable($payrollDateStart))->modify('last day of this month')->format('Y-m-d');
$payrollCutoffCondition = match ($selectedPayrollCutoff) {
    'Kinsenas' => " AND pay_period = 'Kinsenas'",
    'Katapusan' => " AND pay_period = 'Katapusan'",
    default => '',
};
$payrollFilterLabel = $selectedPayrollMonth === 'all'
    ? (string) $selectedPayrollYear
    : DateTimeImmutable::createFromFormat('!Y-n', $selectedPayrollYear . '-' . $selectedPayrollMonth)->format('F Y');
$payrollCutoffLabel = match ($selectedPayrollCutoff) {
    'Kinsenas' => ' · 1st–15th',
    'Katapusan' => ' · 16th–month end',
    default => '',
};

$employee_count_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT employee_id) AS cnt
    FROM payroll
    WHERE pay_date BETWEEN ? AND ?{$payrollCutoffCondition}
");
if (!$employee_count_stmt) {
    throw new RuntimeException('Unable to prepare filtered payroll employee count: ' . $conn->error);
}
$employee_count_stmt->bind_param('ss', $payrollDateStart, $payrollDateEnd);
if (!$employee_count_stmt->execute()) {
    $error = $employee_count_stmt->error;
    $employee_count_stmt->close();
    throw new RuntimeException('Unable to load filtered payroll employee count: ' . $error);
}
$total_employees = (int) ($employee_count_stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
$employee_count_stmt->close();

$metrics_stmt = $conn->prepare("
    SELECT
        SUM(CASE WHEN status = 'Paid' THEN 1 ELSE 0 END) AS paid_count,
        SUM(CASE WHEN status = 'Paid' THEN total_credited ELSE 0 END) AS total_disbursed,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN status = 'Pending' THEN total_credited ELSE 0 END) AS total_pending_pay
    FROM payroll
    WHERE pay_date BETWEEN ? AND ?{$payrollCutoffCondition}
");
if (!$metrics_stmt) {
    throw new RuntimeException('Unable to prepare filtered payroll metrics: ' . $conn->error);
}
$metrics_stmt->bind_param('ss', $payrollDateStart, $payrollDateEnd);
if (!$metrics_stmt->execute()) {
    $error = $metrics_stmt->error;
    $metrics_stmt->close();
    throw new RuntimeException('Unable to load filtered payroll metrics: ' . $error);
}
$metrics = $metrics_stmt->get_result()->fetch_assoc() ?: [];
$metrics_stmt->close();
$paid_count = (int) ($metrics['paid_count'] ?? 0);
$total_disbursed = (float) ($metrics['total_disbursed'] ?? 0);
$pending_count = (int) ($metrics['pending_count'] ?? 0);
$total_pending_pay = (float) ($metrics['total_pending_pay'] ?? 0);

$total_budget = $total_disbursed + $total_pending_pay;

$monthly_status_counts = [
    'Paid' => 0,
    'Pending' => 0
];
$monthly_status_stmt = $conn->prepare("
    SELECT status, COUNT(*) AS total
    FROM payroll
    WHERE pay_date BETWEEN ? AND ?{$payrollCutoffCondition}
    GROUP BY status
");
if (!$monthly_status_stmt) {
    throw new RuntimeException('Failed to prepare monthly payroll status counts: ' . $conn->error);
}
$monthly_status_stmt->bind_param('ss', $payrollDateStart, $payrollDateEnd);
if (!$monthly_status_stmt->execute()) {
    $error = $monthly_status_stmt->error;
    $monthly_status_stmt->close();
    throw new RuntimeException('Failed to load monthly payroll status counts: ' . $error);
}
$monthly_status_res = $monthly_status_stmt->get_result();
while ($monthly_status = $monthly_status_res->fetch_assoc()) {
    if (isset($monthly_status_counts[$monthly_status['status']])) {
        $monthly_status_counts[$monthly_status['status']] = (int) $monthly_status['total'];
    }
}
$monthly_status_stmt->close();

$period_payroll_totals = [
    'Kinsenas' => 0.0,
    'Katapusan' => 0.0
];
$period_payroll_stmt = $conn->prepare("
    SELECT pay_period, SUM(gross_pay) AS total
    FROM payroll
    WHERE pay_date BETWEEN ? AND ?{$payrollCutoffCondition}
    GROUP BY pay_period
");
if (!$period_payroll_stmt) {
    throw new RuntimeException('Failed to prepare monthly payroll totals: ' . $conn->error);
}
$period_payroll_stmt->bind_param('ss', $payrollDateStart, $payrollDateEnd);
if (!$period_payroll_stmt->execute()) {
    $error = $period_payroll_stmt->error;
    $period_payroll_stmt->close();
    throw new RuntimeException('Failed to load monthly payroll totals: ' . $error);
}
$period_payroll_res = $period_payroll_stmt->get_result();
while ($period_payroll = $period_payroll_res->fetch_assoc()) {
    if (isset($period_payroll_totals[$period_payroll['pay_period']])) {
        $period_payroll_totals[$period_payroll['pay_period']] = (float) $period_payroll['total'];
    }
}
$period_payroll_stmt->close();
$monthly_salary_expense = array_sum($period_payroll_totals);

// Fetch Payroll Records with Employee Details
$payrolls = $conn->prepare("
    SELECT p.*, e.employee_id, e.tin_no, e.sss_no, e.hdmf_no, e.position, e.username
    FROM payroll p 
    JOIN employee e ON p.employee_id = e.employee_id 
    WHERE p.pay_date BETWEEN ? AND ?"
    . str_replace(['pay_date', 'pay_period'], ['p.pay_date', 'p.pay_period'], $payrollCutoffCondition) . "
    ORDER BY p.payroll_id DESC
");
if (!$payrolls) {
    throw new RuntimeException('Unable to prepare filtered payroll records: ' . $conn->error);
}
$payrolls->bind_param('ss', $payrollDateStart, $payrollDateEnd);
if (!$payrolls->execute()) {
    $error = $payrolls->error;
    $payrolls->close();
    throw new RuntimeException('Unable to load filtered payroll records: ' . $error);
}
$payroll_result = $payrolls->get_result();

$payroll_records = [];
while ($r = $payroll_result->fetch_assoc()) {
    $payroll_records[] = $r;
}
$payrolls->close();
?>
<!DOCTYPE html>
<html lang="tl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payroll Dashboard</title>
  
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- FontAwesome 6 Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;0,6..72,600;1,6..72,400&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/table-scroll.css?v=<?= filemtime(__DIR__ . '/css/table-scroll.css'); ?>">
  <link rel="stylesheet" href="css/calendar-picker.css?v=<?= filemtime(__DIR__ . '/css/calendar-picker.css'); ?>">
  <link rel="stylesheet" href="css/role-form-controls.css?v=<?= filemtime(__DIR__ . '/css/role-form-controls.css'); ?>">
  <link rel="stylesheet" href="css/select-control.css?v=<?= filemtime(__DIR__ . '/css/select-control.css'); ?>">
  <link rel="stylesheet" href="css/payroll-sidebar.css?v=<?= filemtime(__DIR__ . '/css/payroll-sidebar.css'); ?>">

  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: {
              50: '#f0fdf4',
              100: '#dcfce7',
              500: '#16a34a',
              600: '#15803d',
              700: '#14532d',
            },
            paie: {
              bg: '#FAF8F5',
              card: '#FFFFFF',
              dark: '#1E1E1E',
              border: '#E4E4E7'
            }
          },
          fontFamily: {
            sans: ['Inter', 'sans-serif'],
            serif: ['Newsreader', 'serif'],
            mono: ['JetBrains Mono', 'monospace'],
          }
        }
      }
    }
  </script>

  <style>
    body { background-color: #f8fafc; color: #1E1E1E; }
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: #f8fafc; }
    ::-webkit-scrollbar-thumb { background: #D4D4D8; border-radius: 9999px; }
    ::-webkit-scrollbar-thumb:hover { background: #A1A1AA; }
    .calc-input:focus { outline: none; border-color: #16a34a; box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.15); }
    
    /* Active indicator sa Supervisor Sidebar */
    .nav-item.active {
      color: #000000;
      background-color: #f9fafb;
      font-weight: 700;
    }
    .nav-item.active::before {
      content: '';
      position: absolute;
      left: 0;
      top: 0;
      height: 100%;
      width: 4px;
      background-color: #16a34a;
    }

    .payroll-loading-overlay {
      position: fixed;
      inset: 0;
      z-index: 1000;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 20px;
      background: rgba(43, 62, 66, 0.78);
      backdrop-filter: blur(5px);
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.3s ease;
    }
    .payroll-loading-overlay.active {
      opacity: 1;
      pointer-events: auto;
    }
    .payroll-loading-card {
      width: min(400px, 100%);
      padding: 32px 28px;
      border-radius: 14px;
      background: #3a5358;
      color: #fff;
      text-align: center;
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
    }
    .payroll-loading-card h2 {
      margin-bottom: 8px;
      font-size: 1.25rem;
    }
    .payroll-loading-card p {
      margin-bottom: 18px;
      color: #cbd5e1;
      font-size: 0.9rem;
    }
    .receipt-container {
      position: relative;
      display: flex;
      width: 190px;
      height: 190px;
      justify-content: center;
      margin: 0 auto;
      perspective: 600px;
    }
    .printer-slot {
      position: absolute;
      top: 0;
      z-index: 3;
      width: 190px;
      height: 12px;
      border-radius: 6px;
      background: #1e293b;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
    }
    .receipt-feed {
      position: absolute;
      top: 6px;
      z-index: 2;
      width: 160px;
      height: 155px;
      overflow: hidden;
    }
    .receipt-paper {
      position: absolute;
      top: 0;
      left: 0;
      width: 160px;
      padding: 12px 10px;
      border-radius: 2px 2px 0 0;
      background: #fff;
      color: #1e293b;
      box-shadow: 0 10px 15px rgba(0, 0, 0, 0.2);
      opacity: 0;
      transform: translate3d(0, -100%, 0);
      will-change: transform, opacity;
      transition:
        transform 1.8s cubic-bezier(0.22, 0.75, 0.25, 1),
        opacity 0.55s ease-out;
    }
    .receipt-paper::after {
      position: absolute;
      bottom: -8px;
      left: 0;
      width: 100%;
      height: 8px;
      background:
        linear-gradient(-135deg, #fff 4px, transparent 0),
        linear-gradient(135deg, #fff 4px, transparent 0);
      background-size: 8px 8px;
      content: "";
    }
    .receipt-header {
      margin-bottom: 8px;
      padding-bottom: 4px;
      border-bottom: 1px dashed #94a3b8;
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.5px;
      text-align: center;
    }
    .receipt-line {
      height: 5px;
      margin-bottom: 6px;
      border-radius: 2px;
      background: #e2e8f0;
    }
    .receipt-line.short { width: 60%; }
    .receipt-line.medium { width: 80%; }
    .receipt-total {
      display: flex;
      justify-content: space-between;
      margin-top: 10px;
      padding-top: 6px;
      border-top: 1px dashed #94a3b8;
      color: #16a34a;
      font-size: 10px;
      font-weight: 700;
    }
    .payroll-success-badge {
      position: absolute;
      right: 8px;
      bottom: 17px;
      display: flex;
      width: 32px;
      height: 32px;
      justify-content: center;
      align-items: center;
      border-radius: 50%;
      background: #22c55e;
      color: #fff;
      font-size: 18px;
      font-weight: 700;
      box-shadow: 0 4px 10px rgba(34, 197, 94, 0.5);
      transform: scale(0);
      transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .payroll-loading-overlay.step-print .receipt-paper {
      opacity: 1;
      transform: translate3d(0, 8px, 0);
    }
    .payroll-loading-overlay.step-success .payroll-success-badge {
      transform: scale(1);
    }
    .payroll-loading-status {
      margin-top: 22px;
      color: #e2e8f0;
      font-size: 14px;
      font-weight: 600;
      letter-spacing: 0.3px;
    }
    .payroll-loading-overlay.step-success .payroll-loading-status {
      color: #86efac;
    }
    .payroll-review-dialog {
      width: min(440px, calc(100% - 32px));
      padding: 0;
      border: 0;
      border-radius: 20px;
      color: #1e293b;
      box-shadow: 0 24px 70px rgba(15, 23, 42, 0.28);
    }
    .payroll-review-dialog::backdrop {
      background: rgba(15, 23, 42, 0.52);
      backdrop-filter: blur(3px);
    }
    .payroll-review-content { padding: 28px; }
    .payroll-review-icon {
      display: grid;
      width: 48px;
      height: 48px;
      place-items: center;
      margin-bottom: 18px;
      border-radius: 15px;
      background: #dcfce7;
      color: #15803d;
      font-size: 22px;
    }
    .payroll-review-content h2 { margin: 0; font-size: 20px; font-weight: 750; }
    .payroll-review-content p { margin: 10px 0 0; color: #64748b; line-height: 1.6; }
    .payroll-review-note {
      margin-top: 16px;
      padding: 12px 14px;
      border: 1px solid #fde68a;
      border-radius: 12px;
      background: #fffbeb;
      color: #854d0e;
      font-size: 13px;
      line-height: 1.5;
    }
    .payroll-review-error {
      margin-top: 14px;
      color: #b91c1c;
      font-size: 13px;
    }
    .payroll-review-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px; }
    .payroll-review-button {
      min-height: 40px;
      padding: 0 15px;
      border: 0;
      border-radius: 10px;
      font: inherit;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
    }
    .payroll-review-button:disabled { cursor: wait; opacity: 0.65; }
    .payroll-review-cancel { background: #f1f5f9; color: #334155; }
    .payroll-review-confirm { background: #15803d; color: #fff; }
    @media (prefers-reduced-motion: reduce) {
      .payroll-loading-overlay,
      .receipt-paper,
      .payroll-success-badge {
        transition-duration: 0.01ms;
      }
    }
  </style>
</head>
<body class="font-sans antialiased text-slate-800">
<?php include __DIR__ . '/config/notification_bell.php'; ?>

  <!-- App Wrapper -->
  <div class="payroll-app-layout flex h-screen overflow-hidden">

    <?php $payrollSidebarActive = 'dashboard'; include __DIR__ . '/config/payroll_sidebar.php'; ?>

    <!-- RIGHT CONTENT AREA -->
    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">

      <!-- HEADER -->
      <header class="bg-white border-b border-zinc-200 px-6 py-4 sticky top-0 z-20 flex flex-col sm:flex-row justify-between sm:items-center gap-4">
        <div>
          <h1 class="text-2xl sm:text-3xl font-serif font-medium text-zinc-900 tracking-tight">TaskTrack</h1>
          <p class="text-xs text-zinc-500">Payroll System • Mid-month (1st-15th) and End-of-month (16th-30th/31st)</p>
        </div>

        <div class="flex items-center gap-3">
          <div class="hidden lg:flex items-center gap-2 bg-emerald-50 text-emerald-900 px-3 py-1.5 rounded-lg text-xs font-mono border border-emerald-200">
            <i class="fa-solid fa-building text-emerald-600"></i>
            <span class="font-bold">Pasig HQ</span>
            <span class="text-emerald-300">|</span>
            <span class="font-semibold text-emerald-800">Live Period</span>
          </div>

          <!-- GREEN ASSIGN/COMPUTE BUTTON -->
          <button onclick="switchTab('calculator')" class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-semibold px-4 py-2.5 rounded-lg transition flex items-center gap-2 shadow-xs">
            <i class="fa-solid fa-plus text-xs"></i>
            <span>Compute New Payroll</span>
          </button>
        </div>
      </header>

      <!-- MAIN CONTENT CONTAINER -->
      <main class="p-4 sm:p-6 max-w-7xl w-full mx-auto space-y-6">

        <!-- NAV TABS -->
        <div class="border-b border-zinc-200 flex items-center gap-6 text-sm overflow-x-auto">
          <button onclick="switchTab('dashboard')" id="tab-dashboard" class="pb-3 border-b-2 border-emerald-600 font-bold text-zinc-900 flex items-center gap-2 shrink-0">
            <i class="fa-solid fa-chart-pie text-emerald-600"></i> Dashboard & Analytics
          </button>
          <button onclick="switchTab('employees')" id="tab-employees" class="pb-3 border-b-2 border-transparent text-zinc-500 hover:text-zinc-800 font-medium flex items-center gap-2 shrink-0 transition">
            <i class="fa-solid fa-list-check text-zinc-400"></i> Employee Masterlist
          </button>
          <button onclick="switchTab('calculator')" id="tab-calculator" class="pb-3 border-b-2 border-transparent text-zinc-500 hover:text-zinc-800 font-medium flex items-center gap-2 shrink-0 transition">
            <i class="fa-solid fa-calculator text-zinc-400"></i> Calculate Payroll
          </button>
        </div>

        <!-- ================= VIEW 1: DASHBOARD ================= -->
        <div id="view-dashboard" class="space-y-6">

          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-white p-4 rounded-2xl border border-zinc-200 shadow-2xs">
            <div>
              <h2 class="text-sm font-bold text-zinc-900">Dashboard Filters</h2>
              <p class="text-[11px] text-zinc-500">Filters apply to all dashboard metrics and charts · <?= htmlspecialchars($payrollFilterLabel . $payrollCutoffLabel, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <form method="GET" action="payroll.php" class="flex w-full sm:w-auto flex-nowrap items-center gap-2 overflow-x-auto pb-1">
              <input type="hidden" name="tab" value="dashboard">
              <label for="dashboardPayrollYear" class="sr-only">Payroll year</label>
              <select id="dashboardPayrollYear" name="year" onchange="this.form.submit()" class="shrink-0 rounded-lg border border-zinc-200 px-2 py-2 text-xs">
                <?php foreach ($availablePayrollYears as $year): ?>
                  <option value="<?= $year; ?>" <?= $selectedPayrollYear === $year ? 'selected' : ''; ?>><?= $year; ?></option>
                <?php endforeach; ?>
              </select>
              <label for="dashboardPayrollMonth" class="sr-only">Payroll month</label>
              <select id="dashboardPayrollMonth" name="month" onchange="this.form.submit()" class="shrink-0 rounded-lg border border-zinc-200 px-2 py-2 text-xs">
                <option value="all" <?= $selectedPayrollMonth === 'all' ? 'selected' : ''; ?>>All months (yearly)</option>
                <?php for ($month = 1; $month <= 12; $month++): ?>
                  <option value="<?= $month; ?>" <?= (int) $selectedPayrollMonth === $month ? 'selected' : ''; ?>><?= date('F', mktime(0, 0, 0, $month, 1)); ?></option>
                <?php endfor; ?>
              </select>
              <label for="dashboardPayrollCutoff" class="sr-only">Pay cutoff</label>
              <select id="dashboardPayrollCutoff" name="cutoff" onchange="this.form.submit()" class="shrink-0 rounded-lg border border-zinc-200 px-2 py-2 text-xs">
                <option value="all" <?= $selectedPayrollCutoff === 'all' ? 'selected' : ''; ?>>All cutoffs</option>
                <option value="Kinsenas" <?= $selectedPayrollCutoff === 'Kinsenas' ? 'selected' : ''; ?>>1st–15th</option>
                <option value="Katapusan" <?= $selectedPayrollCutoff === 'Katapusan' ? 'selected' : ''; ?>>16th–month end</option>
              </select>
            </form>
          </div>

          <!-- 4 METRIC CARDS -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-zinc-200 shadow-2xs space-y-2">
              <div class="flex justify-between items-center text-zinc-500 text-xs font-medium">
                <span>Employees with Payroll</span>
                <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center"><i class="fa-solid fa-users"></i></div>
              </div>
              <div class="text-2xl sm:text-3xl font-extrabold text-zinc-900"><?php echo $total_employees; ?></div>
              <p class="text-[11px] text-zinc-400">In selected period</p>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-zinc-200 shadow-2xs space-y-2">
              <div class="flex justify-between items-center text-zinc-500 text-xs font-medium">
                <span>Paid Count</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center"><i class="fa-solid fa-circle-check"></i></div>
              </div>
              <div class="text-2xl sm:text-3xl font-extrabold text-emerald-600"><?php echo $paid_count; ?></div>
              <p class="text-[11px] text-emerald-700 font-medium">₱<?php echo number_format($total_disbursed, 2); ?> Disbursed</p>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-zinc-200 shadow-2xs space-y-2">
              <div class="flex justify-between items-center text-zinc-500 text-xs font-medium">
                <span>Pending Count</span>
                <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center"><i class="fa-solid fa-clock"></i></div>
              </div>
              <div class="text-2xl sm:text-3xl font-extrabold text-amber-600"><?php echo $pending_count; ?></div>
              <p class="text-[11px] text-amber-700 font-medium">₱<?php echo number_format($total_pending_pay, 2); ?> Unpaid</p>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-zinc-200 shadow-2xs space-y-2">
              <div class="flex justify-between items-center text-zinc-500 text-xs font-medium">
                <span>Total Budget</span>
                <div class="w-8 h-8 rounded-lg bg-slate-100 text-slate-800 flex items-center justify-center"><i class="fa-solid fa-wallet"></i></div>
              </div>
              <div class="text-2xl sm:text-3xl font-extrabold text-zinc-900 font-mono">₱<?php echo number_format($total_budget, 2); ?></div>
              <p class="text-[11px] text-zinc-400">Total for selected period</p>
            </div>
          </div>

          <!-- CHARTS SECTION -->
          <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-5 bg-white p-6 rounded-2xl border border-zinc-200 shadow-2xs space-y-4">
              <h3 class="font-bold text-zinc-900 text-base border-b border-zinc-100 pb-3">Status Ratio</h3>
              <div class="relative flex items-center justify-center h-56">
                <canvas id="chart-status-pie"></canvas>
              </div>
            </div>

            <div class="lg:col-span-7 bg-white p-6 rounded-2xl border border-zinc-200 shadow-2xs space-y-4">
              <div class="flex flex-col gap-1 border-b border-zinc-100 pb-3">
                <h3 class="font-bold text-zinc-900 text-base"><?= $selectedPayrollMonth === 'all' ? 'Yearly' : 'Monthly'; ?> Salary Expense</h3>
                <p class="text-[11px] text-zinc-500">Gross payroll before deductions · <?= htmlspecialchars($payrollFilterLabel . $payrollCutoffLabel, ENT_QUOTES, 'UTF-8'); ?></p>
              </div>
              <div class="relative flex items-center justify-center h-56">
                <canvas id="chart-payroll-bar"></canvas>
              </div>
            </div>
          </div>

        </div>

        <!-- ================= VIEW 2: EMPLOYEES MASTERLIST ================= -->
        <div id="view-employees" class="hidden space-y-5">
          <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-5 rounded-2xl border border-zinc-200 shadow-2xs">
            <div>
              <h3 class="text-xl font-bold text-zinc-900">Employee Disbursement Masterlist</h3>
              <p class="text-xs text-zinc-500">Review payroll records for <?= htmlspecialchars($payrollFilterLabel . $payrollCutoffLabel, ENT_QUOTES, 'UTF-8'); ?>. This is not a payout receipt.</p>
            </div>
            <div class="flex flex-col gap-2 w-full sm:w-auto">
              <form method="GET" action="payroll.php" class="flex w-full sm:w-auto flex-nowrap items-center gap-2 overflow-x-auto pb-1">
                <input type="hidden" name="tab" value="employees">
                <label for="employeesPayrollYear" class="sr-only">Payroll year</label>
                <select id="employeesPayrollYear" name="year" onchange="this.form.submit()" class="shrink-0 rounded-lg border border-zinc-200 px-2 py-1.5 text-xs">
                  <?php foreach ($availablePayrollYears as $year): ?>
                    <option value="<?= $year; ?>" <?= $selectedPayrollYear === $year ? 'selected' : ''; ?>><?= $year; ?></option>
                  <?php endforeach; ?>
                </select>
                <label for="employeesPayrollMonth" class="sr-only">Payroll month</label>
                <select id="employeesPayrollMonth" name="month" onchange="this.form.submit()" class="shrink-0 rounded-lg border border-zinc-200 px-2 py-1.5 text-xs">
                  <option value="all" <?= $selectedPayrollMonth === 'all' ? 'selected' : ''; ?>>All months (yearly)</option>
                  <?php for ($month = 1; $month <= 12; $month++): ?>
                    <option value="<?= $month; ?>" <?= (int) $selectedPayrollMonth === $month ? 'selected' : ''; ?>><?= date('F', mktime(0, 0, 0, $month, 1)); ?></option>
                  <?php endfor; ?>
                </select>
                <label for="employeesPayrollCutoff" class="sr-only">Pay cutoff</label>
                <select id="employeesPayrollCutoff" name="cutoff" onchange="this.form.submit()" class="shrink-0 rounded-lg border border-zinc-200 px-2 py-1.5 text-xs">
                  <option value="all" <?= $selectedPayrollCutoff === 'all' ? 'selected' : ''; ?>>All cutoffs</option>
                  <option value="Kinsenas" <?= $selectedPayrollCutoff === 'Kinsenas' ? 'selected' : ''; ?>>1st–15th</option>
                  <option value="Katapusan" <?= $selectedPayrollCutoff === 'Katapusan' ? 'selected' : ''; ?>>16th–month end</option>
                </select>
              </form>
              <input type="search" id="searchInput" oninput="filterTable()" placeholder="Search employee..." class="px-3.5 py-2 border border-zinc-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500 outline-none w-full">
            </div>
          </div>

          <div class="bg-white rounded-2xl border border-zinc-200 shadow-2xs overflow-hidden">
            <div class="overflow-x-auto table-scroll">
              <table class="w-full text-left border-collapse" id="payrollTable">
                <thead>
                  <tr class="border-b border-zinc-200 bg-zinc-50/80 text-[11px] uppercase tracking-wider text-zinc-500 font-bold">
                    <th class="p-4">Employee</th>
                    <th class="p-4">Period</th>
                    <th class="p-4">Pay Date</th>
                    <th class="p-4 text-right">Gross Pay</th>
                    <th class="p-4 text-right">Deductions</th>
                    <th class="p-4 text-right">Net Credited</th>
                    <th class="p-4 text-center">Status</th>
                    <th class="p-4 text-center">Action</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 text-xs">
                  <?php foreach($payroll_records as $row): ?>
                  <tr class="hover:bg-zinc-50 transition">
                    <td class="p-4 font-medium text-slate-800">
                      <p class="font-bold"><?php echo htmlspecialchars($row['full_name'] ?? $row['username']); ?></p>
                      <p class="text-[10px] text-zinc-400">ID: #<?php echo htmlspecialchars($row['employee_id']); ?></p>
                    </td>
                    <td class="p-4">
                      <span class="px-2 py-0.5 rounded text-[10px] font-semibold <?php echo $row['pay_period'] === 'Kinsenas' ? 'bg-blue-50 text-blue-600' : 'bg-purple-50 text-purple-600'; ?>">
                        <?php echo $row['pay_period'] === 'Kinsenas' ? '1st Half (Kinsenas)' : '2nd Half (Katapusan)'; ?>
                      </span>
                    </td>
                    <td class="p-4 text-zinc-600"><?php echo htmlspecialchars($row['pay_date']); ?></td>
                    <td class="p-4 text-right font-mono">₱<?php echo number_format($row['gross_pay'], 2); ?></td>
                    <td class="p-4 text-right font-mono text-red-500">-₱<?php echo number_format($row['total_deductions'], 2); ?></td>
                    <td class="p-4 text-right font-mono font-bold text-slate-800">₱<?php echo number_format($row['total_credited'], 2); ?></td>
                    <td class="p-4 text-center">
                      <?php if ($row['status'] === 'Paid'): ?>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">✓ Paid · reviewed</span>
                        <p class="mt-1 text-[10px] text-zinc-500">
                          <?php echo !empty($row['paid_at']) ? 'Review confirmed ' . htmlspecialchars(date('M j, Y', strtotime($row['paid_at']))) : 'Review confirmation date not recorded'; ?>
                        </p>
                      <?php else: ?>
                        <span class="block mb-1 text-[10px] font-bold text-amber-700">⏳ Pending</span>
                        <button onclick="markPayrollPaid(<?php echo (int) $row['payroll_id']; ?>)" class="px-2.5 py-1 rounded-full text-[10px] font-bold transition bg-emerald-100 text-emerald-700 hover:bg-emerald-200">
                          Confirm Review
                        </button>
                      <?php endif; ?>
                    </td>
                    <td class="p-4 text-center">
                      <?php if ($row['status'] === 'Paid'): ?>
                        <a href="payroll_edit.php?payroll_id=<?php echo (int) $row['payroll_id']; ?>" class="px-3 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-800 rounded-lg font-bold transition text-[11px] inline-flex items-center gap-1.5">Correct Paid Record</a>
                      <?php endif; ?>
                      <button onclick='printPayslip(<?php echo json_encode($row); ?>)' class="px-3 py-1.5 bg-zinc-100 hover:bg-emerald-700 hover:text-white text-zinc-700 rounded-lg font-bold transition text-[11px] inline-flex items-center gap-1.5">
                        <i class="fa-solid fa-print"></i> Payslip
                      </button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- ================= VIEW 3: PAYROLL CALCULATOR ================= -->
        <div id="view-calculator" class="hidden space-y-6">
          <div class="bg-emerald-50/50 border border-emerald-200 rounded-2xl p-6 shadow-2xs space-y-4">
            <h3 class="text-xl font-bold text-zinc-900">Compute & Record New Payroll</h3>
            
            <form action="config/Payroll_API.php" method="POST" class="space-y-4" id="payrollForm">
              <input type="hidden" name="action" value="create_payroll">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['payroll_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
              <p id="payrollFormError" class="hidden rounded-lg border border-red-300 bg-red-50 p-3 text-sm font-semibold text-red-800" role="alert"></p>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-bold text-zinc-800 mb-1">Select Employee</label>
                  <select name="employee_id" id="calc_employee_id" required onchange="onEmployeeSelect()" disabled class="w-full px-3 py-2 border rounded-xl text-xs bg-white focus:ring-2 focus:ring-emerald-500 outline-none">
                    <option value="">Loading eligible employees...</option>
                  </select>
                </div>

                <div>
                  <label class="block text-xs font-bold text-zinc-800 mb-1">Pay Period</label>
                  <select name="pay_period" id="calc_pay_period" onchange="refreshEligibleEmployees()" class="w-full px-3 py-2 border rounded-xl text-xs bg-white focus:ring-2 focus:ring-emerald-500 outline-none">
                    <option value="Kinsenas" <?= (int) date('j') <= 15 ? 'selected' : ''; ?>>1st Half (1st - 15th)</option>
                    <option value="Katapusan" <?= (int) date('j') > 15 ? 'selected' : ''; ?>>2nd Half (16th - 31st)</option>
                  </select>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-xs font-bold text-zinc-800 mb-1">Daily Rate (₱)</label>
                  <input type="number" step="0.01" id="daily_rate" name="daily_rate" value="755.00" oninput="calculateRegularPay()" class="w-full px-3 py-2 border rounded-xl text-xs bg-white outline-none font-mono">
                </div>
                <div>
                  <label class="block text-xs font-bold text-zinc-800 mb-1">Days Worked</label>
                  <input type="number" step="0.5" id="days_worked" name="days_worked" value="0" readonly oninput="calculateRegularPay()" class="w-full px-3 py-2 border rounded-xl text-xs bg-zinc-100 outline-none font-mono font-bold text-emerald-700 cursor-not-allowed" title="Fetched from attendance">
                </div>
                <div>
                  <label class="block text-xs font-bold text-zinc-800 mb-1">Pay Date</label>
                  <input type="date" name="pay_date" required value="<?php echo date('Y-m-d'); ?>" class="calendar-control w-full px-3 py-2 border rounded-xl text-xs bg-white outline-none">
                </div>
              </div>

              <hr class="my-2 border-emerald-200">

              <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                  <label class="block text-[11px] text-zinc-600 font-semibold">Regular Pay (₱)</label>
                  <input type="number" step="0.01" id="regular_pay" name="regular_pay" value="000.00" class="w-full px-2.5 py-1.5 border rounded-lg text-xs font-mono bg-emerald-100 font-bold" readonly>
                </div>
                <div>
                  <label class="block text-[11px] text-zinc-600">Paid SIL Leave (₱)</label>
                  <input type="number" step="0.01" id="paid_leaves_display" placeholder="0.00" readonly class="w-full px-2.5 py-1.5 border rounded-lg text-xs font-mono bg-zinc-100">
                  <p class="text-[10px] text-zinc-500 col-span-2">Calculated automatically from HR-recorded paid SIL. Unpaid LWOP is not included in pay.</p>
                </div>
              </div>

              <div class="grid grid-cols-3 gap-3">
                <div>
                  <label class="block text-[11px] text-zinc-600">Late Deduction (₱)</label>
                  <input type="number" step="0.01" id="late_deduction" name="late_deduction" value="0.00" readonly class="w-full px-2.5 py-1.5 border rounded-lg text-xs font-mono bg-zinc-100">
                  <p id="attendance-calculation-message" class="mt-1 text-[10px] text-zinc-500" aria-live="polite">Deduction starts after 8:01 AM and uses completed late minutes through 12:00 PM × daily rate ÷ 8 ÷ 60.</p>
                  <p id="attendance-validation-warning" class="mt-2 hidden rounded-lg border border-amber-300 bg-amber-50 p-2 text-[11px] font-semibold text-amber-800" role="alert"></p>
                </div>
                <div>
                  <label class="block text-[11px] text-zinc-600">SSS / Calamity Loan (₱)</label>
                  <input type="number" step="0.01" name="sss" value="500.00" class="w-full px-2.5 py-1.5 border rounded-lg text-xs font-mono">
                </div>
                <div>
                  <label class="block text-[11px] text-zinc-600">Pag-IBIG / HDMF (₱)</label>
                  <input type="number" step="0.01" name="pagibig" value="100.00" class="w-full px-2.5 py-1.5 border rounded-lg text-xs font-mono">
                </div>
              </div>

              <div class="grid grid-cols-3 gap-3">
                <div>
                  <label class="block text-[11px] text-zinc-600">HMO (₱)</label>
                  <input type="number" step="0.01" name="hmo" placeholder="000.00" class="w-full px-2.5 py-1.5 border rounded-lg text-xs font-mono">
                </div>
                <div>
                  <label class="block text-[11px] text-zinc-600">Reimbursement (₱)</label>
                  <input type="number" step="0.01" name="reimbursement" placeholder="000.00" class="w-full px-2.5 py-1.5 border rounded-lg text-xs font-mono">
                </div>
              </div>

              <div class="pt-4 flex justify-end gap-3">
                <button type="submit" class="px-5 py-2.5 bg-emerald-700 hover:bg-emerald-800 text-white font-bold rounded-xl text-xs transition shadow-xs">
                  Save Payroll Computation
                </button>
              </div>
            </form>
          </div>
        </div>

      </main>
    </div>
  </div>

  <div
    id="payrollLoadingOverlay"
    class="payroll-loading-overlay"
    role="status"
    aria-live="polite"
    aria-hidden="true"
  >
    <div class="payroll-loading-card">
      <h2>Processing Payroll</h2>
      <p>Your computation is being saved. Please wait.</p>
      <div class="receipt-container" aria-hidden="true">
        <div class="printer-slot"></div>
        <div class="receipt-feed">
          <div class="receipt-paper">
            <div class="receipt-header">TASKTRACK PAYROLL</div>
            <div class="receipt-line medium"></div>
            <div class="receipt-line short"></div>
            <div class="receipt-line"></div>
            <div class="receipt-line medium"></div>
            <div class="receipt-total">
              <span>COMPUTATION:</span>
              <span>READY</span>
            </div>
            <div class="payroll-success-badge">✓</div>
          </div>
        </div>
      </div>
      <div id="payrollLoadingStatus" class="payroll-loading-status">Calculating payroll data...</div>
    </div>
  </div>

  <dialog id="payrollReviewDialog" class="payroll-review-dialog" aria-labelledby="payrollReviewTitle" aria-describedby="payrollReviewDescription">
    <div class="payroll-review-content">
      <div class="payroll-review-icon" aria-hidden="true"><i class="fa-solid fa-clipboard-check"></i></div>
      <h2 id="payrollReviewTitle">Confirm payroll review</h2>
      <p id="payrollReviewDescription">Have you checked this payroll record and its amounts?</p>
      <div class="payroll-review-note">
        Confirming changes the system status to <strong>Paid</strong> as a review record only. It does not confirm or record an actual payout.
      </div>
      <p id="payrollReviewError" class="payroll-review-error" role="alert" hidden></p>
      <div class="payroll-review-actions">
        <button id="cancelPayrollReview" type="button" class="payroll-review-button payroll-review-cancel">Review first</button>
        <button id="confirmPayrollReview" type="button" class="payroll-review-button payroll-review-confirm">Yes, confirm review</button>
      </div>
    </div>
  </dialog>

  <!-- SCRIPT HANDLERS -->
  <script>
    const payrollForm = document.getElementById('payrollForm');
    const payrollLoadingOverlay = document.getElementById('payrollLoadingOverlay');
    const payrollLoadingStatus = document.getElementById('payrollLoadingStatus');

    payrollForm.addEventListener('submit', async function (event) {
      event.preventDefault();
      if (payrollForm.dataset.submitting === 'true') {
        return;
      }

      const employeeSelect = document.getElementById('calc_employee_id');
      const errorMessage = document.getElementById('payrollFormError');
      const submitButton = event.submitter || payrollForm.querySelector('button[type="submit"]');
      errorMessage.classList.add('hidden');
      errorMessage.textContent = '';

      if (employeeSelect.disabled || !employeeSelect.value) {
        errorMessage.textContent = employeeSelect.disabled
          ? 'Wait for the employee list to finish loading, then select an eligible employee.'
          : 'Select an employee before saving payroll.';
        errorMessage.classList.remove('hidden');
        return;
      }

      const amount = (selector) => Number(document.querySelector(selector)?.value || 0);
      const grossPay = amount('#regular_pay') + amount('#paid_leaves_display');
      const reimbursement = amount('[name="reimbursement"]');
      const deductions = [
        '#late_deduction',
        '[name="sss"]',
        '[name="pagibig"]',
        '[name="philhealth"]',
        '[name="calamity_loan"]',
        '[name="hmo"]'
      ].reduce((total, selector) => total + amount(selector), 0);
      if (grossPay + reimbursement < deductions) {
        errorMessage.textContent = `Total deductions (₱${deductions.toFixed(2)}) exceed gross pay plus reimbursement (₱${(grossPay + reimbursement).toFixed(2)}). Adjust the deduction amounts before saving.`;
        errorMessage.classList.remove('hidden');
        return;
      }

      payrollForm.dataset.submitting = 'true';
      submitButton.disabled = true;
      payrollLoadingOverlay.className = 'payroll-loading-overlay active';
      payrollLoadingOverlay.setAttribute('aria-hidden', 'false');
      payrollLoadingStatus.textContent = 'Saving payroll computation...';

      try {
        const payrollEndpoint = new URL(payrollForm.getAttribute('action'), window.location.href);
        const response = await fetch(payrollEndpoint, {
          method: 'POST',
          body: new FormData(payrollForm),
          credentials: 'same-origin'
        });
        if (response.redirected) {
          sessionStorage.setItem('payrollTabAfterReload', 'employees');
          window.location.assign(response.url);
          return;
        }

        const responseText = await response.text();
        let data;
        try {
          data = JSON.parse(responseText);
        } catch {
          console.error('Payroll API returned a non-JSON response:', response.status, responseText);
          throw new Error(`Payroll could not be saved (HTTP ${response.status}). The server returned an invalid response; check the server error log or contact the administrator.`);
        }
        if (!response.ok || data.status !== 'success') {
          throw new Error(data.message || 'Could not save payroll. Please review the entered values and try again.');
        }
      } catch (error) {
        console.error(error);
        errorMessage.textContent = error.message || 'Could not save payroll. Please try again.';
        errorMessage.classList.remove('hidden');
        payrollLoadingOverlay.classList.remove('active');
        payrollLoadingOverlay.setAttribute('aria-hidden', 'true');
        payrollForm.dataset.submitting = 'false';
        submitButton.disabled = false;
      }
    });

    let attendanceSummaryRequest = 0;
    let excessiveLateDays = [];
    let eligibleEmployeesRequest = 0;

    async function refreshEligibleEmployees() {
      const requestId = ++eligibleEmployeesRequest;
      const employeeSelect = document.getElementById('calc_employee_id');
      const selectedEmployeeId = employeeSelect.value;
      const period = document.getElementById('calc_pay_period').value;
      const payDate = document.querySelector('input[name="pay_date"]').value;
      employeeSelect.disabled = true;
      employeeSelect.innerHTML = '<option value="">Loading eligible employees...</option>';

      try {
        const params = new URLSearchParams({
          action: 'get_available_employees',
          pay_period: period,
          pay_date: payDate
        });
        const response = await fetch(`config/Payroll_API.php?${params.toString()}`);
        if (!response.ok) {
          throw new Error(`Employee lookup failed (${response.status}).`);
        }
        const data = await response.json();
        if (requestId !== eligibleEmployeesRequest) {
          return;
        }
        if (data.status !== 'success') {
          throw new Error(data.message || 'Could not load eligible employees.');
        }

        const employees = data.employees || [];
        employeeSelect.replaceChildren(new Option(
          employees.length ? '-- Choose Employee --' : 'No employees available for this pay period',
          ''
        ));
        employees.forEach(employee => {
          const option = new Option(
            `${employee.username} (#${employee.employee_id})`,
            employee.employee_id
          );
          option.dataset.rate = employee.daily_rate;
          employeeSelect.add(option);
        });
        employeeSelect.disabled = employees.length === 0;

        if (employees.some(employee => String(employee.employee_id) === selectedEmployeeId)) {
          employeeSelect.value = selectedEmployeeId;
        }
        onEmployeeSelect();
      } catch (error) {
        if (requestId !== eligibleEmployeesRequest) {
          return;
        }
        console.error(error);
        employeeSelect.replaceChildren(new Option('Could not load employees. Please retry.', ''));
        employeeSelect.disabled = true;
        fetchEmployeeAttendance();
      }
    }

    function switchTab(tabName) {
      document.getElementById('view-dashboard').classList.add('hidden');
      document.getElementById('view-employees').classList.add('hidden');
      document.getElementById('view-calculator').classList.add('hidden');

      // Reset active class sa sidebar links
      document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));

      // Reset tab button style
      document.getElementById('tab-dashboard').className = "pb-3 border-b-2 border-transparent text-zinc-500 hover:text-zinc-800 font-medium flex items-center gap-2 shrink-0 transition";
      document.getElementById('tab-employees').className = "pb-3 border-b-2 border-transparent text-zinc-500 hover:text-zinc-800 font-medium flex items-center gap-2 shrink-0 transition";
      document.getElementById('tab-calculator').className = "pb-3 border-b-2 border-transparent text-zinc-500 hover:text-zinc-800 font-medium flex items-center gap-2 shrink-0 transition";

      // Set current tab active
      document.getElementById(`view-${tabName}`).classList.remove('hidden');
      document.getElementById(`tab-${tabName}`).className = "pb-3 border-b-2 border-emerald-600 font-bold text-zinc-900 flex items-center gap-2 shrink-0";
      
      let navBtn = document.getElementById(`nav-${tabName === 'calculator' ? 'calc' : tabName}`);
      if(navBtn) navBtn.classList.add('active');
    }

    function filterTable() {
      let input = document.getElementById('searchInput').value.toLowerCase();
      let rows = document.querySelectorAll('#payrollTable tbody tr');
      rows.forEach(row => {
        let text = row.innerText.toLowerCase();
        row.style.display = text.includes(input) ? '' : 'none';
      });
    }

    const payrollReviewDialog = document.getElementById('payrollReviewDialog');
    const payrollReviewError = document.getElementById('payrollReviewError');
    const confirmPayrollReviewButton = document.getElementById('confirmPayrollReview');
    let payrollIdToReview = null;

    function markPayrollPaid(id) {
      payrollIdToReview = id;
      payrollReviewError.hidden = true;
      payrollReviewError.textContent = '';
      confirmPayrollReviewButton.disabled = false;
      confirmPayrollReviewButton.textContent = 'Yes, confirm review';
      payrollReviewDialog.showModal();
    }

    document.getElementById('cancelPayrollReview').addEventListener('click', () => {
      payrollReviewDialog.close();
    });

    confirmPayrollReviewButton.addEventListener('click', async () => {
      if (!payrollIdToReview) {
        return;
      }
      confirmPayrollReviewButton.disabled = true;
      confirmPayrollReviewButton.textContent = 'Saving...';
      payrollReviewError.hidden = true;
      try {
        const response = await fetch('config/Payroll_API.php', {
          method: 'POST',
          body: new URLSearchParams({
            action: 'mark_paid',
            payroll_id: String(payrollIdToReview),
            review_confirmed: '1',
            csrf_token: <?php echo json_encode($_SESSION['payroll_csrf']); ?>
          })
        });
        const data = await response.json();
        if (!response.ok || data.status !== 'success') {
          throw new Error(data.message || 'Could not mark payroll as paid.');
        }
        sessionStorage.setItem('payrollTabAfterReload', 'employees');
        location.reload();
      } catch (error) {
        console.error(error);
        payrollReviewError.textContent = error.message || 'Could not confirm the payroll review. Please try again.';
        payrollReviewError.hidden = false;
        confirmPayrollReviewButton.disabled = false;
        confirmPayrollReviewButton.textContent = 'Try again';
      }
    });

    function calculateRegularPay() {
      let rate = parseFloat(document.getElementById('daily_rate').value) || 0;
      let days = parseFloat(document.getElementById('days_worked').value) || 0;
      let regPay = rate * days;
      document.getElementById('regular_pay').value = regPay.toFixed(2);
    }

    function onEmployeeSelect() {
      let select = document.getElementById('calc_employee_id');
      let selectedOption = select.options[select.selectedIndex];
      if (selectedOption && selectedOption.dataset.rate) {
        document.getElementById('daily_rate').value = parseFloat(selectedOption.dataset.rate || 755).toFixed(2);
      } else {
        document.getElementById('daily_rate').value = "755.00";
      }
      fetchEmployeeAttendance();
    }

    function fetchEmployeeAttendance() {
        const requestId = ++attendanceSummaryRequest;
        excessiveLateDays = [];
        renderAttendanceValidationWarning();
        let empId = document.getElementById('calc_employee_id').value;
        let period = document.getElementById('calc_pay_period').value;
        let payDate = document.querySelector('input[name="pay_date"]').value;
        let dailyRate = document.getElementById('daily_rate').value;
        let summaryMessage = document.getElementById('attendance-calculation-message');
                      
        if (!empId) {
            document.getElementById('days_worked').value = 0;
            const paidLeaveDisplay = document.getElementById('paid_leaves_display');
            if (paidLeaveDisplay) paidLeaveDisplay.value = '0.00';
            document.getElementById('late_deduction').value = '0.00';
            summaryMessage.textContent = '';
            excessiveLateDays = [];
            renderAttendanceValidationWarning();
            calculateRegularPay();
            return;
        }

        const params = new URLSearchParams({
            action: 'get_attendance',
            employee_id: empId,
            pay_period: period,
            pay_date: payDate,
            daily_rate: dailyRate
        });
        fetch(`config/Payroll_API.php?${params.toString()}`)
            .then(res => {
                if (!res.ok) {
                    throw new Error(`Attendance lookup failed (${res.status}).`);
                }
                return res.json();
            })
            .then(data => {
            if (requestId !== attendanceSummaryRequest) {
                return;
            }
            if (data.status !== 'success') {
                throw new Error(data.message || 'Could not calculate attendance payroll.');
            }
            document.getElementById('days_worked').value = data.days_worked;
            const paidLeaveDisplay = document.getElementById('paid_leaves_display');
            if (paidLeaveDisplay) paidLeaveDisplay.value = Number(data.paid_leaves || 0).toFixed(2);
            document.getElementById('late_deduction').value = Number(data.late_deduction).toFixed(2);
            summaryMessage.textContent = `Paid SIL: ${Number(data.paid_leave_days || 0)} day(s); LWOP: ${Number(data.unpaid_leave_days || 0)} day(s). ${data.late_minutes} late minute(s) included in the deduction.`;
            excessiveLateDays = data.excessive_late_days || [];
            renderAttendanceValidationWarning();
            calculateRegularPay();
            })
            .catch(error => {
            if (requestId !== attendanceSummaryRequest) {
                return;
            }
            console.error(error);
            document.getElementById('days_worked').value = 0;
            const paidLeaveDisplay = document.getElementById('paid_leaves_display');
            if (paidLeaveDisplay) paidLeaveDisplay.value = '0.00';
            document.getElementById('late_deduction').value = '0.00';
            excessiveLateDays = [];
            renderAttendanceValidationWarning();
            calculateRegularPay();
            summaryMessage.textContent = 'Could not load attendance calculations. Please try again.';
            });
        }

        function renderAttendanceValidationWarning() {
            const warning = document.getElementById('attendance-validation-warning');
            if (!excessiveLateDays.length) {
                warning.textContent = '';
                warning.classList.add('hidden');
                return;
            }

            const flaggedDates = excessiveLateDays
                .map(day => `${day.date}: ${day.late_minutes} minutes`)
                .join('; ');
            warning.textContent = `Review attendance records before saving. More than 240 late minutes: ${flaggedDates}. Time In after 12:00 PM is counted as 0.5 day and excluded from the late deduction.`;
            warning.classList.remove('hidden');
        }

        const payDateInput = document.querySelector('input[name="pay_date"]');
        payDateInput.addEventListener('change', () => {
          const dayOfMonth = Number(payDateInput.value.split('-')[2]);
          if (Number.isInteger(dayOfMonth) && dayOfMonth >= 1 && dayOfMonth <= 31) {
            document.getElementById('calc_pay_period').value = dayOfMonth <= 15 ? 'Kinsenas' : 'Katapusan';
          }
          refreshEligibleEmployees();
        });
        document.getElementById('daily_rate').addEventListener('change', fetchEmployeeAttendance);
        refreshEligibleEmployees();

    function printPayslip(data) {
      let periodLabel = data.pay_period === 'Kinsenas' ? '1st Half' : '2nd Half';
      let name = data.full_name || data.username || 'Employee';
      let daysWorked = data.days_worked !== undefined ? data.days_worked : 0;
      
      let win = window.open('', '_blank', 'width=800,height=900');
      win.document.write(`
        <html>
        <head>
          <title>Payslip - ${name}</title>
          <style>
            body { font-family: sans-serif; padding: 25px; color: #111; }
            .header { text-align: center; border-bottom: 2px solid #16a34a; padding-bottom: 10px; margin-bottom: 15px; }
            .header h2 { color: #111; margin: 0; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            td { padding: 6px 0; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
            .bold { font-weight: bold; }
            .right { text-align: right; }
          </style>
        </head>
        <body>
          <div class="header">
            <h2>TaskTrack</h2>
            <p style="font-size:11px; margin:2px 0;">3201 Tycoon Center Bldg, Ortigas Center, Pasig</p>
            <h3>OFFICIAL PAYSLIP (${periodLabel})</h3>
          </div>
          <table>
            <tr><td><b>Employee:</b> ${name}</td><td class="right"><b>Pay Date:</b> ${data.pay_date}</td></tr>
            <tr><td><b>ID:</b> #${data.employee_id}</td><td class="right"><b>Status:</b> ${data.status}</td></tr>
            <tr><td><b>Days Worked:</b> ${daysWorked} day/s</td><td class="right"><b>Daily Rate:</b> ₱${parseFloat(data.daily_rate || 755).toFixed(2)}</td></tr>
          </table>
          <hr style="margin: 15px 0;">
          <table>
            <tr class="bold"><td>EARNINGS</td><td class="right">AMOUNT</td></tr>
            <tr><td>Regular Pay (${daysWorked} days)</td><td class="right">₱${parseFloat(data.regular_pay || 0).toFixed(2)}</td></tr>
            <tr><td>Paid Leaves</td><td class="right">₱${parseFloat(data.paid_leaves || 0).toFixed(2)}</td></tr>
            <tr class="bold"><td>GROSS PAY</td><td class="right">₱${parseFloat(data.gross_pay || 0).toFixed(2)}</td></tr>
          </table>
          <br>
          <table>
            <tr class="bold"><td>DEDUCTIONS</td><td class="right">AMOUNT</td></tr>
            <tr><td>Late Deduction</td><td class="right">-₱${parseFloat(data.late_deduction || 0).toFixed(2)}</td></tr>
            <tr><td>SSS / Calamity Loan</td><td class="right">-₱${parseFloat(data.sss || 0).toFixed(2)}</td></tr>
            <tr><td>HDMF / Pag-IBIG</td><td class="right">-₱${parseFloat(data.pagibig || 0).toFixed(2)}</td></tr>
            <tr><td>HMO</td><td class="right">-₱${parseFloat(data.hmo || 0).toFixed(2)}</td></tr>
            <tr class="bold"><td>TOTAL DEDUCTIONS</td><td class="right" style="color:red;">-₱${parseFloat(data.total_deductions || 0).toFixed(2)}</td></tr>
          </table>
          <br>
          <table>
            <tr class="bold" style="font-size: 16px;">
              <td>TOTAL AMOUNT CREDITED</td>
              <td class="right" style="color: #166534;">₱${parseFloat(data.total_credited || 0).toFixed(2)}</td>
            </tr>
          </table>
          <script>window.print();<\/script>
        </body>
        </html>
      `);
    }

    // Chart.js Visual Rendering (Green Theme)
    window.addEventListener('DOMContentLoaded', () => {
      const ctxPie = document.getElementById('chart-status-pie')?.getContext('2d');
      if (ctxPie) {
        new Chart(ctxPie, {
          type: 'doughnut',
          data: {
            labels: ['Nasahuran Na (Paid)', 'Pending'],
            datasets: [{
              data: [<?php echo $monthly_status_counts['Paid']; ?>, <?php echo $monthly_status_counts['Pending']; ?>],
              backgroundColor: ['#10B981', '#F59E0B']
            }]
          },
          options: { responsive: true, maintainAspectRatio: false }
        });
      }

      const ctxBar = document.getElementById('chart-payroll-bar')?.getContext('2d');
      if (ctxBar) { 
        new Chart(ctxBar, {
          type: 'bar',
          data: {
            labels: ['1st Half (1st–15th)', '2nd Half (16th–month end)', <?= json_encode($selectedPayrollMonth === 'all' ? 'Yearly Salary Expense' : 'Monthly Salary Expense'); ?>],
            datasets: [{
              label: 'Amount (₱)',
              data: [<?php echo json_encode($period_payroll_totals['Kinsenas']); ?>, <?php echo json_encode($period_payroll_totals['Katapusan']); ?>, <?php echo json_encode($monthly_salary_expense); ?>],
              backgroundColor: ['#10B981', '#0F766E', '#3B82F6']
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              y: {
                beginAtZero: true,
                ticks: {
                  callback: value => `₱${Number(value).toLocaleString()}`
                }
              }
            }
          }
        });
      }

      if (sessionStorage.getItem('payrollTabAfterReload') === 'employees') {
        sessionStorage.removeItem('payrollTabAfterReload');
        switchTab('employees');
      }
      <?php if ($initialPayrollTab !== 'dashboard'): ?>
      sessionStorage.removeItem('payrollTabAfterReload');
      switchTab(<?= json_encode($initialPayrollTab); ?>);
      <?php endif; ?>
    });
  </script>
  <script src="js/calendar-picker.js?v=<?= filemtime(__DIR__ . '/js/calendar-picker.js'); ?>"></script>
  <script src="js/select-control.js?v=<?= filemtime(__DIR__ . '/js/select-control.js'); ?>"></script>
</body>
</html>