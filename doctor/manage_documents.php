<?php
date_default_timezone_set('Asia/Kuala_Lumpur');
session_start();

// Paparkan ralat untuk tujuan debugging (buang selepas siap)
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}
require '../db_connect.php'; 

// Gunakan staff_number mengikut session login anda
$doctorID = $_SESSION['userID'];
$doctor_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Doctor';

// Susunan kolum yang dibenarkan
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'issueDate';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';
$allowed_columns = ['documentID', 'patientName', 'documentType', 'issueDate', 'status'];
if (!in_array($sort_column, $allowed_columns)) { $sort_column = 'issueDate'; }
$sort_order = ($sort_order === 'ASC') ? 'ASC' : 'DESC';

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

function getDisplayStatus($status, $expiryDate) {
    if (strtoupper($status) == 'REVOKED') return 'Revoked';
    
    $today = strtotime(date("Y-m-d"));
    $expiry = strtotime($expiryDate);
    
    if ($expiry < $today) return 'Expired';
    
    return ucfirst(strtolower($status)); 
}
?>

<?php if (isset($_GET['msg']) && $_GET['msg'] == 'revoked'): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Document Revoked',
        text: 'The document status has been updated to Revoked and recorded in the audit log.',
        confirmButtonColor: '#d9534f'
    });
