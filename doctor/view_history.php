<?php
date_default_timezone_set('Asia/Kuala_Lumpur');
session_start();

// Sekatan keselamatan portal doktor
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}
require '../db_connect.php';

// Ambil maklumat doktor yang sedang log masuk
$doctorID = $_SESSION['userID'];
$doctor_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Doctor';

// Susunan kolum sorting yang dibenarkan (Diselaraskan dengan manage_documents)
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'createdAt';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';
$allowed_columns = ['docID', 'patientName', 'documentType', 'issueDate', 'createdAt'];
if (!in_array($sort_column, $allowed_columns)) { $sort_column = 'createdAt'; }
$sort_order = ($sort_order === 'ASC') ? 'ASC' : 'DESC';

// ─── DIBAIKI: KEPUTUSAN STATUS IKUT ARAHAN SV (TIADA EXPIRED BAGI REKOD SAH) ───
function getDisplayStatus($status) {
    $statusUpper = strtoupper(trim($status));
    if ($statusUpper == 'REVOKED') return 'Revoked';
    return 'Active'; 
}

// Combine both table mc and timeslip dengan sokongan Sorting Kolum Dinamik
$sql = "SELECT * FROM (
            SELECT mcID AS docID, patientName, 'MC' AS documentType, startDate AS issueDate, endDate AS expiryDate, status, createdAt, documentHash 
            FROM mc 
            WHERE doctorID = ?
            UNION
            SELECT slipID AS docID, patientName, 'Time-Slip' AS documentType, visitDate AS issueDate, visitDate AS expiryDate, status, createdAt, documentHash 
            FROM timeslip 
            WHERE doctorID = ?
        ) AS combined_history
        ORDER BY $sort_column $sort_order";

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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* ─── UI KONSISTENSI TOTAL: Diambil bulat-bulat daripada manage_documents.php ─── */
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

        /* ====== CONTAINER & CARD ====== */
        .container { width: 95%; max-width: 100%; margin: 30px auto; padding: 0 40px; box-sizing: border-box; display: flex; flex-direction: column; gap: 25px; }

        .page-hero {
            background: white; border-radius: 15px; padding: 25px 35px;
            display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .hero-info h1 { margin: 0; color: var(--dark-blue); font-size: 24px; display: flex; align-items: center; gap: 12px; }
        .hero-info p { margin: 5px 0 0; color: #666; font-size: 14px; }

        /* ─── 🆕 DIBAIKI MUKTAMAD: CONTROLS BAR SYSTEM ALIGNMENT TERPUSAT (CENTERED) ─── */
        .controls-bar {
            display: flex;
            flex-direction: column;
            align-items: center; /* Paksa bar carian & tab duduk di tengah halaman simetri */
            justify-content: center;
            gap: 20px;
            width: 100%;
            margin-top: 5px;
        }
        
        .search-wrapper { position: relative; width: 450px; max-width: 100%; }
        .search-wrapper input {
            width: 100%; padding: 12px 15px 12px 45px;
            border-radius: 25px; /* Format membulat penuh kapsul premium */
            border: 1px solid #cbd5e1; outline: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06); font-size: 14px; transition: 0.2s;
            text-align: center; /* Teks input carian menaip di tengah */
            box-sizing: border-box;
        }
        .search-wrapper input:focus { border-color: var(--header-bg); box-shadow: 0 0 0 3px rgba(43, 122, 158, 0.15); }
        .search-wrapper i { position: absolute; left: 20px; top: 50%; transform: translateY(-50%); color: #a0aec0; font-size: 16px; }

        /* 💡 DESIGN EXCLUSIVE: IPHONE BUBBLE GLASS TAB CONTEXT MATRIX */
        .tabs-container { 
            display: inline-flex; 
            justify-content: center;
            align-items: center;
            gap: 12px; 
            background: rgba(255, 255, 255, 0.75); 
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); /* Kesan kabur cermin lutsinar iPhone */
            padding: 8px 16px; 
            border-radius: 40px; 
            box-shadow: 0 8px 25px rgba(0,0,0,0.06);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        .tab-btn { 
            padding: 12px 26px; 
            border: none; 
            border-radius: 30px; 
            cursor: pointer; 
            background: transparent; 
            color: var(--dark-blue); 
            font-weight: 700; 
            font-size: 14px; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tab-btn:hover { background: rgba(43, 122, 158, 0.08); color: var(--header-bg); }
        .tab-btn.active { 
            background: #2b7a9e; 
            color: white !important; 
            box-shadow: 0 4px 15px rgba(43, 122, 158, 0.35); 
        }

        /* TABLE LAYOUT HOVER SYSTEM */
        .manage-card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0px 4px 15px rgba(0,0,0,0.1); width: 100%; overflow-x: auto; box-sizing: border-box; }
        .section-title { font-size: 18px; color: var(--dark-blue); margin-top: 0; margin-bottom: 15px; font-weight: 600; display: flex; align-items: center; gap: 10px; }

        table { width: 100%; border-collapse: collapse; table-layout: auto; }
        th { background-color: #f8f9fa; color: #555; text-align: left; padding: 15px; border-bottom: 2px solid #dee2e6; font-weight: 600; font-size: 14px; }
        th a { display: flex; align-items: center; gap: 8px; width: 100%; text-decoration: none; color: inherit; }
        th:hover { background-color: #eef2f7; }
        td { padding: 15px; border-bottom: 1px solid #eee; font-size: 14px; color: #333; vertical-align: middle; }
        tr.doc-row:hover { background-color: #f1f8ff; }

        /* Badges & Status style matching */
        .badge-blockchain { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: bold; background: #e8f5e9; color: var(--success-green); border: 1px solid #c8e6c9; display: inline-block; margin-top: 5px; }
        .type-badge { padding: 4px 10px; border-radius: 5px; font-size: 12px; font-weight: bold; text-transform: uppercase; display: inline-flex; align-items: center; gap: 5px; }
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

        @media (max-width: 850px) {
            .page-hero { flex-direction: column; text-align: center; gap: 15px; }
            .controls-bar { width: 100%; }
            .search-wrapper { width: 100%; }
            .tabs-container { flex-direction: column; width: 100%; border-radius: 20px; }
            .tab-btn { width: 100%; justify-content: center; }
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
                <h1><i class="fa-solid fa-clock-rotate-left"></i> Issuance History Logs</h1>
                <p>Welcome, Dr. <?php echo htmlspecialchars($doctor_name); ?>. Reviewing all immutable medical logs issued under your practitioner signature.</p>
            </div>
        </div>

        <div class="controls-bar">
            <div class="search-wrapper">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Search patient name...">
            </div>

            <div class="tabs-container">
                <button class="tab-btn active" onclick="filterTableType('ALL', this)"><i class="fa-solid fa-layer-group"></i> All Logs</button>
                <button class="tab-btn" onclick="filterTableType('MC', this)"><i class="fa-solid fa-file-prescription"></i> Medical Certificates</button>
                <button class="tab-btn" onclick="filterTableType('TIMESLIP', this)"><i class="fa-solid fa-user-clock"></i> Time Slips</button>
            </div>
        </div>

        <div class="manage-card">
            <div class="section-title"><i class="fa-solid fa-clock-rotate-left"></i> Document Issuance Logs History Directory</div>
            <table id="docTable">
                <thead>
                    <tr>
                        <?php 
                        function getSortLink($col, $current_sort, $current_order) {
                            $new_order = ($current_sort == $col && $current_order == 'ASC') ? 'DESC' : 'ASC';
                            $icon = ($current_sort == $col) ? ($current_order == 'ASC' ? ' <i class="fa-solid fa-sort-up"></i>' : ' <i class="fa-solid fa-sort-down"></i>') : ' <i class="fa-solid fa-sort" style="opacity:0.3"></i>';
                            return "<a href='?sort=$col&order=$new_order'>$icon ";
                        }
                        ?>
                        <th style="width: 15%;"><?php echo getSortLink('docID', $sort_column, $sort_order); ?>Doc ID</a></th>
                        <th style="width: 20%;"><?php echo getSortLink('patientName', $sort_column, $sort_order); ?>Patient Name</a></th>
                        <th style="width: 15%;"><?php echo getSortLink('documentType', $sort_column, $sort_order); ?>Document Type</a></th>
                        <th style="width: 20%;"><?php echo getSortLink('issueDate', $sort_column, $sort_order); ?>Timeline Details</a></th>
                        <th style="width: 15%; text-align: center;">Leave Duration</th>
                        <th style="width: 15%;"><?php echo getSortLink('createdAt', $sort_column, $sort_order); ?>Issued At</a></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()):
                            $docType = strtoupper(trim($row['documentType']));
                            $isMC = ($docType === 'MC');
                            $typeClass = $isMC ? 'type-mc' : 'type-ts';                           
                            $typeIcon = $isMC ? 'fa-file-medical' : 'fa-clock';

                            $statusLabel = getDisplayStatus($row['status']); 
                            $statusClass = (strtolower($statusLabel) === 'revoked') ? 'status-revoked' : 'status-active';

                            // Hitung tempoh cuti (Leave Period) secara tepat
                            if ($isMC) {
                                $start = new DateTime($row['issueDate']);
                                $end = new DateTime($row['expiryDate']);
                                $interval = $start->diff($end);
                                $days = $interval->days + 1;
                                $durationText = $days . ($days > 1 ? ' days' : ' day');
                            } else {
                                $durationText = '<span style="color:#aaa; font-style: italic;">Single Day Visit</span>';
                            }
                        ?>
                        <tr class="doc-row" data-type="<?php echo ($isMC ? 'MC' : 'TIMESLIP'); ?>">
                            <td>
                                <strong style="color:var(--dark-blue);">
                                    <?php 
                                        $prefix = ($isMC ? 'MCUTHM' : 'TSUTHM');
                                        echo $prefix . str_pad($row['docID'], 6, "0", STR_PAD_LEFT); 
                                    ?>
                                </strong><br>
                                <span class="badge-blockchain"><i class="fa-solid fa-cube"></i> Anchored</span>
                            </td>
                            <td><span style="font-weight: 600; color: #1a202c;"><?php echo htmlspecialchars($row['patientName']); ?></span></td>
                            <td><span class="type-badge <?php echo $typeClass; ?>"><i class="fa-solid <?php echo $typeIcon; ?>"></i> <?php echo ($isMC ? 'MC' : 'Time Slip'); ?></span></td>                         
                            <td>
                                <span style="color:#4a5568; font-weight: 500;">
                                    <i class="fa-regular fa-calendar" style="color:var(--header-bg); margin-right:3px;"></i> 
                                    <?php echo date("d M Y", strtotime($row['issueDate'])); ?>
                                    <?php if ($isMC): ?>
                                        ➔ <?php echo date("d M Y", strtotime($row['expiryDate'])); ?>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td style="text-align: center; font-weight: 600; color: #4a5568;"><?php echo $durationText; ?></td>
                            <td class="timestamp-cell"><i class="fa-regular fa-calendar-days" style="color: var(--header-bg); margin-right: 3px;"></i> <?php echo date("d M Y, h:i A", strtotime($row['createdAt'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center; padding: 40px; color: #718096;">No medical logs or issuance archives found under your practitioner signature.</td></tr>
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

    // ─── 🟢 FIX MUKTAMAD: Fungsi Penapisan Jenis Tab (MC / Time Slip) yang Berfungsi Sepenuhnya ───
    function filterTableType(type, btn) {
        // Tukar warna kelas aktif pada elemen butang tab
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        // Ambil baris rekod tatasusunan data dengan kelas .doc-row
        const rows = document.querySelectorAll('.doc-row');
        rows.forEach(row => {
            if (type === 'ALL') {
                row.style.display = "";
            } else {
                // Memadankan nilai data-type atribut baris
                row.style.display = (row.getAttribute('data-type') === type) ? "" : "none";
            }
        });
    }

    // Fungsi carian Patient Name (Real-Time Search Engine)
    function filterTable() {
        const input = document.getElementById("searchInput");
        const filter = input.value.toUpperCase();
        const table = document.getElementById("docTable");
        const tr = table.getElementsByTagName("tr");

        // Set semula tab aktif ke 'ALL' setiap kali carian teks menaip untuk mengelakkan kekeliruan indeks baris
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelector('.tabs-container .tab-btn').classList.add('active');

        for (let i = 1; i < tr.length; i++) {
            const td = tr[i].getElementsByTagName("td")[1]; // Menggunakan Index 1 (Kolum Patient Name selepas Doc ID)
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