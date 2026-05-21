<?php
session_start();
// Sekatan Keselamatan Eksklusif: Hanya benarkan Admin mengakses halaman ini
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/login.php");
    exit();
}
require '../db_connect.php'; 

$patientID = isset($_GET['id']) ? intval($_GET['id']) : 0;
$patient = null;

// Pull current patient data based on Primary Key ID
if ($patientID > 0) {
    $sql = "SELECT * FROM patients WHERE patientID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $patientID);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();
}

// Jika ID tidak sah atau data tiada dalam pangkalan data, tendang balik
if (!$patient) {
    header("Location: user_management.php?msg=patient_not_found");
    exit();
}

// PROSES PENGEMASAN KINI DATA (POST SUBMISSION)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $ic_passport = trim($_POST['ic_passport']);
    $matric_staff_no = trim($_POST['matric_staff_no']);
    $email = trim($_POST['email']);

    if (empty($full_name) || empty($ic_passport) || empty($matric_staff_no)) {
        $error_msg = "Please fulfill all mandatory data fields.";
    } else {
        // Update core patient data inside the patients table
        $update_sql = "UPDATE patients SET full_name = ?, ic_passport = ?, matric_staff_no = ?, email = ? WHERE patientID = ?";
        $up_stmt = $conn->prepare($update_sql);
        $up_stmt->bind_param("ssssi", $full_name, $ic_passport, $matric_staff_no, $email, $patientID);
        
        if ($up_stmt->execute()) {
            // CASCADING UPDATE (Opsional): Kemas kini nama & no matrik lama dalam rekod perubatan untuk kekalkan konsistensi data
            $old_matric = $patient['matric_staff_no'];
            
            $update_mc = $conn->prepare("UPDATE mc SET patientName = ?, matric_staff_no = ? WHERE matric_staff_no = ?");
            $update_mc->bind_param("sss", $full_name, $matric_staff_no, $old_matric);
            $update_mc->execute();

            $update_ts = $conn->prepare("UPDATE timeslip SET patientName = ?, matric_staff_no = ? WHERE matric_staff_no = ?");
            $update_ts->bind_param("sss", $full_name, $matric_staff_no, $old_matric);
            $update_ts->execute();

            header("Location: user_management.php?msg=patient_updated");
            exit();
        } else {
            $error_msg = "Database Error: Failed to execute record maintenance update.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintain Patient Records - SEAL</title>
    
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
        .sidebar-header { padding: 20px; background-color: #122542; display: flex; align-items: center; gap: 15px; font-weight: bold; }
        .sidebar-menu { list-style: none; padding: 0; margin: 20px 0; }
        .sidebar-menu li a { padding: 15px 25px; display: flex; align-items: center; gap: 15px; color: #d1d9e6; text-decoration: none; transition: 0.2s; }
        .sidebar-menu li a:hover { background-color: #2b7a9e; color: white; }
        .sidebar-menu li a.active { background-color: #2b7a9e; color: white; border-left: 4px solid #fff; }

        /* ====== MAIN WRAPPER ====== */
        .main-wrapper { flex: 1; display: flex; flex-direction: column; margin-left: var(--sidebar-width); width: 100%; }
        .header { height: 56px; background-color: var(--header-bg); display: flex; align-items: center; padding: 0 24px; color: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }

        /* ====== CONTAINER & CARD ====== */
        .container { width: 95%; max-width: 700px; margin: 40px auto; padding: 0 20px; box-sizing: border-box; }
        
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
        .form-group {
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

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
        }

        .input-wrapper i {
            position: absolute;
            left: 15px;
            color: #a0aec0;
            font-size: 16px;
        }

        .input-wrapper input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 14px;
            outline: none;
            box-sizing: border-box;
            transition: all 0.2s ease;
            color: #334155;
        }

        .input-wrapper input:focus {
            border-color: var(--header-bg);
            box-shadow: 0 0 0 3px rgba(43, 122, 158, 0.15);
        }

        /* ====== BUTTON ACTIONS ====== */
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            border-top: 1px solid #f0f4f8;
            padding-top: 20px;
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

        .alert-error {
            background-color: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: 12px 15px;
            color: #991b1b;
            font-size: 13px;
            font-weight: 500;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>

<div class="sidebar">
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

<div class="main-wrapper">
    <div class="header">
        <span style="font-weight: 600; font-size: 16px;">Patient Data Maintenance</span>
    </div>

    <div class="container">
        <div class="form-card">
            <h2><i class="fa-solid fa-user-pen" style="color: var(--header-bg);"></i> Modify Master Patient Profile</h2>

            <?php if (isset($error_msg)): ?>
                <div class="alert-error">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span><?php echo $error_msg; ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" id="editPatientForm">
                <div class="form-group">
                    <label>Patient Full Name (As per NRIC/Passport)</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-id-card"></i>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($patient['full_name']); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Matric / Staff Number</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-graduation-cap"></i>
                        <input type="text" name="matric_staff_no" value="<?php echo htmlspecialchars($patient['matric_staff_no']); ?>" oninput="this.value = this.value.toUpperCase()" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Identity Card (NRIC) / Passport Number</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-passport"></i>
                        <input type="text" name="ic_passport" value="<?php echo htmlspecialchars($patient['ic_passport']); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-envelope"></i>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($patient['email'] ?? ''); ?>">
                    </div>
                </div>

                <div class="btn-group">
                    <a href="user_management.php" class="btn btn-cancel"><i class="fa-solid fa-arrow-left"></i> Cancel</a>
                    <button type="submit" class="btn btn-save"><i class="fa-solid fa-floppy-disk"></i> Update Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Penyerahan Borang Interaktif dengan SweetAlert2
    document.getElementById('editPatientForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        Swal.fire({
            title: 'Confirm Update?',
            text: "Are you sure you want to commit these maintenance modifications to the database?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6e7881',
            confirmButtonText: 'Yes, modify registry!',
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