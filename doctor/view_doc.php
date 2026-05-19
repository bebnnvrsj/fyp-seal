<?php
date_default_timezone_set('Asia/Kuala_Lumpur');
session_start();
if (!isset($_SESSION['userID'])) {
    header("Location: ../login.php");
    exit();
}
require '../db_connect.php';

$hash = isset($_GET['hash']) ? mysqli_real_escape_string($conn, $_GET['hash']) : '';
$type = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : '';

if (empty($hash) || empty($type)) {
    die("Error: Document hash or type is missing.");
}

$current_userID = $_SESSION['userID'];

// 1. Tarik data dari DB
if ($type === 'mc') {
    $sql = "SELECT m.*, m.mcID as docID, u.name as doctor_name, u.mmc_number 
            FROM mc m 
            JOIN users u ON m.doctorID = u.userID 
            WHERE m.documentHash = ? AND m.doctorID = ?";
} else {
    $sql = "SELECT t.*, t.slipID as docID, u.name as doctor_name, u.mmc_number 
            FROM timeslip t 
            JOIN users u ON t.doctorID = u.userID 
            WHERE t.documentHash = ? AND t.doctorID = ?";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $hash, $current_userID);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();

if (!$doc) { 
    die("Document not found."); 
}

// 2. Logik Paparan Status 
$db_status = strtoupper(trim($doc['status'])); 
$today = new DateTime(); 
$today->setTime(0, 0, 0);

// Tentukan tarikh tamat tempoh berdasarkan jenis dokumen
$expiryCheckDate = ($type === 'mc') ? $doc['endDate'] : $doc['visitDate'];
$expiryDateObj = new DateTime($expiryCheckDate);
$expiryDateObj->setTime(0, 0, 0);

if ($db_status === 'REVOKED') {
    $current_status = "REVOKED";
    $statusClass = "status-revoked";
} else if ($today > $expiryDateObj) {
    $current_status = "EXPIRED";
    $statusClass = "status-expired";
} else {
    $current_status = "ACTIVE";
    $statusClass = "status-active";
}

// 3. Jana QR URL (Gunakan URL penuh sistem anda)
// Gunakan IP laptop/server anda supaya boleh diakses oleh peranti lain
$serverIP = "192.168.0.223"; // Tukar kepada IP laptop anda
$verificationURL = "http://" . $serverIP . "/fyp/verify.php?hash=" . $doc['documentHash'];
$qrCodeURL = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($verificationURL);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Document - SEAL</title>
    <style>
        :root {
            --primary-blue: #183055;
            --active-green: #27ae60;
            --expired-red: #c0392b;
            --revoked-orange: #e67e22;
        }
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f4f7f6; padding: 40px; }
        .document-container { 
            background: #fff; max-width: 800px; margin: 0 auto; padding: 50px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); border-radius: 8px; position: relative;
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
        .label { font-size: 12px; color: #7f8c8d; text-transform: uppercase; margin-bottom: 5px; }
        .value { font-size: 16px; color: #2c3e50; font-weight: 600; }
        
        .footer-meta { 
            margin-top: 50px; padding-top: 20px; border-top: 1px solid #eee;
            font-size: 11px; color: #95a5a6; display: flex; justify-content: space-between;
        }
        .hash-box { font-family: 'Courier New', monospace; word-break: keep-all; margin-top: 5px; color: #7f8c8d; overflow: hidden; font-size: 9px; }

        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin: 20px 0; text-align: center;">
        <a href="generate_pdf.php?hash=<?php echo $doc['documentHash']; ?>&type=<?php echo $type; ?>" 
        class="btn btn-primary" 
        style="padding: 12px 25px; text-decoration: none; background-color: #0056b3; color: white; border-radius: 5px; font-weight: bold; display: inline-block; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <i class="bi bi-file-earmark-pdf"></i> Download Official PDF
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

    <div class="info-grid">
        <?php if($type === 'mc'): ?>
            <div>
                <div class="label">Start Date</div>
                <div class="value"><?php echo date('d M Y', strtotime($doc['startDate'])); ?></div>
            </div>
            <div>
                <div class="label">End Date</div>
                <div class="value"><?php echo date('d M Y', strtotime($doc['endDate'])); ?></div>
            </div>
        <?php else: ?>
            <div>
                <div class="label">Visit Date</div>
                <div class="value"><?php echo date('d M Y', strtotime($doc['visitDate'])); ?></div>
            </div>
            <div>
                <div class="label">Time</div>
                <div class="value"><?php echo date('h:i A', strtotime($doc['timeIn'])) . " - " . date('h:i A', strtotime($doc['timeOut'])); ?></div>
            </div>
        <?php endif; ?>
    </div>

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