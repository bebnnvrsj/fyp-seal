<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'verifier') {
    header("Location: ../login/login.php");
    exit();
}
require '../db_connect.php';

// Ambil maklumat verifier
$verifierName = isset($_SESSION['name']) ? $_SESSION['name'] : 'Verifier';
$verifierID = $_SESSION['staff_number'];

// 1. Statistik: Jumlah keseluruhan pengesahan oleh verifier ini
$stats_sql = "SELECT COUNT(*) as total FROM verificationlog WHERE verifierID = ?";
$stmt = $conn->prepare($stats_sql);
$stmt->bind_param("i", $verifierID);
$stmt->execute();
$total_stats = $stmt->get_result()->fetch_assoc();

// 2. Statistik: Pengesahan yang dibuat HARI INI sahaja
$today_sql = "SELECT COUNT(*) as today_total FROM verificationlog WHERE verifierID = ? AND DATE(verificationDate) = CURDATE()";
$stmt_today = $conn->prepare($today_sql);
$stmt_today->bind_param("i", $verifierID);
$stmt_today->execute();
$today_stats = $stmt_today->get_result()->fetch_assoc();

// 3. Statistik: Dokumen yang disahkan AUTHENTIC/VALID
$valid_sql = "SELECT COUNT(*) as valid_total FROM verificationlog WHERE verifierID = ? AND verificationStatus IN ('Valid', 'Authentic')";
$stmt_valid = $conn->prepare($valid_sql);
$stmt_valid->bind_param("i", $verifierID);
$stmt_valid->execute();
$valid_stats = $stmt_valid->get_result()->fetch_assoc();

// 4. Statistik Tambahan: Jumlah penipuan dikesan (TAMPERED DATA)
$tampered_sql = "SELECT COUNT(*) as tampered_total FROM verificationlog WHERE verifierID = ? AND verificationStatus IN ('Tampered', 'Forged', 'Invalid')";
$stmt_tampered = $conn->prepare($tampered_sql);
$stmt_tampered->bind_param("i", $verifierID);
$stmt_tampered->execute();
$tampered_stats = $stmt_tampered->get_result()->fetch_assoc();

// 5. Ambil 3 data log penipuan terakhir untuk tujuan Security Alert Box
$alerts_sql = "SELECT v.verificationID, v.documentID, v.verificationDate, doc_data.patientName 
               FROM verificationlog v
               LEFT JOIN (
                   SELECT CONCAT('MCUTHM', LPAD(mcID, 6, '0')) as docID, patientName FROM mc
                   UNION ALL
                   SELECT CONCAT('TSUTHM', LPAD(slipID, 6, '0')) as docID, patientName FROM timeslip
               ) AS doc_data ON v.documentID = doc_data.docID
               WHERE v.verifierID = ? AND v.verificationStatus IN ('Tampered', 'Forged', 'Invalid') 
               ORDER BY v.verificationDate DESC LIMIT 3";
