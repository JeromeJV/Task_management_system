<?php
include('config/connection.php');
session_start();

if (!isset($_SESSION['email']) ||$_SESSION['role'] !== 'payroll') {
    header("Location: index.php");
    exit();
}  

// Fetch Metrics
$total_emp_res =$conn->query("SELECT COUNT(*) as cnt FROM employee");
$total_employees =$total_emp_res->fetch_assoc()['cnt'] ?? 0;

$paid_res =$conn->query("SELECT COUNT(*) as cnt, SUM(total_credited) as total FROM payroll WHERE status = 'Paid'");
$paid_data =$paid_res->fetch_assoc();
$paid_count =$paid_data['cnt'] ?? 0;
$total_disbursed =$paid_data['total'] ?? 0;

$pending_res =$conn->query("SELECT COUNT(*) as cnt, SUM(total_credited) as total FROM payroll WHERE status = 'Pending'");
$pending_data =$pending_res->fetch_assoc();
$pending_count =$pending_data['cnt'] ?? 0;
$total_pending_pay =$pending_data['total'] ?? 0;

$total_budget = $total_disbursed +$total_pending_pay;

// Fetch Employees List for Modal Dropdown
$employees_list =$conn->query("SELECT * FROM employee ORDER BY username ASC");

// Fetch Payroll Records
$payrolls =$conn->query("
    SELECT p.*, e.employee_id, e.tin_no, e.sss_no, e.hdmf_no, e.position, e.department, e.username 
    FROM payroll p 
    JOIN employee e ON p.employee_id = e.employee_id 
    ORDER BY p.payroll_id DESC
");

$payroll_records = [];
while ($r =$payrolls->fetch_assoc()) {
    $payroll_records[] =$r;
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
              600: '#166534',
              700: '#14532d',
            },
            paie: {
              bg: '#FAF8F5',
              card: '#FFFFFF',
              yellow: '#FBBF24',
              amber: '#F59E0B',
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
    body { background-color: #FAF8F5; color: #1E1E1E; }
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: #FAF8F5; }
    ::-webkit-scrollbar-thumb { background: #D4D4D8; border-radius: 9999px; }
    ::-webkit-scrollbar-thumb:hover { background: #A1A1AA; }
    .calc-input:focus { outline: none; border-color: #F59E0B; box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.15); }
  </style>
</head>
<body class="font-sans antialiased text-slate-800">

  <!-- App Wrapper -->
  <div class="flex h-screen overflow-hidden">

    <!-- SIDEBAR -->
    <aside class="w-16 md:w-20 bg-white border-r border-zinc-200 flex flex-col justify-between items-center py-5 z-30 shrink-0 shadow-xs">
      <div class="flex flex-col items-center gap-6 w-full">
        <a href="#" class="w-10 h-10 bg-amber-400 hover:bg-amber-500 rounded-xl flex items-center justify-center font-black text-slate-900 text-xl shadow-xs transition-transform active:scale-95" title="RERA CORP Payroll System">
          R
        </a>

        <nav class="flex flex-col gap-3 w-full px-3">
          <button onclick="switchTab('dashboard')" id="nav-dashboard" class="w-full h-11 rounded-xl flex items-center justify-center bg-amber-400 text-slate-900 shadow-xs font-semibold transition" title="Dashboard">
            <i class="fa-solid fa-chart-pie text-lg"></i>
          </button>

          <button onclick="switchTab('employees')" id="nav-employees" class="w-full h-11 rounded-xl flex items-center justify-center text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 transition" title="Listahan ng Empleyado">
            <i class="fa-solid fa-users-viewfinder text-lg"></i>
          </button>

          <button onclick="switchTab('calculator')" id="nav-calc" class="w-full h-11 rounded-xl flex items-center justify-center text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 transition" title="Kalkulador ng Sahod">
            <i class="fa-solid fa-calculator text-lg"></i>
          </button>
        </nav>
      </div>

      <div class="flex flex-col items-center gap-3 w-full px-3">
        <div class="w-10 h-10 rounded-full bg-slate-900 text-amber-400 font-extrabold text-xs flex items-center justify-center border-2 border-white shadow-xs" title="<?php echo htmlspecialchars($_SESSION['email']); ?>">
          <?php echo strtoupper(substr($_SESSION['email'], 0, 2)); ?>
        </div>
        <a href="logout.php" class="text-zinc-400 hover:text-red-500 transition text-sm p-2" title="Logout">
          <i class="fa-solid fa-right-from-bracket"></i>
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
          <div class="hidden lg:flex items-center gap-2 bg-amber-50 text-amber-900 px-3 py-1.5 rounded-lg text-xs font-mono border border-amber-200">
            <i class="fa-solid fa-building text-amber-600"></i>
            <span class="font-bold">Pasig HQ</span>
            <span class="text-amber-300">|</span>
            <span class="font-semibold text-amber-800">Live Period</span>
          </div>

          <button onclick="switchTab('calculator')" class="bg-amber-400 hover:bg-amber-500 text-slate-950 text-xs font-extrabold px-4 py-2 rounded-xl transition flex items-center gap-1.5 shadow-xs">
            <i class="fa-solid fa-plus text-sm"></i>
            <span>Compute New Payroll</span>
          </button>
        </div>
      </header>

      <!-- MAIN CONTENT CONTAINER -->
      <main class="p-4 sm:p-6 max-w-7xl w-full mx-auto space-y-6">

        <!-- NAV TABS -->
        <div class="border-b border-zinc-200 flex items-center gap-6 text-sm overflow-x-auto">
          <button onclick="switchTab('dashboard')" id="tab-dashboard" class="pb-3 border-b-2 border-amber-500 font-bold text-zinc-900 flex items-center gap-2 shrink-0">
            <i class="fa-solid fa-chart-pie text-amber-500"></i> Dashboard & Analytics
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
            <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Search employee..." class="px-3.5 py-2 border border-zinc-200 rounded-xl text-xs focus:ring-2 focus:ring-amber-400 outline-none w-full sm:w-64">
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
                  <?php foreach($payroll_records as$row): ?>
                  <tr class="hover:bg-zinc-50 transition">
                    <td class="p-4 font-medium text-slate-800">
                      <p class="font-bold"><?php echo htmlspecialchars($row['employee_name'] ?? $row['username']); ?></p>
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
                      <button onclick="toggleStatus(<?php echo $row['payroll_id']; ?>, '<?php echo$row['status']; ?>')" class="px-2.5 py-1 rounded-full text-[10px] font-bold transition <?php echo $row['status'] === 'Paid' ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 'bg-amber-100 text-amber-700 hover:bg-amber-200'; ?>">
                        <?php echo $row['status'] === 'Paid' ? '✓ Paid' : '⏳ Pending'; ?>
                      </button>
                    </td>
                    <td class="p-4 text-center">
                      <button onclick='printPayslip(<?php echo json_encode($row); ?>)' class="px-3 py-1.5 bg-zinc-100 hover:bg-amber-400 hover:text-slate-950 text-zinc-700 rounded-lg font-bold transition text-[11px] inline-flex items-center gap-1.5">
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
          <div class="bg-amber-50/70 border border-amber-200 rounded-2xl p-6 shadow-2xs space-y-4">
            <h3 class="text-xl font-bold text-zinc-900">Compute & Record New Payroll</h3>
            
            <form action="config/Payroll_API.php" method="POST" class="space-y-4">
              <input type="hidden" name="action" value="create_payroll">

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-bold text-zinc-800 mb-1">Select Employee</label>
                  <select name="employee_id" required class="w-full px-3 py-2 border rounded-xl text-xs bg-white focus:ring-2 focus:ring-amber-400 outline-none">
                    <?php 
                    $employees_list->data_seek(0);
                    while($emp =$employees_list->fetch_assoc()): 
                    ?>
                      <option value="<?php echo $emp['employee_id']; ?>"><?php echo htmlspecialchars($emp['username']); ?> (#<?php echo $emp['employee_id']; ?>)</option>
                    <?php endwhile; ?>
                  </select>
                </div>

                <div>
                  <label class="block text-xs font-bold text-zinc-800 mb-1">Pay Period</label>
                  <select name="pay_period" class="w-full px-3 py-2 border rounded-xl text-xs bg-white focus:ring-2 focus:ring-amber-400 outline-none">
                    <option value="Kinsenas">1st Half (1st - 15th)</option>
                    <option value="Katapusan">2nd Half (16th - 31st)</option>
                  </select>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-bold text-zinc-800 mb-1">Pay Date</label>
                  <input type="date" name="pay_date" required value="<?php echo date('Y-m-d'); ?>" class="w-full px-3 py-2 border rounded-xl text-xs bg-white outline-none">
                </div>
                <div>
                  <label class="block text-xs font-bold text-zinc-800 mb-1">Payment Status</label>
                  <select name="status" class="w-full px-3 py-2 border rounded-xl text-xs bg-white outline-none">
                    <option value="Pending">Pending</option>
                    <option value="Paid">Paid</option>
                  </select>
                </div>
              </div>

              <hr class="my-2 border-amber-200">

              <div class="grid grid-cols-3 gap-3">
                <div>
                  <label class="block text-[11px] text-zinc-600">Regular Pay (₱)</label>
                  <input type="number" step="0.01" name="regular_pay" value="10270.00" class="w-full px-2.5 py-1.5 border rounded-lg text-xs font-mono">
                </div>
                <div>
                  <label class="block text-[11px] text-zinc-600">Paid Leaves (₱)</label>
                  <input type="number" step="0.01" name="paid_leaves" value="790.00" class="w-full px-2.5 py-1.5 border rounded-lg text-xs font-mono">
                </div>
                <div>
                  <label class="block text-[11px] text-zinc-600">Daily Allowance (₱)</label>
                  <input type="number" step="0.01" name="daily_allowance" value="1300.00" class="w-full px-2.5 py-1.5 border rounded-lg text-xs font-mono">
                </div>
              </div>

              <div class="grid grid-cols-3 gap-3">
                <div>
                  <label class="block text-[11px] text-zinc-600">Late Deduction (₱)</label>
                  <input type="number" step="0.01" name="late_deduction" value="238.65" class="w-full px-2.5 py-1.5 border rounded-lg text-xs font-mono">
                </div>
                <div>
                  <label class="block text-[11px] text-zinc-600">SSS Contribution (₱)</label>
                  <input type="number" step="0.01" name="sss" value="500.00" class="w-full px-2.5 py-1.5 border rounded-lg text-xs font-mono">
                </div>
                <div>
                  <label class="block text-[11px] text-zinc-600">Pag-IBIG / HDMF (₱)</label>
                  <input type="number" step="0.01" name="pagibig" value="100.00" class="w-full px-2.5 py-1.5 border rounded-lg text-xs font-mono">
                </div>
              </div>

              <div class="grid grid-cols-3 gap-3">
                <div>
                  <label class="block text-[11px] text-zinc-600">Calamity Loan (₱)</label>
                  <input type="number" step="0.01" name="calamity_loan" value="768.77" class="w-full px-2.5 py-1.5 border rounded-lg text-xs font-mono">
                </div>
                <div>
                  <label class="block text-[11px] text-zinc-600">HMO (₱)</label>
                  <input type="number" step="0.01" name="hmo" value="150.00" class="w-full px-2.5 py-1.5 border rounded-lg text-xs font-mono">
                </div>
                <div>
                  <label class="block text-[11px] text-zinc-600">Reimbursement (₱)</label>
                  <input type="number" step="0.01" name="reimbursement" value="2193.00" class="w-full px-2.5 py-1.5 border rounded-lg text-xs font-mono">
                </div>
              </div>

              <div class="pt-4 flex justify-end gap-3">
                <button type="submit" class="px-5 py-2.5 bg-amber-400 hover:bg-amber-500 text-slate-950 font-bold rounded-xl text-xs transition shadow-xs">
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

      document.getElementById('tab-dashboard').className = "pb-3 border-b-2 border-transparent text-zinc-500 hover:text-zinc-800 font-medium flex items-center gap-2 shrink-0 transition";
      document.getElementById('tab-employees').className = "pb-3 border-b-2 border-transparent text-zinc-500 hover:text-zinc-800 font-medium flex items-center gap-2 shrink-0 transition";
      document.getElementById('tab-calculator').className = "pb-3 border-b-2 border-transparent text-zinc-500 hover:text-zinc-800 font-medium flex items-center gap-2 shrink-0 transition";

      document.getElementById(`view-${tabName}`).classList.remove('hidden');
      document.getElementById(`tab-${tabName}`).className = "pb-3 border-b-2 border-amber-500 font-bold text-zinc-900 flex items-center gap-2 shrink-0";
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

    function printPayslip(data) {
      let periodLabel = data.pay_period === 'Kinsenas' ? '1st Half' : '2nd Half';
      let name = data.employee_name || data.username || 'Employee';
      let win = window.open('', '_blank', 'width=800,height=900');
      win.document.write(`
        <html>
        <head>
          <title>Payslip - ${name}</title>
          <style>
            body { font-family: sans-serif; padding: 25px; color: #111; }
            .header { text-align: center; border-bottom: 2px solid #F59E0B; padding-bottom: 10px; margin-bottom: 15px; }
            .header h2 { color: #111; margin: 0; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            td { padding: 6px 0; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
            .bold { font-weight: bold; }
            .right { text-align: right; }
          </style>
        </head>
        <body>
          <div class="header">
            <h2>RERA CORP</h2>
            <p style="font-size:11px; margin:2px 0;">3201 Tycoon Center Bldg, Ortigas Center, Pasig</p>
            <h3>OFFICIAL PAYSLIP (${periodLabel})</h3>
          </div>
          <table>
            <tr><td><b>Employee:</b> ${name}</td><td class="right"><b>Pay Date:</b> ${data.pay_date}</td></tr>
            <tr><td><b>ID:</b> #${data.employee_id}</td><td class="right"><b>Status:</b> ${data.status}</td></tr>
          </table>
          <hr style="margin: 15px 0;">
          <table>
            <tr class="bold"><td>EARNINGS</td><td class="right">AMOUNT</td></tr>
            <tr><td>Regular Pay</td><td class="right">₱${parseFloat(data.regular_pay || 0).toFixed(2)}</td></tr>
            <tr><td>Paid Leaves</td><td class="right">₱${parseFloat(data.paid_leaves || 0).toFixed(2)}</td></tr>
            <tr><td>Daily Allowance</td><td class="right">₱${parseFloat(data.daily_allowance || 0).toFixed(2)}</td></tr>
            <tr class="bold"><td>GROSS PAY</td><td class="right">₱${parseFloat(data.gross_pay || 0).toFixed(2)}</td></tr>
          </table>
          <br>
          <table>
            <tr class="bold"><td>DEDUCTIONS</td><td class="right">AMOUNT</td></tr>
            <tr><td>Late Deduction</td><td class="right">-₱${parseFloat(data.late_deduction || 0).toFixed(2)}</td></tr>
            <tr><td>SSS Contribution</td><td class="right">-₱${parseFloat(data.sss || 0).toFixed(2)}</td></tr>
            <tr><td>HDMF / Pag-IBIG</td><td class="right">-₱${parseFloat(data.pagibig || 0).toFixed(2)}</td></tr>
            <tr><td>Calamity Loan</td><td class="right">-₱${parseFloat(data.calamity_loan || 0).toFixed(2)}</td></tr>
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

    // Chart.js Visual Rendering
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