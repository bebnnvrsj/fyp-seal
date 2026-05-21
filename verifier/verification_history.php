<?php
date_default_timezone_set('Asia/Kuala_Lumpur'); 
session_start();

// 1. KESELAMATAN: Pastikan hanya Verifier boleh akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'verifier') {
    header("Location: ../login/login.php");
    exit();
}
require '../db_connect.php'; 

// 2. AMBIL DATA CARIAN & FILTER
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : '';

// 3. LOGIK SORTING DINAMIK
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'verificationDate';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

$allowed_columns = ['documentID', 'patientName', 'verificationDate', 'verificationStatus'];
if (!in_array($sort_column, $allowed_columns)) { $sort_column = 'verificationDate'; }
$sort_order = ($sort_order === 'ASC') ? 'ASC' : 'DESC';

$verifierID = $_SESSION['userID'];

// =========================================================================
// 4. BINA QUERY SEJARAH (MUKTAMAD: UNION SUBQUERY ANTI-COLLISION)
// =========================================================================
$sql = "SELECT main_data.* FROM (
            SELECT 
                v.verificationID, 
                v.documentID, 
                v.verificationStatus, 
                v.verificationDate, 
                v.verifierID,
                COALESCE(doc_info.dHash, 'N/A') AS documentHash,
                COALESCE(doc_info.pName, 'Unknown Patient') AS patientName,
                COALESCE(doc_info.dType, 'UNKNOWN') AS derived_type
            FROM verificationlog v
            LEFT JOIN (
                SELECT mcID AS realID, patientName AS pName, 'MC' AS dType, documentHash AS dHash FROM mc
                UNION ALL
                SELECT slipID AS realID, patientName AS pName, 'TIMESLIP' AS dType, documentHash AS dHash FROM timeslip
            ) AS doc_info ON v.documentID = doc_info.realID
        ) AS main_data
        WHERE main_data.verifierID = ? ";

$params = [$verifierID];
$types = "i";

if (!empty($search)) {
    $sql .= " AND (main_data.documentID LIKE ? OR main_data.patientName LIKE ? OR main_data.documentHash LIKE ?)";    
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($filter_date)) {
    $sql .= " AND DATE(main_data.verificationDate) = ?";
    array_push($params, $filter_date);
    $types .= "s";
}

$sql .= " ORDER BY main_data.$sort_column $sort_order";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$total_count = $result->num_rows; 