$stmt_alerts = $conn->prepare($alerts_sql);
$stmt_alerts->bind_param("i", $verifierID);
$stmt_alerts->execute();
$alert_logs = $stmt_alerts->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Home - Verifier | SEAL</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            --sidebar-width: 260px;
            --header-bg: #2b7a9e;
            --main-bg: linear-gradient(to bottom, #caf0f8, #90e0ef, #48cae4);
            --dark-blue: #183055;
            --success-green: #1a7f4e;
            --alert-red: #d32f2f;
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
        .sidebar { width: var(--sidebar-width); height: 100vh; background-color: var(--dark-blue); color: white; position: fixed; left: 0; top: 0; transition: transform 0.3s ease-in-out; z-index: 1005; display: flex; flex-direction: column; }
        .sidebar.closed { transform: translateX(-100%); }
        .sidebar-header { padding: 20px; background-color: #122542; display: flex; align-items: center; gap: 15px; font-weight: bold; }
        .sidebar-menu { list-style: none; padding: 0; margin: 20px 0; }
        .sidebar-menu li a { padding: 15px 25px; display: flex; align-items: center; gap: 15px; color: #d1d9e6; text-decoration: none; transition: 0.2s; }
        .sidebar-menu li a:hover { background-color: #2b7a9e; color: white; }
        .sidebar-menu li a.active { background-color: #2b7a9e; color: white; border-left: 4px solid #fff; }

        /* ====== MAIN WRAPPER & HEADER LAYOUT ====== */
        .main-wrapper { flex: 1; display: flex; flex-direction: column; margin-left: var(--sidebar-width); transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out; width: 100%; box-sizing: border-box; }
        .main-wrapper.full-width { margin-left: 0 !important; }

        .header { height: 56px; background-color: var(--header-bg); display: flex; align-items: center; justify-content: space-between; padding: 0 24px; color: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); position: relative; z-index: 1000; }
        .toggle-btn { cursor: pointer; font-size: 20px; }

        /* CONTENT CONTAINER */
        .container { width: 95%; max-width: 1000px; margin: 30px auto; padding: 0 20px; display: flex; flex-direction: column; gap: 25px; }

        /* HERO SECTION */
        .page-hero { background: white; border-radius: 15px; padding: 35px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .hero-text h1 { margin: 0; color: var(--dark-blue); font-size: 28px; }
        .hero-text p { margin: 5px 0; color: #666; font-size: 15px; }

        /* SECURITY ALERT PANEL (DIBINA BARU) */
        .alert-card {
            background: #ffebee; border-left: 6px solid var(--alert-red); border-radius: 12px; padding: 20px;
            box-shadow: 0 4px 12px rgba(211, 47, 47, 0.1); margin-bottom: 5px;
        }
        .alert-card h2 { margin: 0 0 10px 0; color: var(--alert-red); font-size: 18px; display: flex; align-items: center; gap: 10px; }
        .alert-item { background: white; padding: 10px 15px; border-radius: 6px; margin-top: 8px; font-size: 13px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .alert-badge { background: var(--alert-red); color: white; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }

        /* STATS CARDS & GRIDS */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; }
        .stat-card { background: white; padding: 25px; border-radius: 15px; display: flex; align-items: center; gap: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border-left: 5px solid var(--header-bg); }
        .stat-icon { font-size: 35px; color: var(--header-bg); }
        .stat-info h3 { margin: 0; font-size: 26px; color: var(--dark-blue); }
        .stat-info p { margin: 0; font-size: 11px; color: #888; text-transform: uppercase; font-weight: bold; letter-spacing: 1px; }

        /* MENU GRID */
        .menu-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        .menu-box { background: white; border-radius: 20px; padding: 40px; text-align: center; text-decoration: none; color: var(--dark-blue); transition: 0.3s; box-shadow: 0 4px 15px rgba(0,0,0,0.08); border: 1px solid rgba(0,0,0,0.05); }
        .menu-box:hover { transform: translateY(-10px); box-shadow: 0 12px 30px rgba(0,0,0,0.15); border-color: var(--header-bg); }
        .menu-box i { font-size: 50px; color: var(--header-bg); margin-bottom: 20px; }
        .menu-box h2 { margin: 0; font-size: 20px; }
        .menu-box p { color: #777; font-size: 14px; margin-top: 10px; }

        @media (max-width: 1024px) {
            .main-wrapper {  }
        }

        @media (max-width: 768px) { 
            .menu-grid, .stats-grid { grid-template-columns: 1fr; } 
            .page-hero { flex-direction: column; text-align: center; gap: 20px; padding: 25px; } 
            .hero-text h1 { font-size: 22px; }
        }
    </style>
</head>

<body>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header"><i class="fa-solid fa-hospital-user"></i> <span>SEAL</span></div>
    <ul class="sidebar-menu">
        <li><a href="home_verifier.php" class="active"><i class="fa-solid fa-house"></i> Home</a></li>
        <li><a href="verify_document.php"><i class="fa-solid fa-magnifying-glass"></i> Verify Document</a></li>       
        <li><a href="verification_history.php"><i class="fa-solid fa-clock-rotate-left"></i> Verification History</a></li>
        <li><a href="../profile.php"><i class="fa-solid fa-user-gear"></i> Profile Settings</a></li>
        <li><a href="../login/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
    </ul>
</div>

<div class="main-wrapper" id="mainWrapper">
    <div class="header">
        <div class="header-left">
            <i class="fa-solid fa-bars toggle-btn" onclick="toggleSidebar()"></i>
            <span style="font-weight: 600; margin-left: 15px;">Verifier Dashboard</span>
        </div>
    </div>

    <div class="container">
        <div class="page-hero">
            <div class="hero-text">
                <h1>Welcome, <?php echo htmlspecialchars($verifierName); ?>!</h1>
                <p>Monitor verification metrics and trace security events in real-time.</p>
            </div>
            <div class="hero-icon">
                <i class="fa-solid fa-shield-halved" style="font-size: 60px; color: var(--header-bg); opacity: 0.15;"></i>
            </div>
        </div>

        <?php if ($tampered_stats['tampered_total'] > 0): ?>
        <div class="alert-card">
            <h2><i class="fa-solid fa-triangle-exclamation"></i> Security Warning: Tampered Data Detected!</h2>
            <p style="margin: 0; font-size: 13px; color: #555;">The following recent verification requests failed the cryptographic hash integrity test:</p>
            <?php while($alert = $alert_logs->fetch_assoc()): ?>
                <div class="alert-item">
                    <div>
                        <strong style="color: var(--alert-red);"><?php echo htmlspecialchars($alert['documentID']); ?></strong> 
                        - Patient: <?php echo strtoupper(htmlspecialchars($alert['patientName'])); ?>
                    </div>
                    <span class="alert-badge">MISMATCH</span>
                </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-clipboard-list"></i></div>
                <div class="stat-info">
                    <h3><?php echo $total_stats['total']; ?></h3>
                    <p>Total Verified</p>
                </div>
            </div>
            <div class="stat-card" style="border-left-color: #f39c12;">
                <div class="stat-icon" style="color: #f39c12;"><i class="fa-solid fa-calendar-day"></i></div>
                <div class="stat-info">
                    <h3><?php echo $today_stats['today_total']; ?></h3>
                    <p>Today's Checks</p>
                </div>
            </div>
            <div class="stat-card" style="border-left-color: var(--success-green);">
                <div class="stat-icon" style="color: var(--success-green);"><i class="fa-solid fa-circle-check"></i></div>
                <div class="stat-info">
                    <h3><?php echo $valid_stats['valid_total']; ?></h3>
                    <p>Authentic Docs</p>
                </div>
            </div>
            <div class="stat-card" style="border-left-color: var(--alert-red);">
                <div class="stat-icon" style="color: var(--alert-red);"><i class="fa-solid fa-shield-virus"></i></div>
                <div class="stat-info">
                    <h3><?php echo $tampered_stats['tampered_total']; ?></h3>
                    <p>Forged Detected</p>
                </div>
            </div>
        </div>

        <div class="menu-grid">
            <a href="verify_document.php" class="menu-box">
                <i class="fa-solid fa-file-circle-check"></i>
                <h2>Verify Document Workspace</h2>
                <p>Launch live camera QR scan or drag-and-drop secure digital PDFs.</p>
            </a>
            <a href="verification_history.php" class="menu-box">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <h2>Verification History</h2>
                <p>Review audit logs of all medical records you have previously verified.</p>
            </a>
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