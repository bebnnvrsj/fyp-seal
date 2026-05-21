<?php
date_default_timezone_set('Asia/Kuala_Lumpur');
session_start();

// Sekatan keselamatan eksklusif portal doktor
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login/login.php");
    exit();
}
require '../db_connect.php'; 

$doctorID = $_SESSION['userID'];
$doctor_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Doctor';

// Susunan kolum yang dibenarkan untuk fungsi sorting
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'issueDate';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';
$allowed_columns = ['documentID', 'patientName', 'documentType', 'issueDate', 'status'];
if (!in_array($sort_column, $allowed_columns)) { $sort_column = 'issueDate'; }
$sort_order = ($sort_order === 'ASC') ? 'ASC' : 'DESC';

// Query penyatuan rekod MC & Timeslip milik doktor yang sedang log masuk
$sql = "SELECT * FROM (
            SELECT mcID AS documentID, patientName, 'mc' AS documentType, 
                   startDate AS issueDate, endDate AS expiryDate, status, documentHash 
            FROM mc 
            WHERE doctorID = ?
            UNION
            SELECT slipID AS documentID, patientName, 'timeslip' AS documentType, 
                   visitDate AS issueDate, visitDate AS expiryDate, status, documentHash 
            FROM timeslip 
            WHERE doctorID = ?
        ) AS combined_docs
        ORDER BY $sort_column $sort_order";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $doctorID, $doctorID); 
$stmt->execute();
$result = $stmt->get_result();