function getSortLink($col, $db_field, $current_sort, $current_order) {
    $new_order = ($current_sort == $db_field && $current_order == 'ASC') ? 'DESC' : 'ASC';
    $icon = ($current_sort == $db_field) ? ($current_order == 'ASC' ? ' <i class="fa-solid fa-sort-up"></i>' : ' <i class="fa-solid fa-sort-down"></i>') : ' <i class="fa-solid fa-sort" style="opacity:0.3"></i>';
    return "<a href='?sort=$db_field&order=$new_order&search=" . urlencode($_GET['search'] ?? '') . "&filter_date=" . urlencode($_GET['filter_date'] ?? '') . "' style='text-decoration:none; color:inherit;'>$col $icon</a>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verification History - SEAL</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --sidebar-width: 260px;
            --header-bg: #2b7a9e;
            --main-bg: linear-gradient(to bottom, #caf0f8, #90e0ef, #48cae4);
            --dark-blue: #183055;
            --status-success: #28a745;
            --status-tampered: #dc3545;
            --status-notfound: #fd7e14;
        }

        body { margin: 0; font-family: "Segoe UI", sans-serif; background: var(--main-bg); display: flex; min-height: 100vh;}
        .sidebar { width: var(--sidebar-width); height: 100vh; background-color: var(--dark-blue); color: white; position: fixed; left: 0; top: 0; transition: transform 0.3s ease-in-out; z-index: 1005; display: flex; flex-direction: column; }
        .sidebar.closed { transform: translateX(-100%); }
        .sidebar-header { padding: 20px; background-color: #122542; font-weight: bold; display: flex; align-items: center; gap: 15px; }
        .sidebar-menu { list-style: none; padding: 0; margin: 20px 0; }
        .sidebar-menu li a { padding: 15px 25px; display: flex; align-items: center; gap: 15px; color: #d1d9e6; text-decoration: none; transition: 0.2s; }
        .sidebar-menu li a:hover { background-color: #2b7a9e; color: white; }
        .sidebar-menu li a.active { background-color: #2b7a9e; color: white; border-left: 4px solid #fff; }

        .main-wrapper { flex: 1; display: flex; flex-direction: column; margin-left: var(--sidebar-width); width: 100%; transition: margin-left 0.3s ease; box-sizing: border-box; }
        .main-wrapper.full-width { margin-left: 0 !important; }
        
        .header { height: 56px; background-color: var(--header-bg); display: flex; align-items: center; padding: 0 24px; color: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .toggle-btn { cursor: pointer; font-size: 20px; margin-right: 15px; }

        .container { width: 95%; max-width: 1500px; margin: 30px auto; display: flex; flex-direction: column; gap: 20px; }
        .page-hero { background: white; border-radius: 15px; padding: 30px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .filter-section { background: white; border-radius: 15px; padding: 20px; display: flex; gap: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .input-box { flex: 1; display: flex; align-items: center; background: #f8f9fa; border: 1px solid #ddd; border-radius: 10px; padding: 10px; }
        .input-box input { border: none; background: transparent; width: 100%; outline: none; margin-left: 10px; font-size: 14px; }

        .history-card { background: white; border-radius: 15px; overflow-x: auto; box-shadow: 0 4px 15px rgba(0,0,0,0.08); padding: 15px; }
        table { width: 100%; border-collapse: collapse; min-width: 600px; table-layout: fixed; }
        th { padding: 15px; text-align: left; background: #f8f9fa; border-bottom: 2px solid #dee2e6; font-size: 14px; }
        td { padding: 15px; border-bottom: 1px solid #eee; font-size: 14px; color: #333; word-wrap: break-word; }
        tr:hover { background-color: #f1f8ff; }

        .badge { padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; display: inline-block; }
        .status-authentic { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; } 
        .status-invalid { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        .status-notfound { background: #fff3e0; color: #e65100; border: 1px solid #ffe0b2; }

        .hash-text { font-family: 'Courier New', monospace; font-size: 11px; color: #4a5568; background: #f8f9fa; padding: 4px 8px; border-radius: 4px; border: 1px solid #e2e8f0; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .hash-text:hover { white-space: normal; word-break: break-all; }
    </style>
</head>
<body>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header"><i class="fa-solid fa-hospital-user"></i> <span>SEAL</span></div>
    <ul class="sidebar-menu">
        <li><a href="home_verifier.php"><i class="fa-solid fa-house"></i> Home</a></li>
        <li><a href="verify_document.php"><i class="fa-solid fa-magnifying-glass"></i> Verify Document</a></li>       
        <li><a href="verification_history.php" class="active"><i class="fa-solid fa-clock-rotate-left"></i> Verification History</a></li>
        <li><a href="../profile.php"><i class="fa-solid fa-user-gear"></i> Profile Settings</a></li>
        <li><a href="../login/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
    </ul>
</div>

<div class="main-wrapper" id="mainWrapper">
    <div class="header">
        <div style="display: flex; align-items: center;">
            <i class="fa-solid fa-bars toggle-btn" onclick="toggleSidebar()"></i>
            <span style="font-weight: 600; margin-left: 15px;">Verifier System Logs</span>
        </div>
    </div>

    <div class="container">
        <div class="page-hero">
            <div>
                <h1 style="margin:0; color:var(--dark-blue);">Verification Audit Logs</h1>
                <p style="color:#666; margin:5px 0 0;">Reviewing verified medical document integrity.</p>
            </div>
            <div style="text-align:right;">
                <span style="font-size: 12px; color:#888;">TOTAL LOGS</span>
                <h2 style="margin:0; color: var(--dark-blue);"><?php echo $total_count; ?></h2>
            </div>
        </div>

        <form method="GET" class="filter-section">
            <div class="input-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="searchInput" name="search" onkeyup="filterTable()" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search ID, Name or Hash...">
            </div>
            <div class="input-box"><i class="fa-solid fa-calendar"></i><input type="date" name="filter_date" value="<?php echo $filter_date; ?>"></div>
            <button type="submit" style="background:var(--header-bg); color:white; border:none; padding:10px 25px; border-radius:10px; cursor:pointer; font-weight:600;">Apply Filter</button>
        </form>

        <div class="history-card">
            <table id="docTable">
                <thead>
                    <tr>
                        <th style="width: 15%;"><?php echo getSortLink('Document ID', 'documentID', $sort_column, $sort_order); ?></th>
                        <th style="width: 20%;"><?php echo getSortLink('Patient Name', 'patientName', $sort_column, $sort_order); ?></th>
                        <th style="width: 33%;">Secure Document ID</th>
                        <th style="width: 17%;"><?php echo getSortLink('Verification Date', 'verificationDate', $sort_column, $sort_order); ?></th>
                        <th style="width: 15%; text-align:center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): 
                            $status = strtoupper(trim($row['verificationStatus'] ?? ''));
                            $derivedType = $row['derived_type'];
                            $rawID = intval($row['documentID']);
                            $docHash = $row['documentHash'];

                            if ($status === 'VALID' || $status === 'AUTHENTIC') {
                                $statusClass = 'status-authentic';
                                $statusDisplay = 'REGISTERED';
                                
                                $prefix = ($derivedType === 'TIMESLIP') ? 'TSUTHM' : 'MCUTHM';
                                $formattedID = $prefix . str_pad($rawID, 6, "0", STR_PAD_LEFT);
                                $hashDisplay = "<span class='hash-text'>🔑 " . htmlspecialchars($docHash) . "</span>";
                            } elseif ($status === 'NOT FOUND') {
                                $statusClass = 'status-notfound';
                                $statusDisplay = 'NO RECORD FOUND';
                                $formattedID = "<span style='color:#e65100; font-weight:600;'>UNREGISTERED</span>";
                                $hashDisplay = "<span style='color:#a0aec0; font-style:italic;'>NONE (FAIL STATE)</span>";
                            } else {
                                $statusClass = 'status-invalid';
                                $statusDisplay = 'TAMPERED';
                                
                                // JIKA TAMPERED DAN ID BERJAYA DIKESAN, TUNJUK ID ASAL DAN HASH DB ASAL UNTUK FORENSIK VIVA
                                if ($rawID > 0) {
                                    $prefix = ($derivedType === 'TIMESLIP') ? 'TSUTHM' : 'MCUTHM';
                                    $formattedID = $prefix . str_pad($rawID, 6, "0", STR_PAD_LEFT);
                                    $hashDisplay = "<span class='hash-text' style='color:#c62828; background:#fff5f5;'>⚠️ " . htmlspecialchars($docHash) . "</span>";
                                } else {
                                    $formattedID = "<span style='color:#dc3545; font-weight:600;'>-</span>";
                                    $hashDisplay = "<span style='color:#a0aec0; font-style:italic;'>UNRESOLVED HASH</span>";
                                }
                            }
                        ?>
                        <tr>
                            <td><strong style="color: var(--dark-blue);"><?php echo $formattedID; ?></strong></td>
                            <td><strong><?php echo htmlspecialchars($row['patientName']); ?></strong></td>
                            <td><?php echo $hashDisplay; ?></td>
                            <td><?php echo date('d M Y, h:i A', strtotime($row['verificationDate'])); ?></td>
                            <td style="text-align:center;">
                                <span class="badge <?php echo $statusClass; ?>">
                                    <?php echo $statusDisplay; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center; padding:30px; color: #666;">No audit records found.</td></tr>
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
        if (sidebar && mainWrapper) {
            sidebar.classList.toggle('closed');
            mainWrapper.classList.toggle('full-width');
        }
    }

    function filterTable() {
        const input = document.getElementById("searchInput");
        const filter = input.value.toUpperCase();
        const table = document.getElementById("docTable");
        const tr = table.getElementsByTagName("tr");

        for (let i = 1; i < tr.length; i++) {
            const tdID = tr[i].getElementsByTagName("td")[0];   
            const tdName = tr[i].getElementsByTagName("td")[1]; 
            const tdHash = tr[i].getElementsByTagName("td")[2]; 
            
            if (tdID || tdName || tdHash) {
                const txtValueID = tdID.textContent || tdID.innerText;
                const txtValueName = tdName.textContent || tdName.innerText;
                const txtValueHash = tdHash.textContent || tdHash.innerText;
                
                if (txtValueID.toUpperCase().indexOf(filter) > -1 || 
                    txtValueName.toUpperCase().indexOf(filter) > -1 ||
                    txtValueHash.toUpperCase().indexOf(filter) > -1) {
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