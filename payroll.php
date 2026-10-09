<?php
include('config/connection.php');
session_start();

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'payroll') {
    header("Location: index.php");
    exit();
}   

// Fetch Metrics
$total_emp_res = $conn->query("SELECT COUNT(*) as cnt FROM employee");
$total_employees = $total_emp_res->fetch_assoc()['cnt'] ?? 0;

$paid_res = $conn->query("SELECT COUNT(*) as cnt, SUM(total_credited) as total FROM payroll WHERE status = 'Paid'");
$paid_data = $paid_res->fetch_assoc();
$paid_count = $paid_data['cnt'] ?? 0;
$total_disbursed = $paid_data['total'] ?? 0;

$pending_res = $conn->query("SELECT COUNT(*) as cnt, SUM(total_credited) as total FROM payroll WHERE status = 'Pending'");
$pending_data = $pending_res->fetch_assoc();
$pending_count = $pending_data['cnt'] ?? 0;
$total_pending_pay = $pending_data['total'] ?? 0;

$total_budget = $total_disbursed + $total_pending_pay;

$chartMonth = $_GET['chart_month'] ?? date('Y-m');
$chartMonthDate = DateTimeImmutable::createFromFormat('!Y-m', $chartMonth);
$chartMonthErrors = DateTimeImmutable::getLastErrors();
if (
    !$chartMonthDate
    || $chartMonthDate->format('Y-m') !== $chartMonth
    || ($chartMonthErrors !== false && ($chartMonthErrors['warning_count'] > 0 || $chartMonthErrors['error_count'] > 0))
) {
    http_response_code(400);
    exit('Invalid chart month.');
}
$chartMonthStart = $chartMonthDate->format('Y-m-01');
$chartMonthEnd = $chartMonthDate->modify('last day of this month')->format('Y-m-d');