// ─── DIBAIKI: STATUS MENGIKUT ARAHAN SUPERVISOR (TIADA EXPIRED) ───
function getDisplayStatus($status) {
    $statusUpper = strtoupper(trim($status));
    if ($statusUpper === 'REVOKED') return 'Revoked';
    if ($statusUpper === 'SIGNED' || $statusUpper === 'ACTIVE') return 'Active';
    return ucfirst(strtolower($status)); 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Digital Records - SEAL</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --sidebar-width: 260px;
            --header-bg: #2b7a9e;
            --main-bg: linear-gradient(to bottom, #caf0f8, #90e0ef, #48cae4);
            --dark-blue: #183055;
            --success-green: #28a745;
            --danger-red: #dc3545;
            --warning-orange: #fd7e14;
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

        .has-submenu { position: relative; }
        .submenu { list-style: none; padding: 0; margin: 0; max-height: 0; overflow: hidden; background-color: #122542; transition: max-height 0.4s ease-out; }
        .has-submenu:hover .submenu { max-height: 200px; }
        .submenu li a { padding: 12px 25px 12px 60px !important; font-size: 13px !important; color: #a0aec0 !important; }
        .has-submenu > a::after { content: '\f107'; font-family: 'Font Awesome 6 Free'; font-weight: 900; float: right; font-size: 12px; transition: transform 0.3s; }
        .has-submenu:hover > a::after { transform: rotate(180deg); }

        /* ====== MAIN WRAPPER ====== */
        .main-wrapper { flex: 1; display: flex; flex-direction: column; margin-left: var(--sidebar-width); transition: margin-left 0.3s ease; width: 100%; }
        .main-wrapper.full-width { margin-left: 0; }
        .header { height: 56px; background-color: var(--header-bg); display: flex; align-items: center; justify-content: space-between; padding: 0 24px; color: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .toggle-btn { cursor: pointer; font-size: 20px; }

        /* ====== CONTAINER & CARD ====== */
        .container { width: 95%; max-width: 100%; margin: 30px auto; padding: 0 40px; box-sizing: border-box; display: flex; flex-direction: column; gap: 25px; }

        .page-hero {
            background: white; border-radius: 15px; padding: 25px 35px;
            display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .hero-info h1 { margin: 0; color: var(--dark-blue); font-size: 24px; display: flex; align-items: center; gap: 12px; }
        .hero-info p { margin: 5px 0 0; color: #666; font-size: 14px; }

        .manage-card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0px 4px 15px rgba(0,0,0,0.1); width: 100%; overflow-x: auto; box-sizing: border-box; }
        .section-title { font-size: 18px; color: var(--dark-blue); margin-top: 0; margin-bottom: 15px; font-weight: 600; display: flex; align-items: center; gap: 10px; }

        table { width: 100%; border-collapse: collapse; table-layout: auto; }
        th { background-color: #f8f9fa; color: #555; text-align: left; padding: 15px; border-bottom: 2px solid #dee2e6; font-weight: 600; }
        th a { display: flex; align-items: center; gap: 8px; width: 100%; text-decoration: none; color: inherit; }
        th:hover { background-color: #eef2f7; }
        td { padding: 15px; border-bottom: 1px solid #eee; font-size: 14px; color: #333; }
        tr:hover { background-color: #f1f8ff; }

        /* ====== BADGES & STATUS ====== */
        .badge-blockchain { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: bold; background: #e8f5e9; color: var(--success-green); border: 1px solid #c8e6c9; display: inline-block; margin-top: 5px; }
        .type-badge { padding: 4px 10px; border-radius: 5px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .type-mc { background: #e3f2fd; color: #0d47a1; }
        .type-ts { background: #f3e5f5; color: #6a1b9a; }

        .status-dot { font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }
        .status-active { color: var(--success-green); }
        .status-revoked { color: var(--danger-red); }

        .action-container { display: flex; gap: 15px; }
        .view-btn { 
            background: var(--header-bg); color: white; text-decoration: none; 
            padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; 
            display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; 
            box-shadow: 0 2px 4px rgba(43,122,158,0.2);
        }
        .view-btn:hover { background: var(--dark-blue); transform: translateY(-1px); }

        @media (max-width: 1200px) { .container { padding: 0 20px; } }
        @media (max-width: 850px) {
            .page-hero { flex-direction: column; text-align: center; gap: 15px; }
            td, th { padding: 10px; font-size: 13px; }
        }
    </style>
</head>
<body>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header"><i class="fa-solid fa-user-doctor"></i> <span>SEAL</span></div>
    <ul class="sidebar-menu">
        <li><a href="home_doctor.php"><i class="fa-solid fa-house"></i> Dashboard</a></li>
        <li class="has-submenu">
            <a href="create_document.php"><i class="fa-solid fa-plus"></i> Create Document</a>
            <ul class="submenu">
                <li><a href="create_mc.php"><i class="fa-solid fa-file-medical"></i> Medical Certificate</a></li>
                <li><a href="create_timeslip.php"><i class="fa-solid fa-clock-rotate-left"></i> Time Slip</a></li>
            </ul>
        </li>
        <li><a href="manage_documents.php" class="active"><i class="fa-solid fa-file-pen"></i> Manage Documents</a></li>
        <li><a href="view_history.php"><i class="fa-solid fa-database"></i> Issuance History</a></li>
        <li><a href="../profile.php"><i class="fa-solid fa-user-gear"></i> Profile Settings</a></li>
        <li><a href="../login/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
    </ul>
</div>

<div class="main-wrapper" id="mainWrapper">
    <div class="header">
        <div class="header-left">
            <i class="fa-solid fa-bars toggle-btn" onclick="toggleSidebar()"></i>
            <span style="font-weight: 600; margin-left: 15px;">Doctor Portal</span>
        </div>
    </div>

    <div class="container">
        <div class="page-hero">
            <div class="hero-info">
                <h1><i class="fa-solid fa-file-signature"></i> Issued Documents Registry</h1>
                <p>Welcome, Dr. <?php echo htmlspecialchars($doctor_name); ?>. Review and monitor previously anchored digital medical records.</p>
            </div>
        </div>

        <div class="manage-card">
            <div class="section-title"><i class="fa-solid fa-folder-open"></i> Live Medical Sijil & Time-Slip Directory</div>
            <table>
                <thead>
                    <tr>
                        <?php 
                        function getSortLink($col, $current_sort, $current_order) {
                            $new_order = ($current_sort == $col && $current_order == 'ASC') ? 'DESC' : 'ASC';
                            $icon = ($current_sort == $col) ? ($current_order == 'ASC' ? ' <i class="fa-solid fa-sort-up"></i>' : ' <i class="fa-solid fa-sort-down"></i>') : ' <i class="fa-solid fa-sort" style="opacity:0.3"></i>';
                            return "<a href='?sort=$col&order=$new_order'>$icon ";
                        }
                        ?>
                        <th style="width: 15%;"><?php echo getSortLink('documentID', $sort_column, $sort_order); ?>Doc ID</a></th>
                        <th style="width: 25%;"><?php echo getSortLink('patientName', $sort_column, $sort_order); ?>Patient Name</a></th>
                        <th style="width: 15%;"><?php echo getSortLink('documentType', $sort_column, $sort_order); ?>Document Type</a></th>
                        <th style="width: 25%;"><?php echo getSortLink('issueDate', $sort_column, $sort_order); ?>Covered Validity Timeline</a></th>
                        <th style="width: 10%;">Registry Status</th> 
                        <th style="width: 10%; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): 
                            $status = getDisplayStatus($row['status']);
                            $statusClass = (strtolower($status) === 'revoked') ? 'status-revoked' : 'status-active';
                            $typeClass = ($row['documentType'] === 'mc') ? 'type-mc' : 'type-ts';
                        ?>
                        <tr>
                            <td>
                                <strong style="color:var(--dark-blue);">
                                    <?php 
                                        $prefix = ($row['documentType'] === 'mc' ? 'MCUTHM' : 'TSUTHM');
                                        echo $prefix . str_pad($row['documentID'], 6, "0", STR_PAD_LEFT); 
                                    ?>
                                </strong><br>
                                <span class="badge-blockchain"><i class="fa-solid fa-cube"></i> Anchored</span>
                            </td>
                            <td><span style="font-weight: 600; color: #1a202c;"><?php echo htmlspecialchars($row['patientName']); ?></span></td>
                            <td><span class="type-badge <?php echo $typeClass; ?>"><?php echo ($row['documentType'] === 'mc') ? 'MC' : 'Time Slip'; ?></span></td>
                            <td>
                                <span style="color:#4a5568; font-weight: 500;">
                                    <i class="fa-regular fa-calendar-check" style="color:var(--header-bg);"></i> 
                                    <?php echo date("d M Y", strtotime($row['issueDate'])); ?>
                                    <?php if($row['documentType'] == 'mc'): ?>
                                        ➔ <?php echo date("d M Y", strtotime($row['expiryDate'])); ?>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-dot <?php echo $statusClass; ?>">
                                    ● <?php echo $status; ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <div class="action-container" style="justify-content: center;">
                                    <a href="view_doc.php?hash=<?php echo $row['documentHash']; ?>&type=<?php echo $row['documentType']; ?>" class="view-btn" title="View Document Structure">
                                        <i class="fa-solid fa-eye"></i> View
                                    </a>
                                </div> 
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center; padding: 30px; color: #718096;">No issued medical documents recorded under your practitioner signature.</td></tr>
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
</script>
</body>
</html>