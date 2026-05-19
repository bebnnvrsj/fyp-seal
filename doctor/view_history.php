<?php
date_default_timezone_set('Asia/Kuala_Lumpur');

session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}
require '../db_connect.php';

// Fetch current doctor details for the hero and sidebar
$doctorID = $_SESSION['userID'];
$doctor_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Doctor';

function getDisplayStatus($status, $expiryDate) {
    if (strtolower($status) == 'revoked') return 'Revoked';
    // Bandingkan tarikh hari ini (00:00:00) dengan tarikh luput (00:00:00)
    $today = strtotime(date("Y-m-d"));
    $expiry = strtotime($expiryDate);
    
    if ($expiry < $today) return 'Expired';   
        return $status;
}
//combine both table mc and timeslip
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
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, sans-serif;
            background: var(--main-bg);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
        }

        /* ====== SIDEBAR (Consistent with portal) ====== */
        .sidebar { width: var(--sidebar-width); height: 100vh; background-color: var(--dark-blue); color: white; position: fixed; left: 0; top: 0; transition: transform 0.3s ease; z-index: 1000; display: flex; flex-direction: column; }
        .sidebar.closed { transform: translateX(-100%); }
        .sidebar-header { padding: 20px; background-color: #122542; display: flex; align-items: center; gap: 15px; font-weight: bold; }
        .sidebar-menu { list-style: none; padding: 0; margin: 20px 0; }
        .sidebar-menu li a { padding: 15px 25px; display: flex; align-items: center; gap: 15px; color: #d1d9e6; text-decoration: none; transition: 0.2s; }
        .sidebar-menu li a:hover { background-color: #2b7a9e; color: white; }
        .sidebar-menu li a.active { background-color: #2b7a9e; color: white; border-left: 4px solid #fff; }

        /* ====== SUBMENU HOVER ====== */
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

        .page-hero {
            background: white; border-radius: 15px; padding: 35px;
            display: flex; align-items: center; gap: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .hero-text h1 { margin: 0; color: var(--dark-blue); font-size: 28px; }
        .hero-text p { margin: 5px 0; color: #666; font-size: 15px; }

        /* Search Bar Style */
        .search-container { display: flex; justify-content: flex-start; }
        .search-box { position: relative; width: 350px; }
        .search-box input {
            width: 100%; padding: 12px 15px 12px 45px;
            border-radius: 10px; border: 1px solid #ddd; outline: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .search-box i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #888; }

        /* TABLE CARD */
        .table-card { background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.08); padding: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { background-color: #f8f9fa; color: #555; text-align: left; padding: 15px; border-bottom: 2px solid #dee2e6; font-weight: 600; }
        td { padding: 15px; border-bottom: 1px solid #eee; font-size: 14px; color: #333; }
        .patient-link { text-decoration: none; color: var(--dark-blue); font-weight: bold; transition: 0.2s; }
        .patient-link:hover { color: var(--header-bg); text-decoration: underline; }

        /* Badges */
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block;}
        .badge-mc { background-color: #e3f2fd; color: #0d47a1; }
        .badge-ts { background-color: #f3e5f5; color: #7b1fa2; }
        .active { background: #e8f5e9; color: #2e7d32; }
        .expired { background: #ffebee; color: #c62828; }
        .revoked { background: #fff3e0; color: #e65100; }    .btn-view { color: var(--header-bg); text-decoration: none; font-size: 18px; transition: 0.2s; }
        .btn-view:hover { color: var(--dark-blue); transform: scale(1.1); }
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
            <span style="font-weight: 600; margin-left: 15px;">Document Issuance Logs</span>
        </div>
    </div>

    <div class="container">
        <div class="page-hero">
            <div style="font-size: 50px; color: var(--header-bg);"><i class="fa-solid fa-clock-rotate-left"></i></div>
            <div class="hero-text">
                <h1>Issuance History</h1>
                <p>Reviewing all medical documents issued by Dr. <?php echo htmlspecialchars($doctor_name); ?>.</p>
            </div>
        </div>

        <div class="search-container">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Search patient name...">
            </div>
        </div>

        <div class="table-card" style="margin-top: 20px;">
            <table id="docTable">
                <thead>
                    <tr>
                        <th>Patient Name</th>
                        <th>Type</th>
                        <th>Issue Date</th>
                        <th>Expiry/End Date</th>
                        <th style="text-align:center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()):
                            $docType = strtoupper($row['documentType']);
                            $typeClass = ($docType === 'MC') ? 'badge-mc' : 'badge-ts';                           
                            $status = strtolower($row['status']);
                            $statusLabel = getDisplayStatus($status, $row['expiryDate']); 
                            $statusCSS = strtolower($statusLabel);
                        ?>
                        <tr>
                           <td>
                                    <a href="view_document.php?id=<?php echo $row['docID']; ?>&type=<?php echo strtolower($row['documentType']); ?>" class="patient-link">                                    <i class="fa-solid fa-file-medical" style="margin-right: 8px; opacity: 0.5;"></i>
                                    <?php echo htmlspecialchars($row['patientName']); ?>
                                </a>
                            </td> 
                            <td><span class="badge <?php echo $typeClass; ?>"><?php echo $row['documentType']; ?></span></td>                         
                            <td><?php echo date("d M Y", strtotime($row['issueDate'])); ?></td>
                            <td>
                                <?php 
                                if ($row['documentType'] == 'MC') {
                                    echo date("d M Y", strtotime($row['expiryDate']));
                                } else {
                                    // Time-Slip tidak expire, jadi kita tulis N/A atau Single Day
                                    echo '<span style="color:#aaa;">- N/A -</span>'; 
                                }
                                ?>
                            </td>                            
                            <td style="text-align:center;">
                                <span class="badge <?php echo $statusCSS; ?>">
                                    ● <?php echo $statusLabel; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center; padding: 40px; color: #999;">No records found.</td></tr>
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