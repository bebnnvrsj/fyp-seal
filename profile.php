<?php
session_start();
if (!isset($_SESSION['role'])) {
    header("Location: login/login.php");
    exit();
}
require 'db_connect.php'; 

$userID = $_SESSION['userID']; 

// SQL Query: Fetches profile data dynamically based on the 3NF decentralized structure
$sql = "SELECT u.*, 
               COALESCE(d.name, a.name, v.name) AS display_name,
               d.mmc_number,
               COALESCE(d.staff_number, a.staff_number, v.staff_number) AS id_number,
               v.organization_name
        FROM users u
        LEFT JOIN doctor_profiles d ON u.userID = d.doctorID
        LEFT JOIN admin_profiles a ON u.userID = a.adminID
        LEFT JOIN verifier_profiles v ON u.userID = v.verifierID
        WHERE u.userID = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userID);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    die("User session context not found inside the live database.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - SEAL</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

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

        /* ====== SIDEBAR & WRAPPER ====== */
        .sidebar { width: var(--sidebar-width); height: 100vh; background-color: #183055; color: white; position: fixed; left: 0; top: 0; transition: transform 0.3s ease; z-index: 1000; display: flex; flex-direction: column; }
        .sidebar.closed { transform: translateX(-100%); }
        .sidebar-header { padding: 20px; background-color: #122542; display: flex; align-items: center; gap: 15px; font-weight: bold; }
        .sidebar-menu { list-style: none; padding: 0; margin: 20px 0; }
        .sidebar-menu li a { padding: 15px 25px; display: flex; align-items: center; gap: 15px; color: #d1d9e6; text-decoration: none; transition: 0.2s; }
        .sidebar-menu li a:hover { background-color: #2b7a9e; color: white; }
        .sidebar-menu li a.active { background-color: #2b7a9e; color: white; border-left: 4px solid #fff; }

        .main-wrapper { flex: 1; display: flex; flex-direction: column; margin-left: var(--sidebar-width); transition: margin-left 0.3s ease; width: 100%; }
        .main-wrapper.full-width { margin-left: 0; }
        .header { height: 56px; background-color: var(--header-bg); display: flex; align-items: center; justify-content: space-between; padding: 0 24px; color: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .toggle-btn { font-size: 20px; cursor: pointer; }

        /* ====== CONTAINER ====== */
        .container { max-width: 1100px; margin: 30px auto; padding: 0 25px; display: flex; flex-direction: column; gap: 25px; }

        .profile-hero {
            background: white; border-radius: 15px; padding: 30px;
            display: flex; align-items: center; gap: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .hero-avatar {
            width: 110px; height: 110px; background: #e3f2fd; color: var(--header-bg);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 45px; border: 4px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .hero-text h1 { margin: 0; color: var(--dark-blue); font-size: 26px; }
        .hero-text p { margin: 5px 0; color: #666; }

        .content-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 25px; }
        @media (max-width: 900px) { .content-grid { grid-template-columns: 1fr; } .profile-hero { flex-direction: column; text-align: center; } }

        .details-card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); margin-bottom: 25px; }
        h2 { color: var(--dark-blue); font-size: 19px; margin-top: 0; border-bottom: 2px solid #f0f0f0; padding-bottom: 12px; display: flex; align-items: center; gap: 10px; }
        
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 6px; }
        input { width: 100%; padding: 11px; border-radius: 8px; border: 1px solid #ddd; background: #fcfcfc; box-sizing: border-box; }
        
        .save-btn { background: var(--header-bg); color: white; border: none; padding: 12px 25px; border-radius: 8px; cursor: pointer; font-weight: 600; transition: 0.3s; }
        .save-btn:hover { background: var(--dark-blue); }

        .password-wrapper { position: relative; display: flex; align-items: center; }
        .password-wrapper i.toggle-password { position: absolute; right: 15px; cursor: pointer; color: #888; }

        .requirement { font-size: 11px; padding: 3px 8px; border-radius: 4px; background: #eee; display: inline-block; margin: 2px; transition: 0.3s; }
        .valid { background: #d4edda; color: #155724; }
        .invalid { background: #f8d7da; color: #721c24; }

        /* Added smooth transitions for opacity changes */
        .notification-bar { 
            padding: 15px; border-radius: 8px; margin-bottom: 20px; color: white; 
            display: flex; align-items: center; gap: 10px; 
            transition: opacity 0.5s ease, transform 0.5s ease;
            opacity: 1;
        }
        .bg-success { background: #1a7f4e; }
        .bg-error { background: #d9534f; }

        .has-submenu { position: relative; }
        .submenu { list-style: none; padding: 0; margin: 0; max-height: 0; overflow: hidden; background-color: #122542; transition: max-height 0.4s ease-out; }
        .has-submenu:hover .submenu { max-height: 200px; }
        .submenu li a { padding: 12px 25px 12px 60px !important; font-size: 13px !important; color: #a0aec0 !important; }
        .has-submenu > a::after { content: '\f107'; font-family: 'Font Awesome 6 Free'; font-weight: 900; float: right; font-size: 12px; transition: transform 0.3s; }
        .has-submenu:hover > a::after { transform: rotate(180deg); }
    </style>
</head>

<body>
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <?php 
            if ($user['role'] == 'admin') {
                echo '<i class="fa-solid fa-user-shield"></i>'; 
            } elseif ($user['role'] == 'verifier') {
                echo '<i class="fa-solid fa-file-signature"></i>'; 
            } else {
                echo '<i class="fa-solid fa-user-doctor"></i>'; 
            }
        ?>
        <span>SEAL</span>
    </div>

    <ul class="sidebar-menu">
        <li>
            <?php if($user['role'] == 'admin'): ?>
                <a href="admin/home_admin.php"><i class="fa-solid fa-house"></i> Dashboard</a>
            <?php elseif($user['role'] == 'doctor'): ?>
                <a href="doctor/home_doctor.php"><i class="fa-solid fa-house"></i> Dashboard</a>
            <?php else: ?>
                <a href="verifier/home_verifier.php"><i class="fa-solid fa-house"></i> Dashboard</a>
            <?php endif; ?>
        </li>

        <?php if($user['role'] == 'admin'): ?>
            <li><a href="admin/register_patient.php"><i class="fa-solid fa-user-plus"></i> Register Patient</a></li>
            <li><a href="admin/user_management.php"><i class="fa-solid fa-users-gear"></i> User Management</a></li>
            <li><a href="admin/document_monitoring.php"><i class="fa-solid fa-file-shield"></i> Doc Monitoring</a></li>
            <li><a href="admin/audit_logs.php"><i class="fa-solid fa-clipboard-list"></i> Audit Logs</a></li>
    
        <?php elseif($user['role'] == 'doctor'): ?>
            <li class="has-submenu">
                <a href="#"><i class="fa-solid fa-plus"></i> Create Document</a>
                <ul class="submenu">
                    <li><a href="doctor/create_mc.php"><i class="fa-solid fa-file-medical"></i> Medical Certificate</a></li>
                    <li><a href="doctor/create_timeslip.php"><i class="fa-solid fa-clock-rotate-left"></i> Time Slip</a></li>
                </ul>
            </li>
            <li><a href="doctor/manage_documents.php"><i class="fa-solid fa-file-pen"></i> Manage Documents</a></li>
            <li><a href="doctor/view_history.php"><i class="fa-solid fa-database"></i> Issuance History</a></li>

        <?php elseif($user['role'] == 'verifier'): ?>
            <li><a href="verifier/verify_document.php"><i class="fa-solid fa-magnifying-glass"></i> Verify Document</a></li>
            <li><a href="verifier/verification_history.php"><i class="fa-solid fa-clock-rotate-left"></i> Verification History</a></li>
        <?php endif; ?>

        <li><a href="profile.php" class="active"><i class="fa-solid fa-user-gear"></i> Profile Settings</a></li>
        <li><a href="login/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
    </ul>
</div>

<div class="main-wrapper" id="mainWrapper">
    <div class="header">
        <div class="header-left">
            <i class="fa-solid fa-bars toggle-btn" onclick="toggleSidebar()"></i>
            <span style="font-weight: 600; margin-left: 15px;">Profile Dashboard</span>
        </div>
    </div>

    <div class="container">
        <?php if (isset($_SESSION['msg'])): ?>
            <div id="notificationAlert" class="notification-bar <?php echo ($_SESSION['msg_type'] == 'success') ? 'bg-success' : 'bg-error'; ?>">
                <i class="fa-solid <?php echo ($_SESSION['msg_type'] == 'success') ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>"></i>
                <?php echo htmlspecialchars($_SESSION['msg']); unset($_SESSION['msg']); unset($_SESSION['msg_type']); ?>
            </div>
        <?php endif; ?>

        <div class="profile-hero">
            <div class="hero-avatar">
                <i class="fa-solid <?php echo ($user['role'] == 'doctor') ? 'fa-user-doctor' : 'fa-user-shield'; ?>"></i>           
            </div>
            <div class="hero-text">
                <h1><?php echo htmlspecialchars($user['display_name']); ?></h1>
                <p><i class="fa-solid fa-envelope"></i> <?php echo htmlspecialchars($user['username']); ?> | <span class="role-badge" style="background:var(--dark-blue); padding: 2px 10px; border-radius:10px; color:white; font-size:10px;"><?php echo strtoupper($user['role']); ?></span></p>
                <p style="font-size: 13px; color: #888;"><i class="fa-solid fa-clock"></i> Member since <?php echo date("d M Y", strtotime($user['created_at'])); ?></p>
            </div>
        </div>

        <div class="content-grid">
            <div class="details-card">
                <h2><i class="fa-solid fa-address-card"></i> Personal Information</h2>
                <form action="update_profile.php" method="POST">
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($user['display_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Staff ID</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['id_number'] ?? 'N/A'); ?>" disabled style="background:#eee; cursor: not-allowed;">
                        </div>
                    </div>
                    
                    <?php if($user['role'] == 'doctor'): ?>
                    <div class="form-group">
                        <label>MMC Number</label>
                        <input type="text" name="mmc_number" value="<?php echo htmlspecialchars($user['mmc_number']); ?>" required>
                    </div>
                    <?php elseif($user['role'] == 'verifier'): ?>
                    <div id="orgContainer" class="form-group dynamic-container" style="position: relative;">
                        <label>Organization / Faculty / Center</label>
                        <i class="fa-solid fa-building-user input-icon" style="position: absolute; left: 15px; top: 38px; color: #888;"></i>
                        <select name="organization_name" id="orgSelect" required style="width: 100%; padding: 11px 11px 11px 42px; border-radius: 8px; border: 1px solid #ddd; background: #fcfcfc; box-sizing: border-box; font-family: inherit;">
                            <option value="" disabled <?php if(empty($user['organization_name'])) echo 'selected'; ?>>Choose Dept / Faculty</option>
                            <option value="Fakulti Kejuruteraan Awam dan Alam Sekitar (FKAAS)" <?php if($user['organization_name'] == 'Fakulti Kejuruteraan Awam dan Alam Sekitar (FKAAS)') echo 'selected'; ?>>Fakulti Kejuruteraan Awam dan Alam Sekitar (FKAAS)</option>
                            <option value="Fakulti Kejuruteraan Elektrik dan Elektronik (FKEE)" <?php if($user['organization_name'] == 'Fakulti Kejuruteraan Elektrik dan Elektronik (FKEE)') echo 'selected'; ?>>Fakulti Kejuruteraan Elektrik dan Elektronik (FKEE)</option>
                            <option value="Fakulti Kejuruteraan Mekanikal dan Pembuatan (FKMP)" <?php if($user['organization_name'] == 'Fakulti Kejuruteraan Mekanikal dan Pembuatan (FKMP)') echo 'selected'; ?>>Fakulti Kejuruteraan Mekanikal dan Pembuatan (FKMP)</option>
                            <option value="Fakulti Pengurusan Teknologi dan Perniagaan (FPTP)" <?php if($user['organization_name'] == 'Fakulti Pengurusan Teknologi dan Perniagaan (FPTP)') echo 'selected'; ?>>Fakulti Pengurusan Teknologi dan Perniagaan (FPTP)</option>
                            <option value="Fakulti Pendidikan Teknikal dan Vokasional (FPTV)" <?php if($user['organization_name'] == 'Fakulti Pendidikan Teknikal dan Vokasional (FPTV)') echo 'selected'; ?>>Fakulti Pendidikan Teknikal dan Vokasional (FPTV)</option>
                            <option value="Fakulti Sains Komputer dan Teknologi Maklumat (FSKTM)" <?php if($user['organization_name'] == 'Fakulti Sains Komputer dan Teknologi Maklumat (FSKTM)') echo 'selected'; ?>>Fakulti Sains Komputer dan Teknologi Maklumat (FSKTM)</option>
                            <option value="Fakulti Sains Gunaan dan Teknologi (FAST)" <?php if($user['organization_name'] == 'Fakulti Sains Gunaan dan Teknologi (FAST)') echo 'selected'; ?>>Fakulti Sains Gunaan dan Teknologi (FAST)</option>
                            <option value="Fakulti Teknologi Kejuruteraan (FTK)" <?php if($user['organization_name'] == 'Fakulti Teknologi Kejuruteraan (FTK)') echo 'selected'; ?>>Fakulti Teknologi Kejuruteraan (FTK)</option>
                            <option value="Pusat Pengajian Diploma (PPD)" <?php if($user['organization_name'] == 'Pusat Pengajian Diploma (PPD)') echo 'selected'; ?>>Pusat Pengajian Diploma (PPD)</option>
                            <option value="Pusat Pengajian Umum dan Kokurikulum (PPUK)" <?php if($user['organization_name'] == 'Pusat Pengajian Umum dan Kokurikulum (PPUK)') echo 'selected'; ?>>Pusat Pengajian Umum dan Kokurikulum (PPUK)</option>
                            <option value="Pusat Pengajian Bahasa (PPB)" <?php if($user['organization_name'] == 'Pusat Pengajian Bahasa (PPB)') echo 'selected'; ?>>Pusat Pengajian Bahasa (PPB)</option>
                            <option value="Pejabat Antarabangsa (IO)" <?php if($user['organization_name'] == 'Pejabat Antarabangsa (IO)') echo 'selected'; ?>>Pejabat Antarabangsa (IO / International Office)</option>
                            <option value="Pusat Pembelajaran Maya (CVL)" <?php if($user['organization_name'] == 'Pusat Pembelajaran Maya (CVL)') echo 'selected'; ?>>Pusat Pembelajaran Maya (CVL)</option>
                            <option value="Pusat Pembelajaran Berterusan dan APEL (PPB APEL)" <?php if($user['organization_name'] == 'Pusat Pembelajaran Berterusan dan APEL (PPB APEL)') echo 'selected'; ?>>Pusat Pembelajaran Berterusan dan APEL</option>
                            <option value="Pejabat Pengurusan Akademik (PPA)" <?php if($user['organization_name'] == 'Pejabat Pengurusan Akademik (PPA)') echo 'selected'; ?>>Pejabat Pengurusan Akademik (PPA)</option>
                            <option value="Teaching Factory (TF)" <?php if($user['organization_name'] == 'Teaching Factory (TF)') echo 'selected'; ?>>Teaching Factory (TF UTHM)</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="save-btn">Update Profile</button>
                </form>
            </div>
        </div>

        <div class="details-card">
            <h2><i class="fa-solid fa-shield-lock"></i> Security & Password</h2>
            <form action="process_change.php" method="POST" id="passwordForm">
                <div class="form-group">
                    <label>Current Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="current_password" id="current_password" required placeholder="Enter current password">
                        <i class="fa-solid fa-eye toggle-password"></i>
                    </div>
                    <div id="current_pw_feedback" style="font-size: 11px; margin-top: 5px; font-weight: bold;"></div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>New Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="new_password" id="new_password" required placeholder="Min 12 characters">
                            <i class="fa-solid fa-eye toggle-password"></i>
                        </div>
                        <div id="reqs" style="margin-top: 10px;">
                            <span class="requirement" id="length">12+ Chars</span>
                            <span class="requirement" id="upper">Uppercase</span>
                            <span class="requirement" id="lower">Lowercase</span>
                            <span class="requirement" id="number">Number</span>
                            <span class="requirement" id="special">Symbol</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="confirm_password" id="confirm_password" required placeholder="Repeat new password">
                            <i class="fa-solid fa-eye toggle-password"></i>
                        </div>
                        <div id="match_indicator" style="font-size: 11px; margin-top: 5px; font-weight: bold;"></div>
                    </div>
                </div>
                <button type="submit" class="save-btn" style="background-color: #d9534f;" id="submitBtn">Update Password</button>
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

    // ====== 🆕 AUTO-HIDE NOTIFICATION TIMER CODE ======
    document.addEventListener("DOMContentLoaded", function() {
        const alertBox = document.getElementById("notificationAlert");
        if (alertBox) {
            // Set timeout for 5 seconds (5000ms)
            setTimeout(function() {
                // Smoothly slide and fade away
                alertBox.style.opacity = "0";
                alertBox.style.transform = "translateY(-10px)";
                
                // Completely remove from document layout after transition finishes (0.5 seconds)
                setTimeout(function() {
                    alertBox.remove();
                }, 500);
            }, 5000);
        }
    });

    const currentPassInput = document.getElementById('current_password');
    const currentFeedback = document.getElementById('current_pw_feedback');
    const newPass = document.getElementById('new_password');
    const confirmPass = document.getElementById('confirm_password');
    const matchIndicator = document.getElementById('match_indicator');
    const submitBtn = document.getElementById('submitBtn');

    let isCurrentValid = false, isNewValid = false, isMatchValid = false;

    currentPassInput.addEventListener('input', function() {
        const val = this.value;
        if (val.length > 0) {
            const formData = new URLSearchParams();
            formData.append('current_password', val);

            fetch('check_current_password.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.text())
            .then(data => {
                const result = data.trim(); 
                if (result === 'match') {
                    currentFeedback.textContent = "✓ Verified"; 
                    currentFeedback.style.color = "#1a7f4e"; 
                    isCurrentValid = true;
                } else {
                    currentFeedback.textContent = "✗ Incorrect Password"; 
                    currentFeedback.style.color = "#d9534f"; 
                    isCurrentValid = false;
                }
                validateForm();
            });
        } else {
            currentFeedback.textContent = "";
            isCurrentValid = false;
            validateForm();
        }
    });

    const requirements = {
        length: /^.{12,}$/,
        upper: /[A-Z]/,
        lower: /[a-z]/,
        number: /[0-9]/,
        special: /[@$!%*?&_]/
    };

    newPass.addEventListener('input', () => {
        let allReqsMet = true;
        Object.keys(requirements).forEach(key => {
            const el = document.getElementById(key);
            if (requirements[key].test(newPass.value)) {
                el.classList.add('valid'); el.classList.remove('invalid');
            } else {
                el.classList.add('invalid'); el.classList.remove('valid'); allReqsMet = false;
            }
        });
        isNewValid = allReqsMet;
        checkMatch();
    });

    function checkMatch() {
        if (confirmPass.value.length > 0) {
            if (newPass.value === confirmPass.value) {
                matchIndicator.textContent = "✓ Passwords Match"; matchIndicator.style.color = "#1a7f4e"; isMatchValid = true;
            } else {
                matchIndicator.textContent = "✗ No Match"; matchIndicator.style.color = "#d9534f"; isMatchValid = false;
            }
        } else {
            matchIndicator.textContent = "";
            isMatchValid = false;
        }
        validateForm();
    }

    confirmPass.addEventListener('input', checkMatch);

    function validateForm() {
        submitBtn.disabled = !(isCurrentValid && isNewValid && isMatchValid);
        submitBtn.style.opacity = submitBtn.disabled ? "0.5" : "1";
    }

    document.querySelectorAll('.toggle-password').forEach(icon => {
        icon.addEventListener('click', function() {
            const input = this.previousElementSibling;
            input.type = input.type === 'password' ? 'text' : 'password';
            this.classList.toggle('fa-eye'); this.classList.toggle('fa-eye-slash');
        });
    });
</script>
</body>
</html>