</script>
<?php endif; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Documents - SEAL</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

        .main-wrapper { flex: 1; display: flex; flex-direction: column; margin-left: var(--sidebar-width); transition: margin-left 0.3s ease; width: 100%; }
        .main-wrapper.full-width { margin-left: 0; }

        .header { height: 56px; background-color: var(--header-bg); display: flex; align-items: center; justify-content: space-between; padding: 0 24px; color: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .toggle-btn { cursor: pointer; font-size: 20px; }

        .container { width: 95%; max-width: 1600px; margin: 30px auto; padding: 0 20px; display: flex; flex-direction: column; gap: 25px; }

        .page-hero {
            background: white; border-radius: 15px; padding: 35px;
            display: flex; align-items: center; gap: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .hero-text h1 { margin: 0; color: var(--dark-blue); font-size: 28px; }
        .hero-text p { margin: 5px 0; color: #666; font-size: 15px; }

        .manage-card { background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.08); padding: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { background-color: #f8f9fa; color: #555; text-align: left; padding: 15px; border-bottom: 2px solid #dee2e6; font-weight: 600; }
        td { padding: 15px; border-bottom: 1px solid #eee; font-size: 14px; color: #333; }
        tr:hover { background-color: #f1f8ff; }

        .badge { padding: 3px 8px; border-radius: 5px; font-size: 11px; font-weight: bold; display: inline-block; margin: 2px 0; }
        .bg-green { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }

        .action-container { display: flex; gap: 15px; justify-content: center; }
        .action-item { text-align: center; color: var(--header-bg); text-decoration: none; font-size: 11px; font-weight: 600; transition: 0.2s; }
        .action-item i { font-size: 18px; display: block; margin-bottom: 3px; }
        .action-item:hover { color: var(--dark-blue); transform: scale(1.1); }
        .action-item.revoke { color: #d9534f; }

        th a {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
        }

        th:hover {
            background-color: #eef2f7;
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
            <div style="font-size: 50px; color: var(--header-bg);"><i class="fa-solid fa-file-shield"></i></div>
            <div class="hero-text">
                <h1>Document Management</h1>
                <p>Welcome, Dr. <?php echo htmlspecialchars($doctor_name); ?>. Manage previously issued digital medical records.</p>
            </div>
        </div>

        <div class="manage-card">
            <table>
                <thead>
                    <tr>
                        <?php 
                        // Fungsi pembantu untuk tukar ASC/DESC apabila diklik
                        function getSortLink($col, $current_sort, $current_order) {
                            $new_order = ($current_sort == $col && $current_order == 'ASC') ? 'DESC' : 'ASC';
                            $icon = ($current_sort == $col) ? ($current_order == 'ASC' ? ' <i class="fa-solid fa-sort-up"></i>' : ' <i class="fa-solid fa-sort-down"></i>') : ' <i class="fa-solid fa-sort" style="opacity:0.3"></i>';
                            return "<a href='?sort=$col&order=$new_order' style='text-decoration:none; color:inherit;'>$icon ";                        }
                        ?>
                        <th><?php echo getSortLink('documentID', $sort_column, $sort_order); ?>Doc ID</a></th>
                        <th><?php echo getSortLink('patientName', $sort_column, $sort_order); ?>Patient Name</a></th>
                        <th><?php echo getSortLink('documentType', $sort_column, $sort_order); ?>Type</a></th>
                        <th><?php echo getSortLink('issueDate', $sort_column, $sort_order); ?>Date Issued</a></th>
                        <th>Status</th> <th style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): 
                            // Panggil fungsi status
                            $status = getDisplayStatus($row['status'], $row['expiryDate']);
                            
                            $statusColor = '#d9534f'; 
                            if (strtolower($status) == 'active' || strtolower($status) == 'signed') {
                                $statusColor = '#1a7f4e'; 
                            }
                        ?>
                        <tr>
                            <td>
                                <strong style="color:var(--dark-blue);">
                                    <?php 
                                        // Selaraskan format dengan view_doc.php (Prefix + 6 digit angka)
                                        $prefix = ($row['documentType'] === 'mc' ? 'MCUTHM' : 'TSUTHM');
                                        echo $prefix . str_pad($row['documentID'], 6, "0", STR_PAD_LEFT); 
                                    ?>
                                </strong><br>
                                <span class="badge bg-green"><i class="fa-solid fa-link"></i> Blockchained</span>
                            </td>
                            <td><strong><?php echo htmlspecialchars($row['patientName']); ?></strong></td>
                            <td><span style="text-transform: uppercase; font-weight: bold;"><?php echo $row['documentType']; ?></span></td>
                            <td>
                                <span style="color:#666; font-size:13px;">
                                    <?php echo date("d M Y", strtotime($row['issueDate'])); ?>
                                    <?php if($row['documentType'] == 'mc'): ?>
                                        - <?php echo date("d M Y", strtotime($row['expiryDate'])); ?>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-weight:700; color:<?php echo $statusColor; ?>">
                                    ● <?php echo ucfirst($status); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-container">
                                    <a href="#" class="action-item revoke revoke-btn" 
                                        data-id="<?php echo $row['documentID']; ?>" 
                                        data-type="<?php echo $row['documentType']; ?>"  data-patient="<?php echo htmlspecialchars($row['patientName']); ?>">
                                            <i class="fa-solid fa-circle-xmark"></i>Revoke
                                    </a>
                                    <a href="view_doc.php?hash=<?php echo $row['documentHash']; ?>&type=<?php echo $row['documentType']; ?>" class="action-item">
                                        <i class="fa-solid fa-eye"></i>View
                                    </a>
                                </div> 
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center; padding: 30px;">No document record found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.revoke-btn').forEach(button => {
    button.addEventListener('click', function(e) {
        e.preventDefault();
        
        const docID = this.getAttribute('data-id');
        const docType = this.getAttribute('data-type'); // TAMBAH BARIS INI
        const patientName = this.getAttribute('data-patient');

        Swal.fire({
            title: 'Are you sure?',
            text: `Do you want to revoke Document #${docID} for ${patientName}?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d9534f',
            cancelButtonColor: '#6e7881',
            confirmButtonText: 'Yes, Revoke it!'
        }).then((result) => {
            if (result.isConfirmed) {
                // Sekarang docType sudah ada nilai yang betul (mc/timeslip)
                window.location.href = `revoke_doc.php?id=${docID}&type=${docType}`;
            }
        });
    });
});

// Tunjukkan notifikasi kejayaan jika kembali dari revoke_doc.php
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('msg') === 'revoked') {
    Swal.fire({
        icon: 'success',
        title: 'Revoked!',
        text: 'The medical document has been successfully invalidated.',
        timer: 2500,
        showConfirmButton: false
    });
}
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainWrapper = document.getElementById('mainWrapper');
    sidebar.classList.toggle('closed');
    mainWrapper.classList.toggle('full-width');
}
</script>
</body>
</html>