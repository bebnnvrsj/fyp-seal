<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/login.php");
    exit();
}
require '../db_connect.php'; 

// 1. GET THE USER ID FROM THE URL
if (!isset($_GET['id'])) {
    header("Location: user_management.php");
    exit();
}

$id = $_GET['id'];

// 2. FETCH CURRENT DATA FOR THIS USER
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
    <title>Edit User - SEAL</title>
    
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

        /* ====== MAIN WRAPPER ====== */
        .main-wrapper { flex: 1; display: flex; flex-direction: column; margin-left: var(--sidebar-width); transition: margin-left 0.3s ease; width: 100%; }
        .main-wrapper.full-width { margin-left: 0; }

        .header { height: 56px; background-color: var(--header-bg); display: flex; align-items: center; justify-content: space-between; padding: 0 24px; color: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .toggle-btn { cursor: pointer; font-size: 20px; }

        /* ====== CONTAINER (Stretched Layout) ====== */
        .container { 
            width: 95%; 
            max-width: 1200px; 
            margin: 30px auto; 
            padding: 0 20px; 
            display: flex; 
            flex-direction: column; 
            gap: 25px; 
        }

        /* Page Hero */
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

        /* Form Card */
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

        input, select {
            width: 100%; padding: 12px;
            border-radius: 10px; border: 1px solid #ddd; font-size: 14px; box-sizing: border-box;
            background: #fcfcfc;
        }
        input:focus, select:focus { outline: none; border-color: var(--header-bg); background: #fff; }

        .update-btn {
            background-color: var(--header-bg); color: white; border: none;
            padding: 14px 0; width: 100%; font-size: 16px;
            border-radius: 8px; cursor: pointer; font-weight: 600; margin-top: 20px;
            transition: 0.3s;
        }
        .update-btn:hover { background-color: var(--dark-blue); transform: translateY(-2px); }

        #mmc_container { grid-column: span 2; display: <?php echo ($user['role'] == 'doctor') ? 'block' : 'none'; ?>; }
        .full-width-group { grid-column: span 2; }

        @media (max-width: 850px) {
            .form-grid { grid-template-columns: 1fr; }
            #mmc_container, .full-width-group { grid-column: span 1; }
            .page-hero { flex-direction: column; text-align: center; gap: 15px; }
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
        <li><a href="activity_reports.php"><i class="fa-solid fa-chart-pie"></i> Activity Reports</a></li>
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
        <div class="page-hero">
            <div class="hero-info">
                <h1><i class="fa-solid fa-user-pen"></i> Edit User Account</h1>
                <p>Modify system permissions and professional details for <strong><?php echo htmlspecialchars($user['name']); ?></strong>.</p>
            </div>
            <a href="user_management.php" style="color: var(--header-bg); text-decoration: none; font-weight: 600;">
                <i class="fa-solid fa-arrow-left"></i> Back to Management
            </a>
        </div>

        <div class="form-card">
            <form action="updateuser_process.php" method="POST">
                <input type="hidden" name="userID" value="<?php echo $user['userID']; ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Username (Email)</label>
                        <input type="email" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone_number" value="<?php echo htmlspecialchars($user['phone_number']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Staff Number</label>
                        <input type="text" name="staff_number" value="<?php echo htmlspecialchars($user['staff_number']); ?>" required>
                    </div>

                    <div class="full-width-group form-group">
                        <label>System Role</label>
                        <select name="role" id="roleSelect" required onchange="toggleMMC()">
                            <option value="doctor" <?php if($user['role'] == 'doctor') echo 'selected'; ?>>Doctor</option>
                            <option value="verifier" <?php if($user['role'] == 'verifier') echo 'selected'; ?>>Verifier</option>
                            <option value="admin" <?php if($user['role'] == 'admin') echo 'selected'; ?>>Administrator</option>
                        </select>
                    </div>

                    <div id="mmc_container" class="form-group">
                        <label>MMC Number (Required for Doctors)</label>
                        <input type="text" name="mmc_number" id="mmc_input" value="<?php echo htmlspecialchars($user['mmc_number']); ?>" 
                        placeholder="Enter 7-digit MMC Number" maxlength="100">
                    </div>
                </div>

                <button type="submit" class="update-btn">Save Account Changes</button>
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
</script>

</body>
</html>