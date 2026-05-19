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

// Memadankan nama kolum pangkalan data yang sah untuk sorting kalis SQL Injection
$allowed_columns = ['documentID', 'patientName', 'verificationDate', 'verificationStatus'];
if (!in_array($sort_column, $allowed_columns)) { $sort_column = 'verificationDate'; }
$sort_order = ($sort_order === 'ASC') ? 'ASC' : 'DESC';

// Gunakan userID mengikut pemandat data session anda
$verifierID = $_SESSION['userID'];

// 4. BINA QUERY DENGAN GABUNGAN DAN PADANAN FORMAT ID RASMI (Selesai Isu Unknown Name)
$sql = "SELECT v.verificationID, v.documentID, v.verificationStatus, v.verificationDate, 
               ANY_VALUE(d.patientName) AS patientName 
        FROM verificationlog v
        LEFT JOIN (
            SELECT CONCAT('MCUTHM', LPAD(mcID, 6, '0')) AS docID, patientName FROM mc
            UNION ALL
            SELECT CONCAT('TSUTHM', LPAD(slipID, 6, '0')) AS docID, patientName FROM timeslip
        ) d ON (v.documentID = d.docID OR v.documentID = REPLACE(REPLACE(d.docID, 'MCUTHM', ''), 'TSUTHM', ''))
        WHERE v.verifierID = ? ";

$params = [$verifierID];
$types = "i";

