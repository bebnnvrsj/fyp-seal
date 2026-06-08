<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login/login.php");
    exit();
}
require 'db_connect.php'; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New User - SEAL</title>
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

        /* ====== CONTAINER ====== */
        .container { 
            width: 95%; 
            max-width: 1200px; 
            margin: 30px auto; 
            padding: 0 20px; 
            display: flex; 
            flex-direction: column; 
            gap: 25px; 
        }

        /* Page Hero Header */
        .page-hero {
            background: white;
            border-radius: 15px;
            padding: 25px 35px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .hero-info h1 { margin: 0; color: var(--dark-blue); font-size: 24px; }
        .hero-info p { margin: 5px 0 0; color: #666; font-size: 14px; }

        /* Form Card Styling */
        .form-card { 
            background: #ffffff; 
            border-radius: 15px; 
            padding: 40px; 
            box-shadow: 0px 4px 15px rgba(0,0,0,0.1); 
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            text-align: left;
        }

        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 8px; }

        .password-wrapper { position: relative; width: 100%; }
        .password-wrapper i {
            position: absolute; right: 15px; top: 50%;
            transform: translateY(-50%); cursor: pointer; color: #888;
        }

        input, select {
            width: 100%; padding: 12px;
            border-radius: 10px; border: 1px solid #ddd; font-size: 14px; box-sizing: border-box;
            background: #fcfcfc;
        }

        input:focus, select:focus { outline: none; border-color: var(--header-bg); background: #fff; }

        .add-btn {
            background-color: var(--header-bg); color: white; border: none;
            padding: 14px 0; width: 100%; font-size: 16px;
            border-radius: 8px; cursor: pointer; font-weight: 600; margin-top: 20px;
            transition: 0.3s;
        }
        .add-btn:hover { background-color: var(--dark-blue); transform: translateY(-2px); }

        #mmc_container { grid-column: span 2; display: none; }
        .full-width-group { grid-column: span 2; }

        .input-error { border: 2px solid #ff4d4d !important; background-color: #fff2f2 !important; }
        .input-success { border: 2px solid #2ecc71 !important; background-color: #f2fff6 !important; }

        #match-message {
            font-size: 12px; margin-top: 5px; font-weight: bold; display: block; min-height: 15px;
        }

        @media (max-width: 850px) {
            .form-grid { grid-template-columns: 1fr; }
            #mmc_container, .full-width-group { grid-column: span 1; }
        }
    </style>
</head>
<body>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header"><i class="fa-solid fa-user-shield"></i> <span>SEAL</span></div>
    <ul class="sidebar-menu">
        <li><a href="admin/home_admin.php"><i class="fa-solid fa-house"></i> Dashboard</a></li>
        <li><a href="admin/user_management.php" class="active"><i class="fa-solid fa-users-gear"></i> User Management</a></li>
        <li><a href="admin/document_monitoring.php"><i class="fa-solid fa-file-shield"></i> Doc Monitoring</a></li>
        <li><a href="admin/audit_logs.php"><i class="fa-solid fa-clipboard-list"></i> Audit Logs</a></li>
        <li><a href="admin/activity_reports.php"><i class="fa-solid fa-chart-pie"></i> Activity Reports</a></li>
        <li><a href="profile.php"><i class="fa-solid fa-user-gear"></i> Profile Settings</a></li>
        <li><a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
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
        <?php if (isset($_GET['error'])): ?>
            <div style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 10px; border: 1px solid #f5c6cb; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span>
                    <?php 
                        if($_GET['error'] == 'duplicate') echo "Error: Username or Staff ID already exists.";
                        else echo "An error occurred. Please try again.";
                    ?>
                </span>
            </div>
        <?php endif; ?>

        <div class="page-hero">
            <div class="hero-info">
                <h1><i class="fa-solid fa-user-plus"></i> Add New User</h1>
                <p>Register a new Doctor, Verifier, or Administrator into the SEAL system.</p>
            </div>
            <a href="admin/user_management.php" style="color: var(--header-bg); text-decoration: none; font-weight: 600;">
                <i class="fa-solid fa-arrow-left"></i> Back to List
            </a>
        </div>

        <div class="form-card">
            <form action="adduser_success.php" method="POST" onsubmit="return validatePasswords()">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" placeholder="Enter Full Name" required>
                    </div>
                    <div class="form-group">
                        <label>Email (Username)</label>
                        <input type="email" name="username" placeholder="Enter Email Address" required>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="password" placeholder="Create Password" required onkeyup="checkMatch()">
                            <i class="fa-solid fa-eye" id="togglePassword"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="confirm_password" id="confirm_password" placeholder="Re-enter Password" required onkeyup="checkMatch()">
                            <i class="fa-solid fa-eye" id="toggleConfirmPassword"></i>
                        </div>
                        <span id="match-message"></span>
                    </div>

                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone_number" placeholder="e.g. 0123456789" required>
                    </div>
                    <div class="form-group">
                        <label>Staff Number</label>
                        <input type="text" name="staff_number" placeholder="Enter Staff ID" required>
                    </div>

                    <div class="full-width-group form-group">
                        <label>System Role</label>
                        <select name="role" id="roleSelect" required onchange="toggleMMC()">
                            <option value="" disabled selected>Select a system role...</option>
                            <option value="doctor">Doctor</option>
                            <option value="verifier">Verifier</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>

                    <div id="mmc_container" class="form-group">
                        <label>MMC Number (Required for Doctors)</label>
                        <input type="text" name="mmc_number" id="mmc_input" placeholder="Enter 7-digit MMC Number" maxlength="7">        
                    </div>
                </div>

                <button type="submit" class="add-btn">Create User Account</button>
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
    var role = document.getElementById("roleSelect").value;
    var mmcContainer = document.getElementById("mmc_container");
    var mmcInput = document.getElementById("mmc_input");

    if (role === "doctor") {
        mmcContainer.style.display = "block";
        mmcInput.setAttribute("required", "required");
    } else {
        mmcContainer.style.display = "none";
        mmcInput.removeAttribute("required");
        mmcInput.value = "";
    }
}

function checkMatch() {
    const password = document.getElementById('password');
    const confirm = document.getElementById('confirm_password');
    const message = document.getElementById('match-message');

    if (confirm.value.length > 0) {
        if (password.value === confirm.value) {
            confirm.classList.remove('input-error');
            confirm.classList.add('input-success');
            message.innerHTML = "✓ Passwords match";
            message.style.color = "#2ecc71";
        } else {
            confirm.classList.remove('input-success');
            confirm.classList.add('input-error');
            message.innerHTML = "✗ Passwords do not match";
            message.style.color = "#ff4d4d";
        }
    } else {
        confirm.classList.remove('input-error', 'input-success');
        message.innerHTML = "";
    }
}

const setupToggle = (toggleBtnId, inputId) => {
    const toggleBtn = document.querySelector(toggleBtnId);
    const input = document.querySelector(inputId);
    toggleBtn.addEventListener('click', function () {
        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
        input.setAttribute('type', type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });
};

setupToggle('#togglePassword', '#password');
setupToggle('#toggleConfirmPassword', '#confirm_password');

function validatePasswords() {
    const pass = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;
    if (pass !== confirm) {
        alert("Passwords do not match!");
        return false;
    }
    return true;
}
</script>

</body>
</html>