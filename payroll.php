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

// Fetch Employees List for Modal Dropdown
$employees_list = $conn->query("SELECT * FROM employee ORDER BY username ASC");

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
              <h3 class="font-bold text-zinc-900 text-base border-b border-zinc-100 pb-3">Payroll Financial Breakdown</h3>
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
            <div class="overflow-x-auto">
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
                      <button onclick="toggleStatus(<?php echo $row['payroll_id']; ?>, '<?php echo $row['status']; ?>')" class="px-2.5 py-1 rounded-full text-[10px] font-bold transition <?php echo $row['status'] === 'Paid' ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 'bg-amber-100 text-amber-700 hover:bg-amber-200'; ?>">
                        <?php echo $row['status'] === 'Paid' ? '✓ Paid' : '⏳ Pending'; ?>
                      </button>
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
                  <select name="employee_id" id="calc_employee_id" required onchange="onEmployeeSelect()" class="w-full px-3 py-2 border rounded-xl text-xs bg-white focus:ring-2 focus:ring-emerald-500 outline-none">
                    <option value="">-- Choose Employee --</option>
                    <?php
                    $employees_list->data_seek(0);
                    while($emp = $employees_list->fetch_assoc()):
                    ?>
                    <option value="<?php echo $emp['employee_id']; ?>" data-rate="<?php echo $emp['daily_rate'] ?? 750; ?>">
                        <?php echo htmlspecialchars($emp['username']); ?> (#<?php echo $emp['employee_id']; ?>)
                    </option>
                    <?php endwhile; ?>
                  </select>
                </div>

                <div>
                  <label class="block text-xs font-bold text-zinc-800 mb-1">Pay Period</label>
                  <select name="pay_period" id="calc_pay_period" onchange="fetchEmployeeAttendance()" class="w-full px-3 py-2 border rounded-xl text-xs bg-white focus:ring-2 focus:ring-emerald-500 outline-none">
                    <option value="Kinsenas">1st Half (1st - 15th)</option>
                    <option value="Katapusan">2nd Half (16th - 31st)</option>
                  </select>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-xs font-bold text-zinc-800 mb-1">Daily Rate (₱)</label>
                  <input type="number" step="0.01" id="daily_rate" name="daily_rate" value="750.00" oninput="calculateRegularPay()" class="w-full px-3 py-2 border rounded-xl text-xs bg-white outline-none font-mono">
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
                  <input type="number" step="0.01" name="late_deduction" placeholder="000.00" class="w-full px-2.5 py-1.5 border rounded-lg text-xs font-mono">
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

  <!-- SCRIPT HANDLERS -->
  <script>
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

    function toggleStatus(id, currentStatus) {
      let newStatus = currentStatus === 'Paid' ? 'Pending' : 'Paid';
      fetch('config/Payroll_API.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=toggle_status&payroll_id=${id}&status=${newStatus}`
      }).then(() => location.reload());
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
        document.getElementById('daily_rate').value = parseFloat(selectedOption.dataset.rate || 750).toFixed(2);
      } else {
        document.getElementById('daily_rate').value = "750.00";
      }
      fetchEmployeeAttendance();
    }

    function fetchEmployeeAttendance() {
        let empId = document.getElementById('calc_employee_id').value;
        let period = document.getElementById('calc_pay_period').value;
        let payDate = document.querySelector('input[name="pay_date"]').value;
                      
        if (!empId) {
            document.getElementById('days_worked').value = 0;
            calculateRegularPay();
            return;
        }

        fetch(`config/Payroll_API.php?action=get_attendance&employee_id=${empId}&pay_period=${period}&pay_date=${payDate}`)
            .then(res => res.json())
            .then(data => {
            if (data && data.days_worked !== undefined) {
                document.getElementById('days_worked').value = data.days_worked;
            } else {
                document.getElementById('days_worked').value = 0;
            }
            calculateRegularPay();
            })
            .catch(() => {
            document.getElementById('days_worked').value = 0;
            calculateRegularPay();
            });
        }

        document.querySelector('input[name="pay_date"]').addEventListener('change', fetchEmployeeAttendance);

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
            <tr><td><b>Days Worked:</b> ${daysWorked} day/s</td><td class="right"><b>Daily Rate:</b> ₱${parseFloat(data.daily_rate || 750).toFixed(2)}</td></tr>
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
              data: [<?php echo $paid_count; ?>, <?php echo$pending_count; ?>],
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
            labels: ['Disbursed', 'Pending'],
            datasets: [{
              label: 'Amount Credited (₱)',
              data: [<?php echo $total_disbursed; ?>, <?php echo$total_pending_pay; ?>],
              backgroundColor: ['#10B981', '#F59E0B']
            }]
          },
          options: { responsive: true, maintainAspectRatio: false }
        });
      }
    });
  </script>
</body>
</html>