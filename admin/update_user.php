<?php
session_start();
// Sekatan Keselamatan: Hanya benarkan Admin mengakses halaman ini
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/login.php");
    exit();
}
require '../db_connect.php'; 

// 1. SEMAK PARAMETER ID DARIPADA URL
if (!isset($_GET['id'])) {
    header("Location: user_management.php");
    exit();
}

$id = intval($_GET['id']);

// 2. AMBIL DATA SEMASA PENGGUNA TERSEBUT
$sql = "SELECT * FROM users WHERE userID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("User not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit System User - SEAL</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --sidebar-width: 260px;
            --header-bg: #2b7a9e;
            --main-bg: linear-gradient(to bottom, #caf0f8, #90e0ef, #48cae4);
            --dark-blue: #183055;
            --success-green: #28a745;
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

        /* ====== CONTAINER & FORM CARD ====== */
        .container { width: 95%; max-width: 750px; margin: 40px auto; padding: 0 20px; box-sizing: border-box; }
        
        .form-card {
            background: white;
            border-radius: 15px;
            padding: 35px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            animation: fadeIn 0.4s ease;
        }

        .form-card h2 { 
            margin-top: 0; 
            color: var(--dark-blue); 
            font-size: 22px; 
            border-bottom: 2px solid #f0f4f8; 
            padding-bottom: 15px; 
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

        /* ====== FORM CONTROLS ====== */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .full-width-group { grid-column: span 2; }

        .form-group label {
            font-size: 13px;
            font-weight: 600;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
        }

        .input-wrapper i {
            position: absolute;
            left: 15px;
            color: #a0aec0;
            font-size: 16px;
            z-index: 5;
        }

        .input-wrapper input, .input-wrapper select {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 14px;
            outline: none;
            box-sizing: border-box;
            transition: all 0.2s ease;
            color: #334155;
            background: #ffffff;
        }

        .input-wrapper input:focus, .input-wrapper select:focus {
            border-color: var(--header-bg);
            box-shadow: 0 0 0 3px rgba(43, 122, 158, 0.15);
        }

        #mmc_container { display: <?php echo (strtolower($user['role']) == 'doctor') ? 'flex' : 'none'; ?>; }

        /* ====== BUTTON ACTIONS ====== */
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            border-top: 1px solid #f0f4f8;
            padding-top: 20px;
            grid-column: span 2;
        }

        .btn {
            flex: 1;
            padding: 13px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-save {
            background-color: var(--success-green);
            color: white;
            box-shadow: 0 4px 6px rgba(40, 167, 69, 0.2);
        }
        .btn-save:hover { background-color: #218838; transform: translateY(-1px); }

        .btn-cancel {
            background-color: #e2e8f0;
            color: #475569;
        }
        .btn-cancel:hover { background-color: #cbd5e1; }

        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .full-width-group, .btn-group { grid-column: span 1; }
        }
    </style>
</head>
<body>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header"><i class="fa-solid fa-user-shield"></i> <span>SEAL</span></div>
    <ul class="sidebar-menu">
        <li><a href="home_admin.php"><i class="fa-solid fa-house"></i> Dashboard</a></li>
        <li><a href="user_management.php" class="active"><i class="fa-solid fa-users-gear"></i> User Management</a></li>
        <li><a href="document_monitoring.php"><i class="fa-solid fa-file-shield"></i> Doc Monitoring</a></li>
        <li><a href="audit_logs.php"><i class="fa-solid fa-clipboard-list"></i> Audit Logs</a></li>
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
        <div class="form-card">
            <h2><i class="fa-solid fa-user-pen" style="color: var(--header-bg);"></i> Modify System User</h2>

            <form action="updateuser_process.php" method="POST" id="updateUserForm">
                <input type="hidden" name="userID" value="<?php echo $user['userID']; ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Full Name</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-user"></i>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Username (Email)</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-envelope"></i>
                            <input type="email" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Phone Number</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-phone"></i>
                            <input type="text" name="phone_number" value="<?php echo htmlspecialchars($user['phone_number']); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Staff ID Number</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-address-card"></i>
                            <input type="text" name="staff_number" value="<?php echo htmlspecialchars($user['staff_number']); ?>" oninput="this.value = this.value.toUpperCase()" required>
                        </div>
                    </div>

                    <div class="full-width-group form-group">
                        <label>System Role Permission</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-user-gear"></i>
                            <select name="role" id="roleSelect" required onchange="toggleMMC()">
                                <option value="doctor" <?php if(strtolower($user['role']) == 'doctor') echo 'selected'; ?>>Doctor</option>
                                <option value="verifier" <?php if(strtolower($user['role']) == 'verifier') echo 'selected'; ?>>Verifier</option>
                                <option value="admin" <?php if(strtolower($user['role']) == 'admin') echo 'selected'; ?>>Administrator</option>
                            </select>
                        </div>
                    </div>

                    <div id="mmc_container" class="full-width-group form-group">
                        <label>MMC Registration Number (Required for Doctors)</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-file-medical"></i>
                            <input type="text" name="mmc_number" id="mmc_input" value="<?php echo htmlspecialchars($user['mmc_number'] ?? ''); ?>" 
                            placeholder="Enter official 7-digit MMC Register Code" maxlength="100" <?php if(strtolower($user['role']) == 'doctor') echo 'required'; ?>>
                        </div>
                    </div>

                    <div class="btn-group">
                        <a href="user_management.php" class="btn btn-cancel"><i class="fa-solid fa-arrow-left"></i> Cancel</a>
                        <button type="submit" class="btn btn-save"><i class="fa-solid fa-floppy-disk"></i> Save Account Changes</button>
                    </div>
                </div>
            </form>
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

    function toggleMMC() {
        var role = document.getElementById("roleSelect").value.toLowerCase();
        var mmcContainer = document.getElementById("mmc_container");
        var mmcInput = document.getElementById("mmc_input");

        if (role === "doctor") {
            mmcContainer.style.display = "flex";
            mmcInput.setAttribute("required", "required");
        } else {
            mmcContainer.style.display = "none";
            mmcInput.removeAttribute("required");
            mmcInput.value = ""; 
        }
    }

    // Intersep Borang Dengan Animasi Konfirmasi SweetAlert2
    document.getElementById('updateUserForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        Swal.fire({
            title: 'Commit Changes?',
            text: "Are you sure you want to update this system user's configurations and roles?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6e7881',
            confirmButtonText: 'Yes, save changes!',
            borderRadius: '12px'
        }).then((result) => {
            if (result.isConfirmed) {
                this.submit();
            }
        });
    });
</script>

</body>
</html>