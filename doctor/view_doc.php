<?php
date_default_timezone_set('Asia/Kuala_Lumpur');
session_start();
if (!isset($_SESSION['userID'])) {
    header("Location: ../login.php");
    exit();
}
require '../db_connect.php';

//Get document hash and type from GET parameters
$hash = isset($_GET['hash']) ? mysqli_real_escape_string($conn, $_GET['hash']) : '';
$type = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : '';

//Validate required parameters
if (empty($hash) || empty($type)) {
    die("Error: Document hash or type is missing.");
}

//Get currently logged-in user ID from session
$current_userID = $_SESSION['userID'];

//Load document data based on type
if ($type === 'mc') {
    // Retrieve MC record and join doctor profile data (name and MMC number)
    $sql = "SELECT m.*, m.mcID as docID, dp.name as doctor_name, dp.mmc_number 
            FROM mc m 
            JOIN users u ON m.doctorID = u.userID 
            LEFT JOIN doctor_profiles dp ON u.userID = dp.doctorID
            WHERE m.documentHash = ? AND m.doctorID = ?";
} else {
    // Retrieve Time Slip record and join doctor profile data (name and MMC number)
    $sql = "SELECT t.*, t.slipID as docID, dp.name as doctor_name, dp.mmc_number 
            FROM timeslip t 
            JOIN users u ON t.doctorID = u.userID 
            LEFT JOIN doctor_profiles dp ON u.userID = dp.doctorID
            WHERE t.documentHash = ? AND t.doctorID = ?";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $hash, $current_userID);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();

// Stop execution if document is not found
if (!$doc) { 
    die("Document not found."); 
}

// STATUS DISPLAYED
$db_status = strtoupper(trim($doc['status'])); 

if ($db_status === 'REVOKED') {
    $current_status = "REVOKED";
    $statusClass = "status-revoked";
} else {
    $current_status = "DOCTOR COPY";
    $statusClass = "status-active";
}

// DYNAMIC LEAVE DURATION CALCULATION (FOR MC ONLY)
if ($type === 'mc') {
    $start = new DateTime($doc['startDate']);
    $end = new DateTime($doc['endDate']);
    $interval = $start->diff($end);
    $days = $interval->days + 1; // Ditambah 1 untuk mengira hari merangkumi tarikh akhir
    $durationText = $days . ($days > 1 ? ' Days' : ' Day');
} else {
    $durationText = "Single Day Visit";
}

// Generate QR verification URL
$serverIP = "seal-uthm.site"; // Alamat IP hos pelayan Laragon anda
$verificationURL = "http://" . $serverIP . "/login/login.php";
$qrCodeURL = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($verificationURL);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Document - SEAL</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --primary-blue: #183055;
            --header-bg: #2b7a9e;
            --active-green: #27ae60;
            --expired-red: #c0392b;
            --revoked-orange: #e67e22;
        }
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f4f7f6; padding: 40px; margin: 0; }
        
        /* ─── BAR KAWALAN AKSI MESRA PENGGUNA (UX BAR) ─── */
        .action-control-bar {
            max-width: 800px;
            margin: 0 auto 25px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-sizing: border-box;
        }

        .btn-ux {
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            border: none;
            cursor: pointer;
        }
        .btn-ux:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.12);
        }

        .btn-ux-back {
            background-color: var(--primary-blue);
            color: white;
        }
        .btn-ux-back:hover { background-color: #11223d; }

        .btn-ux-download {
            background-color: var(--active-green);
            color: white;
        }
        .btn-ux-download:hover { background-color: #1e8449; }

        /* ====== DESAIN TERAS MC/TIMESLIP ====== */
        .document-container { 
            background: #fff; max-width: 800px; margin: 0 auto; padding: 50px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); border-radius: 8px; position: relative;
            box-sizing: border-box;
        }
        .header { text-align: center; border-bottom: 3px solid var(--primary-blue); padding-bottom: 20px; margin-bottom: 30px; }
        
        .status-stamp {
            position: absolute; top: 50px; right: 50px; padding: 10px 20px;
            border: 4px solid; border-radius: 10px; font-weight: bold; font-size: 24px;
            transform: rotate(15deg); opacity: 0.8;
        }
        .status-active { color: var(--active-green); border-color: var(--active-green); }
        .status-expired { color: var(--expired-red); border-color: var(--expired-red); }
        .status-revoked { color: var(--revoked-orange); border-color: var(--revoked-orange); }
        
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .info-grid-triple { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 30px; } /* Grid khas untuk paparan 3 kolum */
        .label { font-size: 12px; color: #7f8c8d; text-transform: uppercase; margin-bottom: 5px; }
        .value { font-size: 16px; color: #2c3e50; font-weight: 600; }
        
        .footer-meta { 
            margin-top: 50px; padding-top: 20px; border-top: 1px solid #eee;
            font-size: 11px; color: #95a5a6; display: flex; justify-content: space-between;
        }
        .hash-box { font-family: 'Courier New', monospace; word-break: break-all; margin-top: 5px; color: #7f8c8d; overflow: hidden; font-size: 9px; }

        @media print {
            .no-print { display: none !important; }
            body { padding: 0; background-color: #fff; }
            .document-container { box-shadow: none; border-radius: 0; padding: 0; }
        }
    </style>
</head>
<body>

    <div class="action-control-bar no-print">
        <a href="manage_documents.php" class="btn-ux btn-ux-back">
            <i class="fa-solid fa-arrow-left-long"></i> Back to Directory
        </a>
        
        <a href="generate_pdf.php?hash=<?php echo $doc['documentHash']; ?>&type=<?php echo $type; ?>" class="btn-ux btn-ux-download">
            <i class="fa-solid fa-file-pdf"></i> Download as PDF
        </a>
    </div>

    <div class="document-container">
        <div class="status-stamp <?php echo $statusClass; ?>">
            <?php echo $current_status; ?>
        </div>

        <div class="header">
            <h1 style="color: var(--primary-blue); margin: 0;">
                <?php echo ($type === 'mc' ? 'MEDICAL CERTIFICATE' : 'TIME-SLIP'); ?>
            </h1>
            <p style="margin: 5px 0;">Pusat Kesihatan Universiti | UTHM </p>
            <p style="font-size: 14px;">
                Document ID: <?php echo ($type === 'mc' ? 'MCUTHM' : 'TSUTHM') . str_pad($doc['docID'], 6, "0", STR_PAD_LEFT); ?>
            </p>
        </div>

        <div class="info-grid">
            <div>
                <div class="label">Patient Name</div>
                <div class="value"><?php echo htmlspecialchars($doc['patientName']); ?></div>
            </div>
            <div>
                <div class="label">Patient NRIC</div>
                <div class="value"><?php echo htmlspecialchars($doc['patientNRIC']); ?></div>
            </div>
        </div>

        <div class="info-grid">
            <div>
                <div class="label">Matric / Staff Number</div>
                <div class="value"><?php echo strtoupper(htmlspecialchars($doc['matric_staff_no'])); ?> </div>
            </div>
        </div>

        <?php if($type === 'mc'): ?>
            <div class="info-grid-triple">
                <div>
                    <div class="label">Start Date</div>
                    <div class="value"><i class="fa-regular fa-calendar-plus" style="color:var(--header-bg); margin-right:3px;"></i> <?php echo date('d M Y', strtotime($doc['startDate'])); ?></div>
                </div>
                <div>
                    <div class="label">End Date</div>
                    <div class="value"><i class="fa-regular fa-calendar-minus" style="color:var(--header-bg); margin-right:3px;"></i> <?php echo date('d M Y', strtotime($doc['endDate'])); ?></div>
                </div>
                <div>
                    <div class="label">Leave Duration</div>
                    <div class="value" style="color: var(--primary-blue); font-weight: 700;"><i class="fa-solid fa-business-time" style="color:var(--header-bg); margin-right:3px;"></i> <?php echo $durationText; ?></div>
                </div>
            </div>
        <?php else: ?>
            <div class="info-grid-triple">
                <div>
                    <div class="label">Visit Date</div>
                    <div class="value"><i class="fa-regular fa-calendar" style="color:var(--header-bg); margin-right:3px;"></i> <?php echo date('d M Y', strtotime($doc['visitDate'])); ?></div>
                </div>
                <div>
                    <div class="label">Time Session</div>
                    <div class="value"><i class="fa-regular fa-clock" style="color:var(--header-bg); margin-right:3px;"></i> <?php echo date('h:i A', strtotime($doc['timeIn'])) . " - " . date('h:i A', strtotime($doc['timeOut'])); ?></div>
                </div>
                <div>
                    <div class="label">Duration Status</div>
                    <div class="value" style="color: #718096; font-style: italic;"><i class="fa-solid fa-user-clock" style="color:var(--header-bg); margin-right:3px;"></i> <?php echo $durationText; ?></div>
                </div>
            </div>
        <?php endif; ?>

        <div style="margin-bottom: 30px;">
            <div class="label">Diagnosis / Purpose</div>
            <div class="value" style="font-style: italic;">"<?php echo htmlspecialchars($doc['diagnosis']); ?>"</div>
        </div>

        <div style="margin-bottom: 30px;">
            <div class="label">Attending Physician</div>
            <div class="value">Dr. <?php echo htmlspecialchars($doc['doctor_name']); ?></div>
            <div style="font-size: 12px; color: #7f8c8d;">MMC Number: <?php echo htmlspecialchars($doc['mmc_number']); ?></div>
        </div>

        <div class="footer-meta">
            <div style="max-width: 70%;">
                <strong>Digital Fingerprint (SHA-256):</strong>
                <div class="hash-box"><?php echo $doc['documentHash']; ?></div>
                <br>
                <strong>Blockchain Transaction ID:</strong>
                <div class="hash-box"><?php echo $doc['transactionHash']; ?></div>
                <p style="margin-top: 10px; font-style: italic;">Verified by SEAL Blockchain Security System</p>
            </div>
            <div style="text-align: center;">
                <a href="<?php echo $verificationURL; ?>" target="_blank" style="text-decoration: none;">
                    <img src="<?php echo $qrCodeURL; ?>" alt="QR Verification" style="border: 1px solid #eee; padding: 5px; background: white;">
                    <p style="font-size: 9px; color: var(--primary-blue); font-weight: bold; margin-top: 5px;">Verify Online</p>
                </a>
            </div>
        </div>
    </div>

</body>
</html>