if (!empty($search)) {
    $sql .= " AND (v.documentID LIKE ? OR d.patientName LIKE ?)";    
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if (!empty($filter_date)) {
    $sql .= " AND DATE(v.verificationDate) = ?";
    array_push($params, $filter_date);
    $types .= "s";
}

// Gunakan nama lajur alias yang selamat untuk query ordering
if ($sort_column === 'patientName') {
    $sql .= " ORDER BY patientName $sort_order";
} else {
    $sql .= " ORDER BY v.$sort_column $sort_order";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$total_count = $result->num_rows; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verification History - SEAL</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --sidebar-width: 260px;
            --header-bg: #2b7a9e;
            --main-bg: linear-gradient(to bottom, #caf0f8, #90e0ef, #48cae4);
            --dark-blue: #183055;
        }

        body { margin: 0; font-family: "Segoe UI", sans-serif; background: var(--main-bg); display: flex; }

        /* SIDEBAR & WRAPPER */
        .sidebar { width: var(--sidebar-width); height: 100vh; background-color: var(--dark-blue); color: white; position: fixed; left: 0; top: 0; transition: transform 0.3s ease-in-out; z-index: 1005; display: flex; flex-direction: column; }
        .sidebar.closed { transform: translateX(-100%);}
        .sidebar-header { padding: 20px; background-color: #122542; font-weight: bold; display: flex; align-items: center; gap: 15px; }
        .sidebar-menu { list-style: none; padding: 0; margin: 20px 0; }
        .sidebar-menu li a { padding: 15px 25px; display: flex; align-items: center; gap: 15px; color: #d1d9e6; text-decoration: none; transition: 0.2s; }
        .sidebar-menu li a:hover { background-color: #2b7a9e; color: white; }
        .sidebar-menu li a.active { background-color: #2b7a9e; color: white; border-left: 4px solid #fff; }

        /* MAIN WRAPPER */
        .main-wrapper { flex: 1; display: flex; flex-direction: column; margin-left: var(--sidebar-width); transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out; width: 100%; box-sizing: border-box; }
        .main-wrapper.full-width { margin-left: 0 !important; }
        .header { height: 56px; background-color: var(--header-bg); display: flex; align-items: center; padding: 0 24px; color: white; position: relative; z-index: 1000; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .toggle-btn { cursor: pointer; font-size: 20px; }

        .container { width: 95%; max-width: 1400px; margin: 30px auto; display: flex; flex-direction: column; gap: 20px; box-sizing: border-box; }

        /* HERO & FILTER */
        .page-hero { background: white; border-radius: 15px; padding: 30px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .filter-section { background: white; border-radius: 15px; padding: 20px; display: flex; gap: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .input-box { flex: 1; display: flex; align-items: center; background: #f8f9fa; border: 1px solid #ddd; border-radius: 10px; padding: 10px; }
        .input-box input { border: none; background: transparent; width: 100%; outline: none; margin-left: 10px; font-size: 14px; }

        /* TABLE & BADGES */
        .history-card { background: white; border-radius: 15px; overflow-x: auto; box-shadow: 0 4px 15px rgba(0,0,0,0.08); padding: 15px; }
        table { width: 100%; border-collapse: collapse; min-width: 600px; }
        th { padding: 15px; text-align: left; background: #f8f9fa; border-bottom: 2px solid #dee2e6; font-size: 14px; }
        th a { text-decoration: none; color: #555; display: flex; align-items: center; gap: 5px; }
        td { padding: 15px; border-bottom: 1px solid #eee; font-size: 14px; color: #333; }

        .badge { padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; display: inline-block; }
        .status-authentic { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; } 
        .status-revoked { background: #fbeee6; color: #a04000; border: 1px solid #edbb99; }    
        .status-invalid { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }    

        @media (max-width: 1024px) {
            .main-wrapper { margin-left: 0 !important; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.closed { transform: translateX(0); }
        }

        @media (max-width: 768px) { 
            .filter-section { flex-direction: column; gap: 12px; }
            .page-hero { flex-direction: column; text-align: center; gap: 15px; }
        }
    </style>
</head>
<body>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <i class="fa-solid fa-hospital-user"></i>
        <span>SEAL</span>
    </div>
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
        <div class="header-left">
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
            <div class="input-box"><i class="fa-solid fa-magnifying-glass"></i><input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search ID or Name..."></div>
            <div class="input-box"><i class="fa-solid fa-calendar"></i><input type="date" name="filter_date" value="<?php echo $filter_date; ?>"></div>
            <button type="submit" style="background:var(--header-bg); color:white; border:none; padding:10px 25px; border-radius:10px; cursor:pointer; font-weight:600;">Apply</button>
        </form>

        <div class="history-card">
            <table>
                <thead>
                    <tr>
                        <?php 
                        function getSortLink($col, $db_field, $current_sort, $current_order) {
                            $new_order = ($current_sort == $db_field && $current_order == 'ASC') ? 'DESC' : 'ASC';
                            $icon = ($current_sort == $db_field) ? ($current_order == 'ASC' ? ' <i class="fa-solid fa-sort-up"></i>' : ' <i class="fa-solid fa-sort-down"></i>') : ' <i class="fa-solid fa-sort" style="opacity:0.3"></i>';
                            return "<a href='?sort=$db_field&order=$new_order&search=" . urlencode($_GET['search'] ?? '') . "&filter_date=" . urlencode($_GET['filter_date'] ?? '') . "'>$col $icon</a>";
                        }
                        ?>
                        <th><?php echo getSortLink('Document ID', 'documentID', $sort_column, $sort_order); ?></th>
                        <th><?php echo getSortLink('Patient Name', 'patientName', $sort_column, $sort_order); ?></th>
                        <th><?php echo getSortLink('Verification Date', 'verificationDate', $sort_column, $sort_order); ?></th>
                        <th style="text-align:center;"><?php echo getSortLink('Status', 'verificationStatus', $sort_column, $sort_order); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): 
                            $status = strtoupper(trim($row['verificationStatus'] ?? ''));
                            
                            if ($status == 'VALID' || $status == 'AUTHENTIC') {
                                $statusClass = 'status-authentic';
                                $statusDisplay = 'VALID / ACTIVE';
                            } elseif ($status == 'REVOKED') {
                                $statusClass = 'status-revoked';
                                $statusDisplay = 'REVOKED';
                            } else {
                                $statusClass = 'status-invalid';
                                $statusDisplay = 'TAMPERED';
                            }

                            // ====== LOGIK FORMATTING PREFIX ID (UNTUK MENGELAKKAN REKOD MENTAH) ======
                            $rawID = trim($row['documentID'] ?? '');
                            $formattedID = $rawID; // Lalai jika sudah berformat

                            // Jika ID dalam log disimpan sebagai nombor tulen (Contoh: "5" atau "142")
                            if (is_numeric($rawID)) {
                                // Guna semakan silang rekod blockchain untuk tahu jenis asal dokumen
                                $check_hash_sql = "SELECT 'mc' AS origin FROM mc WHERE mcID = ? 
                                                   UNION ALL 
                                                   SELECT 'ts' AS origin FROM timeslip WHERE slipID = ?";
                                $chk_stmt = $conn->prepare($check_hash_sql);
                                $chk_stmt->bind_param("ii", $rawID, $rawID);
                                $chk_stmt->execute();
                                $origin_type = $chk_stmt->get_result()->fetch_assoc()['origin'] ?? 'mc';
                                
                                $prefix = ($origin_type === 'mc') ? "MCUTHM" : "TSUTHM";
                                $formattedID = $prefix . str_pad($rawID, 6, "0", STR_PAD_LEFT);
                            } else {
                                // Jika disimpan dalam bentuk teks tapi huruf kecil/tidak kemas, selaraskan ke huruf besar
                                $formattedID = strtoupper($rawID);
                            }
                        ?>
                        <tr>
                            <td>
                                <strong style="color: var(--dark-blue);">
                                    <?php echo htmlspecialchars($formattedID); ?>
                                </strong>
                            </td>
                            <td><?php echo htmlspecialchars($row['patientName'] ?? 'Unknown'); ?></td>
                            <td><?php echo date('d M Y, h:i A', strtotime($row['verificationDate'])); ?></td>
                            <td style="text-align:center;">
                                <span class="badge <?php echo $statusClass; ?>">
                                    <?php echo $statusDisplay; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center; padding:30px; color: #666;">No audit records found.</td></tr>
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
</script>
</body>
</html>