<?php
session_start();
// Only admin users can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/login.php");
    exit();
}
require '../db_connect.php'; 

// Fetch current admin details for the sidebar context
$userID = $_SESSION['userID'];

// FIXED: Query admin name from admin_profiles using relational mappings instead of users table
$admin_sql = "SELECT ap.name, u.role FROM users u JOIN admin_profiles ap ON u.userID = ap.adminID WHERE u.userID = ?";
$stmt = $conn->prepare($admin_sql);
$stmt->bind_param("i", $userID);
$stmt->execute();
$admin_data = $stmt->get_result()->fetch_assoc();

// Count total available systemic assignments across all user levels
$count_sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN LOWER(role) = 'admin' THEN 1 ELSE 0 END) as admins,
                SUM(CASE WHEN LOWER(role) = 'doctor' THEN 1 ELSE 0 END) as doctors,
                SUM(CASE WHEN LOWER(role) = 'verifier' THEN 1 ELSE 0 END) as verifiers
              FROM users";
$count_result = $conn->query($count_sql)->fetch_assoc();

// Count patient registry logs independently
$patient_count_sql = "SELECT COUNT(*) as total_patients FROM patients";
$patient_count_result = $conn->query($patient_count_sql)->fetch_assoc();
$total_patients = $patient_count_result['total_patients'] ?? 0;

// FIXED: Perform a LEFT JOIN across sub-profiles to resolve profile names and staff IDs cleanly
$sql = "SELECT 
            u.userID, 
            u.username, 
            u.role, 
            u.status,
            COALESCE(ap.name, dp.name, vp.name) AS name,
            COALESCE(ap.staff_number, dp.staff_number, vp.staff_number) AS staff_number
        FROM users u
        LEFT JOIN admin_profiles ap ON u.userID = ap.adminID
        LEFT JOIN doctor_profiles dp ON u.userID = dp.doctorID
        LEFT JOIN verifier_profiles vp ON u.userID = vp.verifierID";
$result = $conn->query($sql);

