<?php
session_start();
// Verify administration security session parameters
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
require '../db_connect.php'; 

// Fetch audit records across all active role sub-profiles dynamically
$sql = "SELECT 
            a.logID, 
            a.action, 
            a.resource, 
            a.timestamp,
            IFNULL(COALESCE(ap.name, dp.name, vp.name), 'External Verifier') AS admin_name
        FROM auditlog a 
        LEFT JOIN users u ON a.userID = u.userID 
        LEFT JOIN admin_profiles ap ON u.userID = ap.adminID
        LEFT JOIN doctor_profiles dp ON u.userID = dp.doctorID
        LEFT JOIN verifier_profiles vp ON u.userID = vp.verifierID
        ORDER BY a.timestamp DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Audit Logs - SEAL</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --sidebar-width: 260px;
            --header-bg: #2b7a9e;
            --main-bg: linear-gradient(to bottom, #caf0f8, #90e0ef, #48cae4);
            --dark-blue: #183055;
            --success-green: #28a745;
            --danger-red: #dc3545;
            --warning-orange: #fd7e14;
            --info-blue: #0d47a1;
            --purple-reg: #6f42c1;
        }

        body {
            margin: 0; font-family: "Segoe UI", Tahoma, sans-serif;
            background: var(--main-bg); min-height: 100vh; display: flex; overflow-x: hidden;
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

        /* ====== CONTAINER ====== */
        .container { width: 95%; max-width: 100%; margin: 30px auto; padding: 0 40px; box-sizing: border-box; display: flex; flex-direction: column; gap: 25px; }

        .page-hero {
            background: white; border-radius: 15px; padding: 25px 35px;
            display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .hero-info h1 { margin: 0; color: var(--dark-blue); font-size: 24px; display: flex; align-items: center; gap: 12px; }
        .hero-info p { margin: 5px 0 0; color: #666; font-size: 14px; }

        .controls-bar { display: flex; justify-content: center; align-items: center; width: 100%; margin-top: 5px; }

        /* BUBBLE GLASS TAB CONTAINER */
        .tabs-container { 
            display: inline-flex; justify-content: center; align-items: center; gap: 12px; 
            background: rgba(255, 255, 255, 0.75); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); 
            padding: 8px 16px; border-radius: 40px; box-shadow: 0 8px 25px rgba(0,0,0,0.06); border: 1px solid rgba(255, 255, 255, 0.5);
        }
        .tab-btn { 
            padding: 12px 26px; border: none; border-radius: 30px; cursor: pointer; 
            background: transparent; color: var(--dark-blue); font-weight: 700; font-size: 14px; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: flex; align-items: center; gap: 8px;
        }
        .tab-btn:hover { background: rgba(43, 122, 158, 0.08); color: var(--header-bg); }
        .tab-btn.active { 
            background: #2b7a9e; color: white !important; 
            box-shadow: 0 4px 15px rgba(43, 122, 158, 0.35); 
        }

        /* ====== TABLE CARD SYSTEM ====== */
        .management-card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0px 4px 15px rgba(0,0,0,0.1); width: 100%; overflow-x: auto; box-sizing: border-box; }
        .section-title { font-size: 18px; color: var(--dark-blue); margin-top: 0; margin-bottom: 15px; font-weight: 600; display: flex; align-items: center; gap: 10px; }

        table { width: 100%; border-collapse: collapse; table-layout: auto; }
        th { background-color: #f8f9fa; padding: 15px; text-align: left; border-bottom: 2px solid #dee2e6; color: #555; font-weight: 600; font-size: 14px; }
        td { padding: 15px; border-bottom: 1px solid #eee; font-size: 14px; color: #333; vertical-align: middle; }
        tr:hover { background-color: #f1f8ff; }

        /* ====== DYNAMIC SECURITY OPERATION BADGES ====== */
        .action-badge { padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: bold; text-transform: uppercase; display: inline-block; }
        .badge-create { background: #e3f2fd; color: var(--info-blue); }
        .badge-issue { background: #e8f5e9; color: var(--success-green); }
        .badge-revoke { background: #fff3e0; color: var(--warning-orange); }
        .badge-verify { background: #f3e5f5; color: #6a1b9a; }
        .badge-tampered { background: #ffebee; color: var(--danger-red); border: 1px solid rgba(220, 53, 69, 0.2); }
        .badge-registration { background: #f3f0ff; color: var(--purple-reg); }
        .badge-default { background: #f0f0f0; color: #495057; }

        .timestamp-cell { color: #4a5568; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; }
        .timestamp-cell i { color: var(--header-bg); }

        @media (max-width: 1050px) {
            .tabs-container { flex-wrap: wrap; justify-content: center; border-radius: 20px; padding: 15px; gap: 8px; }
            .tab-btn { padding: 10px 18px; font-size: 13px; }
        }
        @media (max-width: 850px) {
            .tabs-container { flex-direction: column; width: 100%; }
            .tab-btn { width: 100%; justify-content: center; }
            td, th { padding: 10px; font-size: 13px; }
        }
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
        <li><a href="../admin/audit_logs.php" class="active"><i class="fa-solid fa-clipboard-list"></i> Audit Logs</a></li>
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
                <h1><i class="fa-solid fa-clipboard-list"></i> System Audit Logs</h1>
                <p>Track internal operations, immutable identity registrations, and system event structures.</p>
            </div>
        </div>

        <div class="controls-bar">
            <div class="tabs-container">
                <button class="tab-btn active" onclick="filterAuditLogs('', this)"><i class="fa-solid fa-layer-group"></i> All Actions</button>
                <button class="tab-btn" onclick="filterAuditLogs('CREATE_MC', this)"><i class="fa-solid fa-file-prescription"></i> Issued MC</button>
                <button class="tab-btn" onclick="filterAuditLogs('CREATE TIMESLIP', this)"><i class="fa-solid fa-user-clock"></i> Issued Time-Slip</button>
                <button class="tab-btn" onclick="filterAuditLogs('VERIFY', this)"><i class="fa-solid fa-file-circle-check"></i> Verification Attempts</button>
                <button class="tab-btn" onclick="filterAuditLogs('USER_REGISTRATION', this)"><i class="fa-solid fa-user-shield"></i> User Registrations</button>
            </div>
        </div>

        <div class="management-card">
            <div class="section-title"><i class="fa-solid fa-clock-rotate-left"></i> Immutable Operations History Trail</div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 10%;">Log ID</th>
                        <th style="width: 20%;">Triggered By</th>
                        <th style="width: 20%;">Action Imposed</th>
                        <th style="width: 32%;">Target Resource Signature</th>
                        <th style="width: 18%;">Execution Timestamp</th>
                    </tr>
                </thead>
                <tbody id="logTableBody">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($log = $result->fetch_assoc()): 
                            $act = strtoupper(trim($log['action']));
                            $badgeType = "badge-default";
                            
                            if (strpos($act, 'CREATE') !== false) $badgeType = "badge-create";
                            elseif (strpos($act, 'ISSUE') !== false) $badgeType = "badge-issue";
                            elseif (strpos($act, 'REVOKE') !== false) $badgeType = "badge-revoke";
                            elseif (strpos($act, 'VERIFY_AUTHENTIC') !== false) $badgeType = "badge-verify";
                            elseif (strpos($act, 'VERIFY_TAMPERED') !== false) $badgeType = "badge-tampered";
                            elseif ($act === 'USER_REGISTRATION') $badgeType = "badge-registration";
                        ?>
                        <tr>
                            <td><strong>#<?php echo $log['logID']; ?></strong></td>
                            <td>
                                <span style="font-weight: 600; color: <?php echo ($log['admin_name'] === 'External Verifier') ? 'var(--warning-orange)' : 'var(--dark-blue)'; ?>;">
                                    <?php echo htmlspecialchars($log['admin_name']); ?>
                                </span>
                            </td>
                            <td><span class="action-badge <?php echo $badgeType; ?>"><?php echo htmlspecialchars($log['action']); ?></span></td>
                            <td><span style="font-family: monospace; color: #4a5568; font-size: 13px;"><?php echo htmlspecialchars($log['resource']); ?></span></td>
                            <td class="timestamp-cell"><i class="fa-regular fa-calendar-days"></i> <?php echo date("d M Y | h:i A", strtotime($log['timestamp'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center; padding: 30px; color: #718096;">No internal logs registered inside this network grid.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
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

    // Live ajax callback trigger handshake channel
    function filterAuditLogs(selectedAction, btn) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const tableBody = document.getElementById('logTableBody');
        
        const xhr = new XMLHttpRequest();
        xhr.open('GET', 'fetch_logs.php?action=' + encodeURIComponent(selectedAction), true);
        xhr.onload = function() {
            if (this.status === 200) {
                tableBody.innerHTML = this.responseText;
            }
        };
        xhr.send();
    }
</script>
</body>
</html>