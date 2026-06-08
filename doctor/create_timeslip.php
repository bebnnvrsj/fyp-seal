<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}
require '../db_connect.php';

$userID = $_SESSION['userID']; 
$sql = "SELECT d.name FROM users u 
        LEFT JOIN doctor_profiles d ON u.userID = d.doctorID 
        WHERE u.userID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userID);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Time-Slip - SEAL</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        :root { --sidebar-width: 260px; --header-bg: #2b7a9e; --main-bg: linear-gradient(to bottom, #caf0f8, #90e0ef, #48cae4); --dark-blue: #183055; }
        body { margin: 0; font-family: "Segoe UI", sans-serif; background: var(--main-bg); min-height: 100vh; display: flex; overflow-x: hidden; }
        
        .sidebar { width: var(--sidebar-width); height: 100vh; background-color: var(--dark-blue); color: white; position: fixed; left: 0; top: 0; transition: transform 0.3s ease; z-index: 1000; display: flex; flex-direction: column; }
        .sidebar.closed { transform: translateX(-100%); }
        .sidebar-header { padding: 20px; background-color: #122542; display: flex; align-items: center; gap: 15px; font-weight: bold; }
        .sidebar-menu { list-style: none; padding: 0; margin: 20px 0; }
        .sidebar-menu li a { padding: 15px 25px; display: flex; align-items: center; gap: 15px; color: #d1d9e6; text-decoration: none; transition: 0.2s; }
        .sidebar-menu li a:hover { background-color: #2b7a9e; color: white; }
        
        .has-submenu { position: relative; }
        .submenu { list-style: none; padding: 0; margin: 0; max-height: 0; overflow: hidden; background-color: #122542; transition: max-height 0.4s ease-out; }
        .has-submenu:hover .submenu { max-height: 200px; }
        .submenu li a { padding: 12px 25px 12px 60px !important; font-size: 13px !important; color: #a0aec0 !important; }
        .has-submenu > a::after { content: '\f107'; font-family: 'Font Awesome 6 Free'; font-weight: 900; float: right; font-size: 12px; transition: transform 0.3s; }
        .has-submenu:hover > a::after { transform: rotate(180deg); }

        /* PERBAIKAN: Transition untuk wrapper supaya tidak static */
        .main-wrapper { flex: 1; display: flex; flex-direction: column; margin-left: var(--sidebar-width); transition: all 0.3s ease; width: calc(100% - var(--sidebar-width)); }
        .main-wrapper.full-width { margin-left: 0; width: 100%; }
        
        .header { height: 56px; background-color: var(--header-bg); display: flex; align-items: center; justify-content: space-between; padding: 0 24px; color: white; position: sticky; top: 0; z-index: 999; }
        .toggle-btn { cursor: pointer; font-size: 20px; }

        .container { width: 95%; max-width: 1600px; margin: 30px auto; padding: 0 20px; display: flex; flex-direction: column; gap: 25px; }
        .ts-form-card { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); width: 100%; max-width: 800px; margin: 0 auto; }
        .form-section-title { font-size: 16px; font-weight: 700; color: var(--dark-blue); margin-bottom: 20px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; display: flex; align-items: center; gap: 10px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 8px; }
        input, textarea { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #ddd; font-size: 14px; box-sizing: border-box; }
        .issue-btn { background: var(--header-bg); color: white; border: none; padding: 14px 40px; border-radius: 8px; font-size: 16px; font-weight: 700; cursor: pointer; width: 100%; transition: 0.3s; }
        .issue-btn:hover { background: var(--dark-blue); }
        .notification-bar { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; color: white; background: #2ecc71; }
        .search-btn { background: var(--dark-blue); color: white; border: none; padding: 0 20px; border-radius: 8px; cursor: pointer; transition: all 0.3s ease;}
        .search-btn:hover { background: var(--header-bg); transform: scale(1.05);}
    </style>
</head>
<body>

<div class="sidebar" id="sidebar">
   <div class="sidebar-header"><i class="fa-solid fa-user-doctor"></i> <span>SEAL</span></div>
    <ul class="sidebar-menu">
        <li><a href="home_doctor.php"><i class="fa-solid fa-house"></i> Dashboard</a></li>
        <li class="has-submenu">
            <a href="#"><i class="fa-solid fa-plus"></i> Create Document</a>
            <ul class="submenu">
                <li><a href="create_mc.php"><i class="fa-solid fa-file-medical"></i> Medical Certificate</a></li>
                <li><a href="create_timeslip.php" class="active"><i class="fa-solid fa-clock-rotate-left"></i> Time Slip</a></li>
            </ul>
        </li>
        <li><a href="manage_documents.php"><i class="fa-solid fa-file-pen"></i> Manage Documents</a></li>
        <li><a href="view_history.php"><i class="fa-solid fa-database"></i> Issuance History</a></li>
        <li><a href="../profile.php"><i class="fa-solid fa-user-gear"></i> Profile Settings</a></li>
        <li><a href="../login/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
    </ul> 
</div>

<div class="main-wrapper" id="mainWrapper">
    <div class="header"> 
        <div class="header-left">
            <i class="fa-solid fa-bars toggle-btn" onclick="toggleSidebar()"></i>
            <span style="font-weight: 600; margin-left: 15px;">Document Issuance</span>
        </div>
    </div>

    <div class="container">
        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'success'): ?>
            <div class="notification-bar" id="success-alert">
                <i class="fa-solid fa-circle-check"></i> Digital timeslip has been issued and secured via Blockchain!
            </div>
        <?php endif; ?>

        <div class="ts-form-card">
            <form id="tsForm" action="create_ts_process.php" method="POST">
                <input type="hidden" name="matric_staff_no" id="matric_hidden">
                <div class="form-section-title"><i class="fa-solid fa-user"></i> Patient Information</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Search Patient (Matric/Staff No)</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" id="matric_search_input" class="form-control" placeholder="E.g. AI2100XX">
                            <button type="button" id="btnSearch" class="search-btn"><i class="fa-solid fa-address-book"></i></button>
                        </div>
                    </div>
                 </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" id="patientName" readonly required>
                    </div>
                    <div class="form-group">
                        <label>Patient NRIC</label>
                        <input type="text" name="patientNRIC" id="patientNRIC" readonly required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Patient Email</label>
                    <input type="email" name="patient_email" id="patientEmail" readonly>
                </div>

                <div class="form-section-title"><i class="fa-solid fa-clock"></i> Visitation Details</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Visit Date</label>
                        <input type="date" name="visit_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Reason for Visit</label>
                        <input type="text" name="diagnosis" required>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Time In</label>
                        <input type="time" name="time_in" id="start_time" required>
                    </div>
                    <div class="form-group">
                        <label>Time Out</label>
                        <input type="time" name="time_out" id="end_time" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Total Duration</label>
                    <input type="text" id="total_duration" readonly placeholder="Calculated automatically" style="background-color: #f9f9f9; font-weight: bold;">
                </div>

                <button type="submit" class="issue-btn">
                    <i class="fa-solid fa-signature"></i> Sign & Issue Time-Slip
                </button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const successAlert = document.getElementById('success-alert');
    if (successAlert) {
        setTimeout(function() {
            successAlert.style.transition = "opacity 0.5s ease";
            successAlert.style.opacity = "0";
            setTimeout(function() {
                successAlert.style.display = "none";
            }, 500);
        }, 5000); 
    }
});

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainWrapper = document.getElementById('mainWrapper');
    sidebar.classList.toggle('closed');
    mainWrapper.classList.toggle('full-width');
}

const startTime = document.getElementById('start_time');
const endTime = document.getElementById('end_time');
const totalDuration = document.getElementById('total_duration');

function calculateDuration() {
    if (startTime.value && endTime.value) {
        const start = new Date(`2000-01-01T${startTime.value}`);
        const end = new Date(`2000-01-01T${endTime.value}`);
        if (end <= start) {
            totalDuration.value = "Invalid Time Range";
            totalDuration.style.color = "red";
            return;
        }
        const diffMs = end - start;
        const diffHrs = Math.floor(diffMs / (1000 * 60 * 60));
        const diffMins = Math.round((diffMs % (1000 * 60 * 60)) / (1000 * 60));
        totalDuration.value = `${diffHrs} hour(s) ${diffMins} min(s)`;
        totalDuration.style.color = "#2b7a9e";
    }
}

startTime.addEventListener('change', calculateDuration);
endTime.addEventListener('change', calculateDuration);

document.getElementById('tsForm').onsubmit = function(e) {
    if (new Date(`2000-01-01T${startTime.value}`) >= new Date(`2000-01-01T${endTime.value}`)) {
        e.preventDefault();
        Swal.fire({ icon: 'error', title: 'Invalid Time', text: 'End time must be later than start time.' });
        return false;
    }
    Swal.fire({ title: 'Processing Document...', text: 'Securing medical record on SEAL Blockchain network', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
    return true;
};

$('#btnSearch').on('click', function() {
    var matric = $('#matric_search_input').val();
    if(matric == "") return Swal.fire("Error", "Please enter Matric No", "error");

    $.ajax({
        url: 'fetch_patient.php',
        type: 'POST',
        data: { matric_no: matric },
        dataType: 'json',
        success: function(response) {
            if(response.status == 'success') {
                $('#patientName').val(response.full_name);
                $('#patientNRIC').val(response.ic_passport);
                $('#patientEmail').val(response.email);
                $('#matric_hidden').val(matric);
                
                Swal.fire({ icon: 'success',
                    title: 'Found!',
                    text: 'Details for ' + response.full_name + ' loaded.',
                    timer: 1500,
                    showConfirmButton: false
                 });
            } else {
                Swal.fire("Not Found", "Please register this patient in Admin portal.", "warning");
            }
        },
        error: function() {
            Swal.fire("System Error", "Failed to connect to fetch patient information.", "error");
        }
    });
});
</script>
</body>
</html>