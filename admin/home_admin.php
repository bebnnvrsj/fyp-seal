<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Pastikan pengguna sudah melepasi 2FA dan merupakan Admin
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/login.php"); // Pastikan path ini betul ke folder login
    exit();
}

// 2. Ambil maklumat untuk paparan profil
$admin_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Administrator';
$staff_number = isset($_SESSION['staff_number']) ? $_SESSION['staff_number'] : 'N/A';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Home - Admin | SEAL</title>
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

        /* ====== CONTAINER (STRETCHED) ====== */
        .container { 
            width: 95%; /* Make it take most of the width */
            max-width: 1600px; /* High limit for ultra-wide screens */
            margin: 30px auto; 
            padding: 0 20px; 
            display: flex; 
            flex-direction: column; 
            gap: 25px; 
        }

        /* LONG WELCOME CARD (Like profile.php hero) */
        .welcome-hero {
            background: white;
            border-radius: 15px;
            padding: 35px;
            display: flex;
            align-items: center;
            gap: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            width: 100%; /* Stretch long */
            box-sizing: border-box;
        }

        .hero-avatar {
            width: 100px; height: 100px;
            background: #e3f2fd; color: var(--header-bg);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 40px; border: 4px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .hero-text h1 { margin: 0; color: var(--dark-blue); font-size: 28px; }
        .hero-text p { margin: 5px 0; color: #666; font-size: 15px; }

        /* GRID CONTENT (Full Page Spread) */
        .main-content {
            display: grid;
            grid-template-columns: repeat(4, 1fr); /* 4 Columns spread across the page */
            gap: 20px;
            width: 100%;
        }

        .menu-box {
            background: white;
            border-radius: 15px;
            padding: 30px 20px;
            text-align: center;
            text-decoration: none;
            color: var(--dark-blue);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .menu-box:hover {
            transform: translateY(-5px);
            background: #f8fdff;
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
            border-color: var(--header-bg);
        }

        .menu-icon { font-size: 45px; color: var(--header-bg); margin-bottom: 15px; }
        .menu-box h2 { font-size: 18px; margin: 0; font-weight: 700; }
        .menu-box p { font-size: 13px; color: #777; margin-top: 10px; line-height: 1.4; }

        /* AUDIT CARD (Long) */
        .audit-summary-card {
            background: white; border-radius: 15px; padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            width: 100%; box-sizing: border-box;
        }

        .audit-item { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #eee; }
        .audit-info { display: flex; flex-direction: column; }
        .audit-action { font-weight: 700; color: var(--dark-blue); font-size: 14px; }
        .audit-time { color: #999; font-size: 12px; }

        @media (max-width: 1200px) { .main-content { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) { 
            .main-content { grid-template-columns: 1fr; }
            .welcome-hero { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header"><i class="fa-solid fa-user-shield"></i> <span>SEAL</span></div>
    <ul class="sidebar-menu">
        <li><a href="../admin/home_admin.php" class="active"><i class="fa-solid fa-house"></i> Dashboard</a></li>
        <li><a href="../admin/register_patient.php"><i class="fa-solid fa-user-plus"></i> Register Patient</a></li>
        <li><a href="../admin/user_management.php"><i class="fa-solid fa-users-gear"></i> User Management</a></li>
        <li><a href="../admin/document_monitoring.php"><i class="fa-solid fa-file-shield"></i> Doc Monitoring</a></li>
        <li><a href="../admin/audit_logs.php"><i class="fa-solid fa-clipboard-list"></i> Audit Logs</a></li>
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
        <div class="welcome-hero">
            <div class="hero-avatar"><i class="fa-solid fa-user-tie"></i></div>
            <div class="hero-text">
                <h1>Welcome back, <?php echo htmlspecialchars($admin_name); ?></h1>
                <p>System Control Center | Access Level: <span style="background:var(--dark-blue); color:white; padding: 2px 10px; border-radius:10px; font-size:11px;">SUPER ADMIN</span></p>
                <p style="font-size: 13px; color: #888;"><i class="fa-solid fa-calendar-day"></i> System Date: <?php echo date("d M Y"); ?></p>
            </div>
        </div>

        <div class="main-content">
            <a href="register_patient.php" class="menu-box">
                <div class="menu-icon"><i class="fa-solid fa-user-plus"></i></div>
                <h2>Register Patient</h2>
                <p>Register new patients and manage their information.</p>
            </a>
            <a href="user_management.php" class="menu-box">
                <div class="menu-icon"><i class="fa-solid fa-users-gear"></i></div>
                <h2>User Management</h2>
                <p>Manage system users, roles, and control account permissions.</p>
            </a>
            <a href="document_monitoring.php" class="menu-box">
                <div class="menu-icon"><i class="fa-solid fa-file-shield"></i></div>
                <h2>Document Monitoring</h2>
                <p>Supervise issued MCs and Slips to ensure blockchain integrity.</p>
            </a>
            <a href="audit_logs.php" class="menu-box">
                <div class="menu-icon"><i class="fa-solid fa-clipboard-list"></i></div>
                <h2>Audit Logs</h2>
                <p>Detailed chronological records of system and admin actions.</p>
            </a>
            
        </div>

        <div class="audit-summary-card">
            <h2 style="color:var(--dark-blue); border-bottom: 2px solid #f0f0f0; padding-bottom:10px; margin-top:0;">
                <i class="fa-solid fa-clock-rotate-left"></i> Recent Activities
            </h2>
            <?php
            require '../db_connect.php';
            $audit_query = "SELECT * FROM auditlog ORDER BY timestamp DESC LIMIT 4";
            $audit_result = $conn->query($audit_query);
            if ($audit_result && $audit_result->num_rows > 0) {
                while($log = $audit_result->fetch_assoc()) {
                    echo '<div class="audit-item">';
                    echo '    <div class="audit-info">';
                    echo '        <span class="audit-action">' . htmlspecialchars($log['action']) . '</span>';
                    echo '        <span style="font-size:13px; color:#666;">' . htmlspecialchars($log['resource']) . '</span>';
                    echo '    </div>';
                    echo '    <span class="audit-time">' . date("d M, h:i A", strtotime($log['timestamp'])) . '</span>';
                    echo '</div>';
                }
            }
            ?>
            <a href="audit_logs.php" style="display:block; text-align:center; margin-top:15px; color:var(--header-bg); text-decoration:none; font-weight:600;">View Full Audit Trail <i class="fa-solid fa-arrow-right"></i></a>
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
</script>
</body>
</html>