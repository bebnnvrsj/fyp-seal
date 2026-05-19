<?php
session_start();
// 1. Kawalan Akses: Hanya Admin dibenarkan[cite: 5]
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
require '../db_connect.php';

// 2. Ambil maklumat admin untuk profil (pilihan)
$admin_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Registration - SEAL</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* CSS menyamai struktur create_mc.php */
        :root { --sidebar-width: 260px; --header-bg: #2b7a9e; --main-bg: linear-gradient(to bottom, #caf0f8, #90e0ef, #48cae4); --dark-blue: #183055; }
        body { margin: 0; font-family: "Segoe UI", sans-serif; background: var(--main-bg); min-height: 100vh; display: flex; overflow-x: hidden; }
        
        /* SIDEBAR */
        .sidebar { width: var(--sidebar-width); height: 100vh; background-color: var(--dark-blue); color: white; position: fixed; left: 0; top: 0; transition: transform 0.3s ease; z-index: 1000; display: flex; flex-direction: column; }
        .sidebar.closed { transform: translateX(-100%); }
        .sidebar-header { padding: 20px; background-color: #122542; display: flex; align-items: center; gap: 15px; font-weight: bold; }
        .sidebar-menu { list-style: none; padding: 0; margin: 20px 0; }
        .sidebar-menu li a { padding: 15px 25px; display: flex; align-items: center; gap: 15px; color: #d1d9e6; text-decoration: none; transition: 0.2s; }
        .sidebar-menu li a:hover { background-color: #2b7a9e; color: white; }
        .sidebar-menu li a.active { background-color: #2b7a9e; color: white; border-left: 4px solid #fff; }

        /* MAIN WRAPPER */
        .main-wrapper { flex: 1; display: flex; flex-direction: column; margin-left: var(--sidebar-width); transition: margin-left 0.3s ease; width: 100%; }
        .main-wrapper.full-width { margin-left: 0; }
        .header { height: 56px; background-color: var(--header-bg); display: flex; align-items: center; justify-content: space-between; padding: 0 24px; color: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .toggle-btn { cursor: pointer; font-size: 20px; }
        
        /* FORM CARD */
        .container { width: 95%; max-width: 1600px; margin: 30px auto; padding: 0 20px; display: flex; flex-direction: column; gap: 25px; }
        .mc-form-card { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); width: 100%; max-width: 800px; margin: 0 auto; }
        .form-section-title { font-size: 16px; font-weight: 700; color: var(--dark-blue); margin-bottom: 20px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; display: flex; align-items: center; gap: 10px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 18px; position: relative; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 8px; }
        input { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #ddd; font-size: 14px; box-sizing: border-box; }
        
        .submit-btn { background: var(--header-bg); color: white; border: none; padding: 14px 40px; border-radius: 8px; font-size: 16px; font-weight: 700; cursor: pointer; width: 100%; margin-top: 10px; transition: 0.3s; }
        .submit-btn:hover { background: var(--dark-blue); }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header"><i class="fa-solid fa-user-shield"></i> <span>SEAL</span></div>
    <ul class="sidebar-menu">
        <li><a href="../admin/home_admin.php"><i class="fa-solid fa-house"></i> Dashboard</a></li>
        <li><a href="../admin/register_patient.php" class="active"><i class="fa-solid fa-user-plus"></i> Register Patient</a></li>
        <li><a href="../admin/user_management.php"><i class="fa-solid fa-users-gear"></i> User Management</a></li>
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
    </div>

    <div class="container">
        <div class="mc-form-card">
            <!-- Form mengikut struktur create_mc.php[cite: 6] -->
            <form action="register_patient_process.php" method="POST" id="regForm">
                <div class="form-section-title"><i class="fa-solid fa-user-plus"></i> Patient Registration</div>
                
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" placeholder="E.g. MUHAMMAD ALI BIN AHMAD" 
                           oninput="this.value = this.value.toUpperCase()" required>
                </div>

                <div class="form-grid">     
                    <div class="form-group">
                        <label>NRIC / Passport</label>
                        <input type="text" name="ic_passport" placeholder="Without '-'" required>
                    </div>

                    <div class="form-group">
                        <label>Matric / Staff Number</label>
                        <input type="text" name="matric_staff_no" placeholder="E.g. AI2100XX" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="patient@example.com">
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fa-solid fa-database"></i> Register Patient Data
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// Fungsi sidebar[cite: 6]
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainWrapper = document.getElementById('mainWrapper');
    sidebar.classList.toggle('closed');
    mainWrapper.classList.toggle('full-width');
}

// SweetAlert untuk maklum balas URL[cite: 5]
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('status') === 'success') {
    Swal.fire({ icon: 'success', title: 'Registered!', text: 'Patient data is now available for doctors.', confirmButtonColor: '#2b7a9e' });
} else if (urlParams.get('status') === 'exists') {
    Swal.fire({ icon: 'warning', title: 'Duplicate Entry', text: 'This IC or Matric Number is already registered.', confirmButtonColor: '#183055' });
} else if (urlParams.get('status') === 'failed') {
    Swal.fire({ icon: 'error', title: 'Registration Failed', text: 'Database error occurred.', confirmButtonColor: '#b91c1c' });
}

// Loading effect semasa menghantar borang[cite: 6]
document.getElementById('regForm').onsubmit = function() {
    Swal.fire({
        title: 'Registering Patient...',
        text: 'Adding record to SEAL central database.',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
};
</script>
</body>
</html>