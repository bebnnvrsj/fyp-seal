<?php
date_default_timezone_set('Asia/Kuala_Lumpur');
session_start();

// Sekatan keselamatan portal doktor
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}
require '../db_connect.php';

// Fetch current doctor details for the hero and sidebar
$doctorID = $_SESSION['userID'];
$doctor_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Doctor';

// ─── DIBAIKI: KEPUTUSAN STATUS IKUT ARAHAN SV (TIADA EXPIRED BAGI REKOD SAH) ───
function getDisplayStatus($status) {
    $statusUpper = strtoupper(trim($status));
    if ($statusUpper == 'REVOKED') return 'Revoked';
    return 'Active'; // Dokumen yang sah akan kekal Active sebagai rekod sejarah tulen
}

// Combine both table mc and timeslip
$sql = "SELECT * FROM (
            SELECT mcID AS docID, patientName, 'MC' AS documentType, startDate AS issueDate, endDate AS expiryDate, status 
            FROM mc 
            WHERE doctorID = ?
            UNION
            SELECT slipID AS docID, patientName, 'Time-Slip' AS documentType, visitDate AS issueDate, visitDate AS expiryDate, status 
            FROM timeslip 
            WHERE doctorID = ?
        ) AS combined_history
        ORDER BY issueDate DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $doctorID, $doctorID);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Issuance History - SEAL</title>
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

        /* ====== CONTAINER & CARDS ====== */
        .container { width: 95%; max-width: 100%; margin: 30px auto; padding: 0 40px; box-sizing: border-box; display: flex; flex-direction: column; gap: 25px; }

        .page-hero {
            background: white; border-radius: 15px; padding: 25px 35px;
            display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .hero-info h1 { margin: 0; color: var(--dark-blue); font-size: 24px; display: flex; align-items: center; gap: 12px; }
        .hero-info p { margin: 5px 0 0; color: #666; font-size: 14px; }

        /* Search Controls Bar */
        .actions-bar { display: flex; justify-content: flex-start; align-items: center; }
        .search-wrapper { position: relative; width: 350px; }
        .search-wrapper input {
            width: 100%; padding: 12px 15px 12px 45px;
            border-radius: 10px; border: 1px solid #cbd5e1; outline: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05); font-size: 14px; transition: 0.2s;
        }
        .search-wrapper input:focus { border-color: var(--header-bg); box-shadow: 0 0 0 3px rgba(43, 122, 158, 0.15); }
        .search-wrapper i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #a0aec0; font-size: 16px; }

        /* TABLE HOVER LAYOUT */
        .table-card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0px 4px 15px rgba(0,0,0,0.1); width: 100%; overflow-x: auto; box-sizing: border-box; }
        .section-title { font-size: 18px; color: var(--dark-blue); margin-top: 0; margin-bottom: 15px; font-weight: 600; display: flex; align-items: center; gap: 10px; }

        table { width: 100%; border-collapse: collapse; table-layout: auto; }
        th { background-color: #f8f9fa; color: #555; text-align: left; padding: 15px; border-bottom: 2px solid #dee2e6; font-weight: 600; }
        td { padding: 15px; border-bottom: 1px solid #eee; font-size: 14px; color: #333; }
        tr:hover { background-color: #f1f8ff; }

        .patient-link { text-decoration: none; color: var(--dark-blue); font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; }
        .patient-link:hover { color: var(--header-bg); text-decoration: underline; }

        /* Badges Moden */
        .type-badge { padding: 4px 10px; border-radius: 5px; font-size: 12px; font-weight: bold; text-transform: uppercase; display: inline-flex; align-items: center; gap: 6px; }
        .badge-mc { background-color: #e3f2fd; color: #0d47a1; }
        .badge-ts { background-color: #f3e5f5; color: #6a1b9a; }
        
        .status-dot { font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }
        .status-active { color: var(--success-green); }
        .status-revoked { color: var(--danger-red); }

        @media (max-width: 1200px) { .container { padding: 0 20px; } }
        @media (max-width: 850px) {
            .page-hero { flex-direction: column; text-align: center; gap: 15px; }
            .actions-bar, .search-wrapper { width: 100%; }
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
        <li><a href="manage_documents.php"><i class="fa-solid fa-file-pen"></i> Manage Documents</a></li>
        <li><a href="view_history.php" class="active"><i class="fa-solid fa-database"></i> Issuance History</a></li>
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
                <h1><i class="fa-solid fa-clock-rotate-left"></i> Issuance History</h1>
                <p>Reviewing all immutable medical documents issued under your practitioner signature.</p>
            </div>
        </div>

        <div class="actions-bar">
            <div class="search-wrapper">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Search patient name...">
            </div>
        </div>

        <div class="table-card">
            <div class="section-title"><i class="fa-solid fa-clock-rotate-left"></i> Document Issuance Logs History</div>
            <table id="docTable">
                <thead>
                    <tr>
                        <th style="width: 30%;">Patient Name</th>
                        <th style="width: 15%;">Document Type</th>
                        <th style="width: 20%;">Date Issued</th>
                        <th style="width: 20%;">Expiry / End Date</th>
                        <th style="width: 15%; text-align: center;">Registry Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()):
                            $docType = strtoupper($row['documentType']);
                            $isMC = ($docType === 'MC');
                            $typeClass = $isMC ? 'badge-mc' : 'badge-ts';                           
                            $typeIcon = $isMC ? 'fa-file-medical' : 'fa-user-clock';

                            $statusLabel = getDisplayStatus($row['status']); 
                            $statusClass = (strtolower($statusLabel) === 'revoked') ? 'status-revoked' : 'status-active';
                        ?>
                        <tr>
                           <td>
                                <a href="view_doc.php?hash=<?php echo $row['documentHash'] ?? ''; ?>&type=<?php echo strtolower($row['documentType']); ?>" class="patient-link">
                                    <i class="fa-solid fa-user-injured" style="opacity: 0.6;"></i>
                                    <?php echo htmlspecialchars($row['patientName']); ?>
                                </a>
                            </td> 
                            <td><span class="type-badge <?php echo $typeClass; ?>"><i class="fa-solid <?php echo $typeIcon; ?>"></i> <?php echo $row['documentType']; ?></span></td>                         
                            <td><i class="fa-regular fa-calendar" style="color:var(--header-bg); margin-right:5px;"></i> <?php echo date("d M Y", strtotime($row['issueDate'])); ?></td>
                            <td>
                                <?php if ($isMC): ?>
                                    <i class="fa-regular fa-calendar-check" style="color:#2f855a; margin-right:5px;"></i> <?php echo date("d M Y", strtotime($row['expiryDate'])); ?>
                                <?php else: ?>
                                    <span style="color:#aaa; font-style: italic;">- Single Day -</span>
                                <?php endif; ?>
                            </td>                            
                            <td style="text-align:center;">
                                <span class="status-dot <?php echo $statusClass; ?>">
                                    ● <?php echo $statusLabel; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center; padding: 40px; color: #999;">No issued records found.</td></tr>
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

    // WAJIB: Kekalkan fungsi asal carian JavaScript (Search Engine)
    function filterTable() {
        const input = document.getElementById("searchInput");
        const filter = input.value.toUpperCase();
        const table = document.getElementById("docTable");
        const tr = table.getElementsByTagName("tr");

        for (let i = 1; i < tr.length; i++) {
            const td = tr[i].getElementsByTagName("td")[0]; // Kolum Patient Name
            if (td) {
                const txtValue = td.textContent || td.innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }
    }
</script>
</body>
</html>