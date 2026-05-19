<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/login.php");
    exit();
}
require '../db_connect.php'; 

$admin_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Admin User';

// Ambil data awal (Default: Semua log)
$sql = "SELECT a.logID, u.name as admin_name, a.action, a.resource, a.timestamp 
        FROM auditlog a 
        JOIN users u ON a.userID = u.userID 
        ORDER BY a.timestamp DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Logs - SEAL</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
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

        /* ====== SIDEBAR (Consistent) ====== */
        .sidebar { width: var(--sidebar-width); height: 100vh; background-color: var(--dark-blue); color: white; position: fixed; left: 0; top: 0; transition: transform 0.3s ease; z-index: 1000; display: flex; flex-direction: column; }
        .sidebar.closed { transform: translateX(-100%); }
        .sidebar-header { padding: 20px; background-color: #122542; display: flex; align-items: center; gap: 15px; font-weight: bold; }
        .sidebar-menu { list-style: none; padding: 0; margin: 20px 0; }
        .sidebar-menu li a { padding: 15px 25px; display: flex; align-items: center; gap: 15px; color: #d1d9e6; text-decoration: none; transition: 0.2s; }
        .sidebar-menu li a:hover { background-color: #2b7a9e; color: white; }
        .sidebar-menu li a.active { background-color: #2b7a9e; color: white; border-left: 4px solid #fff; }

        /* ====== MAIN WRAPPER (Consistent) ====== */
        .main-wrapper { flex: 1; display: flex; flex-direction: column; margin-left: var(--sidebar-width); transition: margin-left 0.3s ease; width: 100%; }
        .main-wrapper.full-width { margin-left: 0; }

        .header { height: 56px; background-color: var(--header-bg); display: flex; align-items: center; justify-content: space-between; padding: 0 24px; color: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .toggle-btn { cursor: pointer; font-size: 20px; }

        /* ====== CONTAINER ====== */
        .container { width: 95%; max-width: 1600px; margin: 30px auto; padding: 0 20px; display: flex; flex-direction: column; gap: 25px; }

        .page-hero {
            background: white; border-radius: 15px; padding: 25px 35px;
            display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .page-hero h1 { margin: 0; color: var(--dark-blue); font-size: 24px; }

        .management-card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        
        table { width: 100%; border-collapse: collapse; }
        th { background-color: #f8f9fa; padding: 15px; text-align: left; border-bottom: 2px solid #dee2e6; color: #555; font-weight: 600; }
        td { padding: 15px; border-bottom: 1px solid #eee; font-size: 14px; color: #333; }
        tr:hover { background-color: #f1f8ff; }

        .action-badge { padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: bold; background: #e9ecef; color: #495057; border: 1px solid #dee2e6; }
        
        .search-wrapper select { padding: 12px; border-radius: 10px; border: 1px solid #ddd; width: 300px; outline: none; }
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
            <h1><i class="fa-solid fa-clipboard-list"></i> System Audit Logs</h1>
            <div class="search-wrapper">
                <i class="fa-solid fa-filter"></i>
                <select id="actionFilter">
                    <option value="">All System Actions</option>
                    <option value="Create User">Create User</option>
                    <option value="Issue MC">Issue MC</option>
                    <option value="Issue Time-Slip">Issue Time-Slip</option>
                    <option value="Revoke Document">Revoke Document</option>
                    <option value="Verify Document">Verify Document</option>
                </select>
            </div>
        </div>

        <div class="management-card">
            <table>
                <thead>
                    <tr>
                        <th>Log ID</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Target Resource</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody id="logTableBody">
                    <?php if ($result->num_rows > 0): ?>
                        <?php while($log = $result->fetch_assoc()): ?>
                        <tr>
                            <td><strong>#<?php echo $log['logID']; ?></strong></td>
                            <td><?php echo htmlspecialchars($log['admin_name']); ?></td>
                            <td><span class="action-badge"><?php echo htmlspecialchars($log['action']); ?></span></td>
                            <td><?php echo htmlspecialchars($log['resource']); ?></td>
                            <td><?php echo date("d M Y | h:i A", strtotime($log['timestamp'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center; padding: 30px;">No system logs available.</td></tr>
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

    // AJAX Filter (Same logic as before but updated IDs)
    document.getElementById('actionFilter').addEventListener('change', function() {
        const selectedAction = this.value;
        const tableBody = document.getElementById('logTableBody');
        
        const xhr = new XMLHttpRequest();
        xhr.open('GET', 'fetch_logs.php?action=' + encodeURIComponent(selectedAction), true);
        xhr.onload = function() {
            if (this.status === 200) {
                tableBody.innerHTML = this.responseText;
            }
        };
        xhr.send();
    });
</script>
</body>
</html>