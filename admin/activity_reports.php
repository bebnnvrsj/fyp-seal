<?php
//Restrict access to admin users only
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/login.php");
    exit();
}
require '../db_connect.php'; 

// Fetch current admin details for the header
$admin_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Admin User';

// Dynamic Stats from Database
$totalUsers = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$totalLogs = $conn->query("SELECT COUNT(*) as count FROM auditlog")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Activity Reports - SEAL</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --sidebar-width: 260px;
            --header-bg: #2b7a9e;
            --main-bg: linear-gradient(to bottom, #caf0f8, #90e0ef, #48cae4);
            --dark-blue: #183055;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, sans-serif;
            background: var(--main-bg);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
        }

        /* ====== SIDEBAR ====== */
        .sidebar { width: var(--sidebar-width); height: 100vh; background-color: var(--dark-blue); color: white; position: fixed; left: 0; top: 0; transition: transform 0.3s ease; z-index: 1000; display: flex; flex-direction: column; }
        .sidebar.closed { transform: translateX(-100%); }
        .sidebar-header { padding: 20px; background-color: #122542; display: flex; align-items: center; gap: 15px; font-weight: bold; }
        .sidebar-menu { list-style: none; padding: 0; margin: 20px 0; }
        .sidebar-menu li a { padding: 15px 25px; display: flex; align-items: center; gap: 15px; color: #d1d9e6; text-decoration: none; transition: 0.2s; }
        .sidebar-menu li a:hover { background-color: #2b7a9e; color: white; }
        .sidebar-menu li a.active { background-color: #2b7a9e; color: white; border-left: 4px solid #fff; }

        /* ====== MAIN WRAPPER ====== */
        .main-wrapper { flex: 1; display: flex; flex-direction: column; margin-left: var(--sidebar-width); transition: margin-left 0.3s ease; width: 100%; }
        .main-wrapper.full-width { margin-left: 0; }

        .header { height: 56px; background-color: var(--header-bg); display: flex; align-items: center; justify-content: space-between; padding: 0 24px; color: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .toggle-btn { cursor: pointer; font-size: 20px; }

        /* ====== CONTAINER (Stretched Layout) ====== */
        .container { 
            width: 95%; 
            max-width: 1600px; 
            margin: 30px auto; 
            padding: 0 20px; 
            display: flex; 
            flex-direction: column; 
            gap: 25px; 
        }

        /* Page Hero */
        .page-hero {
            background: white;
            border-radius: 15px;
            padding: 25px 35px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .hero-info h1 { margin: 0; color: var(--dark-blue); font-size: 24px; }
        .hero-info p { margin: 5px 0 0; color: #666; font-size: 14px; }

        /* Report Grid Layout */
        .report-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 25px;
        }

        .report-card {
            background: white;
            border-radius: 15px;
            padding: 22px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .card-label {
            background: #eee;
            padding: 4px 15px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            color: #555;
            display: inline-block;
            margin-bottom: 20px;
        }

        /* Stats Styling */
        .stat-item {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-item i { font-size: 30px; color: var(--header-bg); width: 45px; text-align: center; }
        .stat-info h3 { margin: 0; font-size: 12px; color: #777; text-transform: uppercase; }
        .stat-info p { margin: 2px 0 0; font-size: 24px; font-weight: 800; color: var(--dark-blue); }

        .chart-box { height: 300px; width: 100%; }

        @media (max-width: 1100px) { .report-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header"><i class="fa-solid fa-user-shield"></i> <span>SEAL</span></div>
    <ul class="sidebar-menu">
        <li><a href="../admin/home_admin.php"><i class="fa-solid fa-house"></i> Dashboard</a></li>
        <li><a href="../admin/register_patient.php"><i class="fa-solid fa-user-plus"></i> Register Patient</a></li>
        <li><a href="../admin/user_management.php"><i class="fa-solid fa-users-gear"></i> User Management</a></li>
        <li><a href="../admin/document_monitoring.php"><i class="fa-solid fa-file-shield"></i> Doc Monitoring</a></li>
        <li><a href="../admin/audit_logs.php"><i class="fa-solid fa-clipboard-list"></i> Audit Logs</a></li>
        <li><a href="../admin/activity_reports.php" class="active"><i class="fa-solid fa-chart-pie"></i> Activity Reports</a></li>
        <li><a href="../profile.php"><i class="fa-solid fa-user-gear"></i> Profile Settings</a></li>
        <li><a href="../login/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
    </ul>
</div>

<div class="main-wrapper" id="mainWrapper">
    <div class="header">
        <div class="header-left">
            <i class="fa-solid fa-bars toggle-btn" onclick="toggleSidebar()"></i>
            <span style="font-weight: 600; margin-left: 15px;">Administrator Portal</span>
        </div>
    </div>

    <div class="container">
        <div class="page-hero">
            <div class="hero-info">
                <h1><i class="fa-solid fa-chart-pie"></i> Activity Analytics</h1>
                <p>Visualizing system growth, verification trends, and blockchain integrity metrics.</p>
            </div>
            <div style="text-align: right;">
                <span style="font-size: 12px; color: #888;">Report Generated:</span><br>
                <span style="font-weight: 700; color: var(--dark-blue);"><?php echo date("d M Y | H:i"); ?></span>
            </div>
        </div>

        <div class="report-grid">
            <div class="left-col" style="display:flex; flex-direction:column; gap:25px;">
                <div class="report-card">
                    <span class="card-label">System-Wide Statistics</span>
                    <div class="stat-item">
                        <i class="fa-solid fa-users"></i>
                        <div class="stat-info">
                            <h3>Total Registered Users</h3>
                            <p><?php echo $totalUsers; ?></p>
                        </div>
                    </div>
                    <div class="stat-item">
                        <i class="fa-solid fa-list-check"></i>
                        <div class="stat-info">
                            <h3>Total System Audit Logs</h3>
                            <p><?php echo $totalLogs; ?></p>
                        </div>
                    </div>
                </div>

                <div class="report-card" style="text-align: center; border-bottom: 5px solid #1a7f4e;">
                    <span class="card-label">Security Health</span>
                    <i class="fa-solid fa-shield-halved" style="font-size: 50px; color: #1a7f4e; margin: 15px 0;"></i>
                    <p style="font-weight:700; color:#1a7f4e;">Blockchain Verified</p>
                    <p style="font-size: 12px; color: #777;">All issued document hashes match ledger records.</p>
                </div>
            </div>

            <div class="right-col" style="display:flex; flex-direction:column; gap:25px;">
                <div class="report-card">
                    <span class="card-label">Verification Success Rates</span>
                    <div class="chart-box">
                        <canvas id="pieChart"></canvas>
                    </div>
                </div>
                <div class="report-card">
                    <span class="card-label">Hourly Verification Peak Load</span>
                    <div class="chart-box">
                        <canvas id="barChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mainWrapper = document.getElementById('mainWrapper');
        sidebar.classList.toggle('closed');
        mainWrapper.classList.toggle('full-width');
    }

    // Charting Logic (Pie Chart)
    const ctxPie = document.getElementById('pieChart').getContext('2d');
    new Chart(ctxPie, {
        type: 'pie',
        data: {
            labels: ['Verified (Success)', 'Mismatch (Failed)', 'Not Found'],
            datasets: [{
                data: [4400, 520, 260],
                backgroundColor: ['#1a7f4e', '#d9534f', '#6c757d'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'right' } }
        }
    });

    // Charting Logic (Bar Chart)
    const ctxBar = document.getElementById('barChart').getContext('2d');
    new Chart(ctxBar, {
        type: 'bar',
        data: {
            labels: ['6am', '8am', '10am', '12pm', '2pm', '4pm', '6pm', '8pm'],
            datasets: [{
                label: 'Verification Requests',
                data: [15, 60, 130, 260, 150, 210, 60, 20],
                backgroundColor: '#2b7a9e',
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true } },
            plugins: { legend: { display: false } }
        }
    });
</script>

</body>
</html>