$monthly_status_counts = [
    'Paid' => 0,
    'Pending' => 0
];
$monthly_status_stmt = $conn->prepare("
    SELECT status, COUNT(*) AS total
    FROM payroll
    WHERE pay_date BETWEEN ? AND ?
    GROUP BY status
");
if (!$monthly_status_stmt) {
    throw new RuntimeException('Failed to prepare monthly payroll status counts: ' . $conn->error);
}
$monthly_status_stmt->bind_param('ss', $chartMonthStart, $chartMonthEnd);
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
    WHERE pay_date BETWEEN ? AND ?
    GROUP BY pay_period
");
if (!$period_payroll_stmt) {
    throw new RuntimeException('Failed to prepare monthly payroll totals: ' . $conn->error);
}
$period_payroll_stmt->bind_param('ss', $chartMonthStart, $chartMonthEnd);
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
$payrolls = $conn->query("
    SELECT p.*, e.employee_id, e.tin_no, e.sss_no, e.hdmf_no, e.position, e.department, e.username, e.username 
    FROM payroll p 
    JOIN employee e ON p.employee_id = e.employee_id 
    ORDER BY p.payroll_id DESC
");

$payroll_records = [];
while ($r = $payrolls->fetch_assoc()) {
    $payroll_records[] = $r;
}
?>
<!DOCTYPE html>
<html lang="tl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>RERA CORP - Payroll Dashboard</title>
  
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- FontAwesome 6 Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;0,6..72,600;1,6..72,400&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/table-scroll.css?v=<?= filemtime(__DIR__ . '/css/table-scroll.css'); ?>">

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

  <!-- App Wrapper -->
  <div class="flex h-screen overflow-hidden">

    <!-- SUPERVISOR STYLE SIDEBAR -->
    <aside class="w-64 bg-white border-r border-zinc-200 flex flex-col justify-between h-screen sticky top-0 z-30 shrink-0 select-none">
      
      <div class="flex flex-col w-full">
        <!-- Profile Header (Top Box + User Info) -->
        <div class="p-5 border-b border-zinc-200 flex items-center gap-3">
          <!-- Gray Square Box -->
          <div class="w-10 h-10 bg-zinc-300 rounded-xs shrink-0"></div>
          
          <!-- User Info -->
          <div class="overflow-hidden leading-tight">
            <h4 class="font-bold text-sm text-zinc-900 truncate">
              <?php echo htmlspecialchars(explode('@', $_SESSION['email'])[0]); ?>
            </h4>
            <p class="text-[11px] text-zinc-400 capitalize truncate">
              Payroll Manager
            </p>
          </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="flex flex-col pt-4 w-full">
          
          <!-- Dashboard -->
          <button onclick="switchTab('dashboard')" id="nav-dashboard" class="nav-item active w-full px-5 py-3.5 flex items-center gap-4 text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50 transition text-xs font-semibold relative">
            <i class="fa-solid fa-house text-sm w-5 text-center"></i>
            <span>Dashboard</span>
          </button>

          <!-- Employee Masterlist -->
          <button onclick="switchTab('employees')" id="nav-employees" class="nav-item w-full px-5 py-3.5 flex items-center gap-4 text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50 transition text-xs font-semibold relative">
            <i class="fa-solid fa-user text-sm w-5 text-center"></i>
            <span>Employee Masterlist</span>
          </button>

          <!-- Calculate Payroll -->
          <button onclick="switchTab('calculator')" id="nav-calc" class="nav-item w-full px-5 py-3.5 flex items-center gap-4 text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50 transition text-xs font-semibold relative">
            <i class="fa-solid fa-calculator text-sm w-5 text-center"></i>
            <span>Calculate Payroll</span>
          </button>

          <a href="office_employee.php" class="nav-item w-full px-5 py-3.5 flex items-center gap-4 text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50 transition text-xs font-semibold relative">
            <i class="fa-solid fa-list-check text-sm w-5 text-center"></i>
            <span>My Tasks</span>
          </a>

        </nav>
      </div>

      <!-- Logout Button at Bottom -->
      <div class="p-4 border-t border-zinc-100 w-full">
        <a href="logout.php" class="w-full px-3 py-2.5 flex items-center gap-4 text-zinc-600 hover:text-red-600 transition text-xs font-semibold">
          <i class="fa-solid fa-right-from-bracket text-sm w-5 text-center"></i>
          <span>Log out</span>
        </a>
      </div>

    </aside>

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

          <!-- 4 METRIC CARDS -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-zinc-200 shadow-2xs space-y-2">
              <div class="flex justify-between items-center text-zinc-500 text-xs font-medium">
                <span>Total Employees</span>
                <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center"><i class="fa-solid fa-users"></i></div>
              </div>
              <div class="text-2xl sm:text-3xl font-extrabold text-zinc-900"><?php echo $total_employees; ?></div>
              <p class="text-[11px] text-zinc-400">Active Employees</p>
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
              <p class="text-[11px] text-zinc-400">Full Allocated Budget</p>
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
              <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-zinc-100 pb-3">
                <div>
                  <h3 class="font-bold text-zinc-900 text-base">Monthly Salary Expense</h3>
                  <p class="text-[11px] text-zinc-500">Gross payroll before deductions</p>
                </div>
                <form method="GET" action="payroll.php" class="flex items-center gap-2">
                  <label for="chart_month" class="text-xs font-semibold text-zinc-600">Month</label>
                  <input
                    type="month"
                    id="chart_month"
                    name="chart_month"
                    value="<?php echo htmlspecialchars($chartMonth, ENT_QUOTES, 'UTF-8'); ?>"
                    onchange="this.form.submit()"
                    class="rounded-lg border border-zinc-200 px-2 py-1.5 text-xs"
                  >
                </form>
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
              <p class="text-xs text-zinc-500">Review and update each employee's payment status.</p>
            </div>
            <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Search employee..." class="px-3.5 py-2 border border-zinc-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500 outline-none w-full sm:w-64">
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
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">✓ Paid</span>
                        <p class="mt-1 text-[10px] text-zinc-500">
                          <?php echo !empty($row['paid_at']) ? 'Paid on ' . htmlspecialchars(date('M j, Y', strtotime($row['paid_at']))) : 'Payment date not recorded'; ?>
                        </p>
                      <?php else: ?>
                        <span class="block mb-1 text-[10px] font-bold text-amber-700">⏳ Pending</span>
                        <button onclick="markPayrollPaid(<?php echo (int) $row['payroll_id']; ?>)" class="px-2.5 py-1 rounded-full text-[10px] font-bold transition bg-emerald-100 text-emerald-700 hover:bg-emerald-200">
                          Mark as Paid
                        </button>
                      <?php endif; ?>
                    </td>
                    <td class="p-4 text-center">
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
                    <option value="Kinsenas">1st Half (1st - 15th)</option>
                    <option value="Katapusan">2nd Half (16th - 31st)</option>
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
                  <input type="date" name="pay_date" required value="<?php echo date('Y-m-d'); ?>" class="w-full px-3 py-2 border rounded-xl text-xs bg-white outline-none">
                </div>
              </div>

              <hr class="my-2 border-emerald-200">

              <div class="grid grid-cols-3 gap-3">
                <div>
                  <label class="block text-[11px] text-zinc-600 font-semibold">Regular Pay (₱)</label>
                  <input type="number" step="0.01" id="regular_pay" name="regular_pay" value="000.00" class="w-full px-2.5 py-1.5 border rounded-lg text-xs font-mono bg-emerald-100 font-bold" readonly>
                </div>
                <div>
                  <label class="block text-[11px] text-zinc-600">Paid Leaves (₱)</label>
                  <input type="number" step="0.01" name="paid_leaves" placeholder="000.00" class="w-full px-2.5 py-1.5 border rounded-lg text-xs font-mono">
                </div>
                <div>
                  <label class="block text-[11px] text-zinc-600">Daily Allowance (₱)</label>
                  <input type="number" step="0.01" name="daily_allowance" value="1300.00" class="w-full px-2.5 py-1.5 border rounded-lg text-xs font-mono">
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

  <!-- SCRIPT HANDLERS -->
  <script>
    const payrollForm = document.getElementById('payrollForm');
    const payrollLoadingOverlay = document.getElementById('payrollLoadingOverlay');
    const payrollLoadingStatus = document.getElementById('payrollLoadingStatus');

    payrollForm.addEventListener('submit', function (event) {
      event.preventDefault();
      if (payrollForm.dataset.submitting === 'true') {
        return;
      }

      payrollForm.dataset.submitting = 'true';
      const submitter = event.submitter;
      payrollLoadingOverlay.className = 'payroll-loading-overlay active';
      payrollLoadingOverlay.setAttribute('aria-hidden', 'false');
      payrollLoadingStatus.textContent = 'Generating payroll computation...';

      window.setTimeout(function () {
        payrollLoadingOverlay.classList.add('step-print');
        payrollLoadingStatus.textContent = 'Preparing payroll computation...';
      }, 500);

      window.setTimeout(function () {
        payrollLoadingOverlay.classList.add('step-success');
        payrollLoadingStatus.textContent = 'Computation ready. Saving payroll...';
      }, 2000);

      window.setTimeout(function () {
        sessionStorage.setItem('payrollTabAfterReload', 'employees');
        HTMLFormElement.prototype.submit.call(payrollForm);
      }, 3500);
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

    async function markPayrollPaid(id) {
      if (!window.confirm('Confirm that this payroll has actually been paid to the employee?')) {
        return;
      }

      try {
        const response = await fetch('config/Payroll_API.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `action=mark_paid&payroll_id=${id}`
        });
        const data = await response.json();
        if (!response.ok || data.status !== 'success') {
          throw new Error(data.message || 'Could not mark payroll as paid.');
        }
        sessionStorage.setItem('payrollTabAfterReload', 'employees');
        location.reload();
      } catch (error) {
        console.error(error);
        window.alert(error.message || 'Could not mark payroll as paid.');
      }
    }

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
            document.getElementById('late_deduction').value = Number(data.late_deduction).toFixed(2);
            summaryMessage.textContent = `${data.late_minutes} late minute(s) included in the deduction.`;
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

        document.querySelector('input[name="pay_date"]').addEventListener('change', refreshEligibleEmployees);
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
            <tr><td>Daily Allowance</td><td class="right">₱${parseFloat(data.daily_allowance || 0).toFixed(2)}</td></tr>
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
            labels: ['1st Half (Kinsenas)', '2nd Half (Katapusan)', 'Monthly Salary Expense'],
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
                max: 1000000,
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
    });
  </script>
  <?php include __DIR__ . '/config/chatbot_widget.php'; ?>
</body>
</html>