// Fetch patient documentation metrics
$patient_sql = "SELECT patientID, full_name, ic_passport, matric_staff_no, email FROM patients";
$patient_result = $conn->query($patient_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User & Patient Management - SEAL</title>
    
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
            margin: 0; font-family: "Segoe UI", Tahoma, sans-serif;
            background: var(--main-bg); min-height: 100vh; display: flex; overflow-x: hidden;
        }

        /* ====== SIDEBAR UI ====== */
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

        .container { width: 95%; max-width: 100%; margin: 30px auto; padding: 0 40px; box-sizing: border-box; display: flex; flex-direction: column; gap: 25px; }

        /* ====== METRIC DASHBOARD CARDS ====== */
        .role-cards-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; }
        .role-box { background: white; border-radius: 12px; padding: 15px 20px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 10px rgba(0,0,0,0.05); cursor: pointer; transition: all 0.3s ease; border-bottom: 4px solid transparent; }
        .role-box:hover { transform: translateY(-5px); box-shadow: 0 6px 15px rgba(0,0,0,0.1); }
        .role-box.active-filter { background-color: #f1f8ff; transform: translateY(-2px); }
        .role-box-info h3 { margin: 0; font-size: 12px; color: #7f8c8d; text-transform: uppercase; letter-spacing: 0.5px; }
        .role-box-info p { margin: 5px 0 0; font-size: 26px; font-weight: bold; color: var(--dark-blue); }
        .role-box-icon { font-size: 26px; padding: 10px; border-radius: 10px; }

        .box-patient { border-bottom-color: #28a745; }
        .box-patient .role-box-icon { background: #e8f5e9; color: #28a745; }
        .box-all { border-bottom-color: var(--dark-blue); }
        .box-all .role-box-icon { background: #eaedf2; color: var(--dark-blue); }
        .box-admin { border-bottom-color: #e65100; }
        .box-admin .role-box-icon { background: #fff3e0; color: #e65100; }
        .box-doctor { border-bottom-color: #0d47a1; }
        .box-doctor .role-box-icon { background: #e3f2fd; color: #0d47a1; }
        .box-verifier { border-bottom-color: #495057; }
        .box-verifier .role-box-icon { background: #f0f0f0; color: #495057; }

        .actions-bar { display: flex; justify-content: space-between; align-items: center; gap: 20px; }
        .search-wrapper { position: relative; width: 350px; }
        .search-wrapper input { width: 100%; padding: 12px 15px 12px 45px; border-radius: 10px; border: 1px solid #ddd; outline: none; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .search-wrapper i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #888; }

        .management-card { background: #ffffff; border-radius: 15px; padding: 25px; box-shadow: 0px 4px 15px rgba(0,0,0,0.1); width: 100%; overflow-x: auto; }
        .section-title { font-size: 18px; color: var(--dark-blue); margin-top: 0; margin-bottom: 15px; font-weight: 600; display: flex; align-items: center; gap: 10px; }

        table { width: 100%; border-collapse: collapse; table-layout: auto;}
        th { background-color: #f8f9fa; color: #555; text-align: left; padding: 15px; border-bottom: 2px solid #dee2e6; font-weight: 600; }
        td { padding: 15px; border-bottom: 1px solid #eee; color: #333; }
        tr:hover { background-color: #f1f8ff; }

        .role-badge { padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .role-doctor { background: #e3f2fd; color: #0d47a1; }
        .role-admin { background: #fff3e0; color: #e65100; }
        .role-verifier { background: #f0f0f0; color: #495057; }

        .action-icons { display: flex; gap: 15px; }
        .delete-icon { color: #d9534f; font-size: 18px; }

        .status-badge { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .status-active { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .status-inactive { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }

        @media (max-width: 1400px) { .role-cards-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 768px) { .actions-bar { flex-direction: column; align-items: stretch; } .search-wrapper { width: 100%; } .role-cards-grid { grid-template-columns: 1fr; } td, th { padding: 10px; font-size: 13px; } }
    </style>
</head>
<body>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header"><i class="fa-solid fa-user-shield"></i> <span>SEAL</span></div>
    <ul class="sidebar-menu">
        <li><a href="../admin/home_admin.php"><i class="fa-solid fa-house"></i> Dashboard</a></li>
        <li><a href="../admin/register_patient.php"><i class="fa-solid fa-user-plus"></i> Register Patient</a></li>
        <li><a href="../admin/user_management.php" class="active"><i class="fa-solid fa-users-gear"></i> User Management</a></li>
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
        <div class="header-right" style="padding-right: 20px;">
            <span style="font-weight: 500;"><i class="fa-solid fa-user-circle"></i> Hi, <?= htmlspecialchars($admin_data['name'] ?? 'Admin') ?></span>
        </div>
    </div>

    <div class="container">
        <?php if (isset($_GET['msg'])): ?>
            <div style="background-color: white; padding: 15px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); border-left: 5px solid #2b7a9e; display: flex; align-items: center; gap: 15px;">
                <i class="fa-solid fa-circle-info" style="color:#2b7a9e; font-size:20px;"></i>
                <span style="font-weight:500;">
                    <?php 
                        if($_GET['msg'] == 'created') echo "User successfully created and active in the system.";
                        elseif($_GET['msg'] == 'updated') echo "User details have been updated successfully.";
                        elseif($_GET['msg'] == 'patient_updated') echo "Patient records updated smoothly inside core registries.";
                        elseif($_GET['msg'] == 'self_delete_error') echo "Security Alert: You cannot deactivate your own administrative login path.";
                    ?>
                </span>
            </div>
        <?php endif; ?>

        <div class="role-cards-grid">
            <div class="role-box box-patient" onclick="filterByRole('patient', this)">
                <div class="role-box-info">
                    <h3>Total Patients</h3>
                    <p><?php echo $total_patients; ?></p>
                </div>
                <div class="role-box-icon"><i class="fa-solid fa-hospital-user"></i></div>
            </div>

            <div class="role-box box-all active-filter" onclick="filterByRole('all', this)">
                <div class="role-box-info">
                    <h3>Total System Users</h3>
                    <p><?php echo $count_result['total']; ?></p>
                </div>
                <div class="role-box-icon"><i class="fa-solid fa-users"></i></div>
            </div>

            <div class="role-box box-admin" onclick="filterByRole('admin', this)">
                <div class="role-box-info">
                    <h3>Administrators</h3>
                    <p><?php echo $count_result['admins'] ?? 0; ?></p>
                </div>
                <div class="role-box-icon"><i class="fa-solid fa-user-shield"></i></div>
            </div>

            <div class="role-box box-doctor" onclick="filterByRole('doctor', this)">
                <div class="role-box-info">
                    <h3>Doctors</h3>
                    <p><?php echo $count_result['doctors'] ?? 0; ?></p>
                </div>
                <div class="role-box-icon"><i class="fa-solid fa-user-md"></i></div>
            </div>

            <div class="role-box box-verifier" onclick="filterByRole('verifier', this)">
                <div class="role-box-info">
                    <h3>Verifiers</h3>
                    <p><?php echo $count_result['verifiers'] ?? 0; ?></p>
                </div>
                <div class="role-box-icon"><i class="fa-solid fa-building-shield"></i></div>
            </div>
        </div>

        <div class="actions-bar">
            <div class="search-wrapper">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="userInput" onkeyup="filterTable()" placeholder="Search records...">
            </div>
        </div>

        <div class="management-card" id="systemUsersCard">
            <div class="section-title"><i class="fa-solid fa-users-gear"></i> Internal System Users Registry</div>
            <table id="userTable">
                <thead>
                    <tr>
                        <th>Staff ID</th>
                        <th>Full Name</th>
                        <th>Username (Email)</th>
                        <th>System Role</th>
                        <th>Status</th> 
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ($result && $result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        $status = $row['status'] ?? 'active'; 
                        $statusClass = ($status == 'active') ? 'status-active' : 'status-inactive';
                        $role = strtolower($row['role']);
                        $badgeClass = ($role == 'doctor') ? 'role-doctor' : (($role == 'admin') ? 'role-admin' : 'role-verifier');
                        $displayStaffNo = !empty($row["staff_number"]) ? htmlspecialchars($row["staff_number"]) : "N/A";
                        $displayName = !empty($row["name"]) ? htmlspecialchars($row["name"]) : "<span style='color:#bbb; font-style:italic;'>Profile Pending</span>";

                        echo "<tr data-role='".$role."'>";
                        echo "<td><strong>#" . $displayStaffNo . "</strong></td>";
                        echo "<td>" . $displayName . "</td>";
                        echo "<td>" . htmlspecialchars($row["username"]) . "</td>";
                        echo "<td><span class='role-badge $badgeClass'>" . ucfirst($row["role"]) . "</span></td>";
                        echo "<td><span class='status-badge $statusClass'>" . ucfirst($status) . "</span></td>";
                        echo "<td style='text-align:center;'>
                                <div class='action-icons' style='justify-content:center;'>
                                    <a href='#' 
                                        class='delete-icon toggle-status-btn' 
                                        data-id='".$row["userID"]."' 
                                        data-name='".htmlspecialchars($row["name"] ?? 'User')."' 
                                        data-status='".$status."' 
                                        title='Toggle Active/Inactive'>
                                        <i class='fa-solid fa-power-off'></i>
                                    </a>                                 
                                </div>
                            </td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='6' style='text-align:center; padding: 30px;'>No registered system users discovered.</td></tr>";
                }
                ?>
            </tbody>
            </table>
        </div>

        <div class="management-card" id="patientsCard" style="display: none;">
            <div class="section-title"><i class="fa-solid fa-hospital-user"></i> Registered UTHM Patients Master Database</div>
            <table id="patientTable">
                <thead>
                    <tr>
                        <th>Matric / Staff No</th>
                        <th>Patient Full Name</th>
                        <th>IC / Passport Number</th>
                        <th>Email Address</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($patient_result && $patient_result->num_rows > 0) {
                        while($p_row = $patient_result->fetch_assoc()) {
                            $patientEmail = !empty($p_row["email"]) ? htmlspecialchars($p_row["email"]) : "<span style='color:#aaa; font-style:italic;'>N/A</span>";
                            
                            echo "<tr data-role='patient'>";
                            echo "<td><strong>" . strtoupper(htmlspecialchars($p_row["matric_staff_no"])) . "</strong></td>";
                            echo "<td>" . strtoupper(htmlspecialchars($p_row["full_name"])) . "</td>";
                            echo "<td>" . htmlspecialchars($p_row["ic_passport"]) . "</td>";
                            echo "<td>" . $patientEmail . "</td>";
                            echo "<td style='text-align:center;'>
                                    <div class='action-icons' style='justify-content:center;'>
                                        <a href='edit_patient.php?id=".$p_row["patientID"]."' class='edit-icon' title='Edit Patient'><i class='fa-solid fa-user-pen'></i></a>
                                    </div>
                                </td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' style='text-align:center; padding: 30px;'>No registered patient records discovered.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<script>
    let currentSelectedRole = 'all';

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mainWrapper = document.getElementById('mainWrapper');
        sidebar.classList.toggle('closed');
        mainWrapper.classList.toggle('full-width');
    }

    function filterByRole(role, element) {
        document.querySelectorAll('.role-box').forEach(box => {
            box.classList.remove('active-filter');
        });
        element.classList.add('active-filter');
        
        currentSelectedRole = role.toLowerCase();

        const systemUsersCard = document.getElementById('systemUsersCard');
        const patientsCard = document.getElementById('patientsCard');

        if (currentSelectedRole === 'patient') {
            systemUsersCard.style.display = 'none';
            patientsCard.style.display = 'block';
            document.getElementById("userInput").placeholder = "Search by name, matrix or IC number...";
        } else {
            systemUsersCard.style.display = 'block';
            patientsCard.style.display = 'none';
            document.getElementById("userInput").placeholder = "Search by name or staff ID...";
        }

        applyCombinedFilter();
    }

    function filterTable() {
        applyCombinedFilter();
    }

    function applyCombinedFilter() {
        const searchFilter = document.getElementById("userInput").value.toUpperCase();
        
        if (currentSelectedRole === 'patient') {
            const table = document.getElementById("patientTable");
            const tr = table.getElementsByTagName("tr");
            for (let i = 1; i < tr.length; i++) {
                const tdMatric = tr[i].getElementsByTagName("td")[0];
                const tdName = tr[i].getElementsByTagName("td")[1];
                const tdIC = tr[i].getElementsByTagName("td")[2];
                if (tdMatric || tdName || tdIC) {
                    const txtMatric = tdMatric.textContent || tdMatric.innerText;
                    const txtName = tdName.textContent || tdName.innerText;
                    const txtIC = tdIC.textContent || tdIC.innerText;
                    if (txtMatric.toUpperCase().indexOf(searchFilter) > -1 || 
                        txtName.toUpperCase().indexOf(searchFilter) > -1 ||
                        txtIC.toUpperCase().indexOf(searchFilter) > -1) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }
            }
        } else {
            const table = document.getElementById("userTable");
            const tr = table.getElementsByTagName("tr");
            for (let i = 1; i < tr.length; i++) {
                const rowRole = tr[i].getAttribute('data-role');
                const tdID = tr[i].getElementsByTagName("td")[0];
                const tdName = tr[i].getElementsByTagName("td")[1];
                
                if (tdID || tdName) {
                    const txtValueID = tdID.textContent || tdID.innerText;
                    const txtValueName = tdName.textContent || tdName.innerText;
                    
                    const matchesSearch = (txtValueID.toUpperCase().indexOf(searchFilter) > -1 || txtValueName.toUpperCase().indexOf(searchFilter) > -1);
                    const matchesRole = (currentSelectedRole === 'all' || rowRole === currentSelectedRole);
                    
                    if (matchesSearch && matchesRole) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.toggle-status-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const userId = this.getAttribute('data-id');
                const userName = this.getAttribute('data-name');
                const currentStatus = this.getAttribute('data-status');
                const actionText = (currentStatus === 'active') ? 'Deactivate' : 'Activate';
                const color = (currentStatus === 'active') ? '#d9534f' : '#28a745';

                Swal.fire({
                    title: 'Are you sure?',
                    text: `Do you want to ${actionText} account for ${userName}?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: color,
                    cancelButtonColor: '#6e7881',
                    confirmButtonText: `Yes, ${actionText} it!`,
                    borderRadius: '15px'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = `toggle_status.php?id=${userId}&current=${currentStatus}`;
                    }
                });
            });
        });
    });
</script>
</body>
</html>