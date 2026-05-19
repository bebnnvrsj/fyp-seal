<?php
require 'db_connect.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

// 1. Terima hash daripada URL QR Code
$hashFromQR = isset($_GET['hash']) ? mysqli_real_escape_string($conn, $_GET['hash']) : '';

$doc = null;
$isAuthentic = false;
$statusLabel = "Invalid";
$statusClass = "status-invalid";
$icon = "fa-circle-xmark";
$type = ''; 

if (!empty($hashFromQR)) {
    // 2. SQL UNION: Cari hash dalam jadual MC atau TimeSlip secara serentak
    $sql = "SELECT combined.*, u.name as doctor_name FROM (
                SELECT mcID as docID, patientName, patientNRIC, 'mc' as type, 
                       startDate, endDate, NULL as visitDate, NULL as timeIn, NULL as timeOut,
                       documentHash, status, doctorID, diagnosis, transactionHash, createdAt 
                FROM mc
                UNION ALL
                SELECT slipID as docID, patientName, patientNRIC, 'timeslip' as type, 
                       NULL as startDate, NULL as endDate, visitDate, timeIn, timeOut,
                       documentHash, status, doctorID, diagnosis, transactionHash, createdAt 
                FROM timeslip
            ) AS combined
            JOIN users u ON combined.doctorID = u.userID
            WHERE combined.documentHash = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $hashFromQR);
    $stmt->execute();
    $doc = $stmt->get_result()->fetch_assoc();

    if ($doc) {
        $type = $doc['type'];
        
        // Ekstrak semula jam:minit:saat yang tepat dari lajur createdAt (Waktu yang telah di-force)
        $currentTime = date("H:i:s", strtotime($doc['createdAt']));

        // 3. LOGIK RE-HASHING REAL-TIME (DISERAGAMKAN DENGAN FORMAT GENERATE_PDF)
        if ($type === 'mc') {
            // Format tarikh 'd M Y' (Contoh: 16 May 2026) sepadan dengan create_mc_process
            $startDateStr = date('d M Y', strtotime($doc['startDate']));
            $endDateStr   = date('d M Y', strtotime($doc['endDate']));

            $rawData = trim($doc['patientNRIC']) . 
                       trim($startDateStr) . 
                       trim($endDateStr) . 
                       strtoupper(trim($doc['diagnosis'])) . 
                       trim($doc['doctorID']) . 
                       trim($currentTime);       
        } else {
            // Format tarikh 'd F Y' (Contoh: 16 May 2026) sepadan dengan create_timeslip_process
            $visitDateStr = date('d F Y', strtotime($doc['visitDate']));
            $startTimeStr = date("h:i A", strtotime($doc['timeIn']));
            $endTimeStr   = date("h:i A", strtotime($doc['timeOut']));
            
            // PEMBETULAN IMPAK FORENSIK: Wajib letak strtoupper() pada diagnosis Time Slip juga!
            $rawData = trim($doc['patientNRIC']) . 
                       trim($visitDateStr) . 
                       trim($startTimeStr) . 
                       trim($endTimeStr) . 
                       trim($doc['doctorID']) . 
                       trim($currentTime);
        }
        
        $calculatedHash = hash('sha256', $rawData);

        // 4. SEMAK DENGAN BLOCKCHAINRECORD (Source of Truth)
        $checkSql = "SELECT * FROM blockchainrecord WHERE documentHash = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("s", $calculatedHash);
        $checkStmt->execute();
        $blockchainResult = $checkStmt->get_result();

        // 5. Perbandingan Hash: Sahkan data DB sepadan dengan Blockchain
        if ($blockchainResult->num_rows > 0 && $calculatedHash === $doc['documentHash']) {
            $isAuthentic = true; 
        }

        // 6. Penentuan Status Akhir
        if ($isAuthentic) {
            $db_status = strtoupper(trim($doc['status']));

            if ($db_status === 'REVOKED') {
                $statusLabel = "DOCUMENT REVOKED";
                $statusClass = "status-tampered"; // Boleh kekalkan kelas warna oren/merah anda
                $icon = "fa-ban";
            } else {
                // Selagi data sepadan blockchain dan tidak di-revoke, ia sentiasa AUTHENTIC
                $statusLabel = "AUTHENTIC & ACTIVE";
                $statusClass = "status-active";
                $icon = "fa-circle-check";
            }
        } else {
            $statusLabel = "DATA TAMPERED / FORGED";
            $statusClass = "status-tampered";
            $icon = "fa-triangle-exclamation";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SEAL - Verification Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f4f8; margin: 0; padding: 20px; display: flex; justify-content: center; }
        .verify-card { background: white; width: 100%; max-width: 450px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); overflow: hidden; border: 1px solid #ddd; }
        .header { background: #183055; color: white; padding: 25px; text-align: center; }
        .status-banner { padding: 20px; text-align: center; font-weight: 800; font-size: 16px; }
        .details { padding: 25px; }
        .detail-row { margin-bottom: 15px; border-bottom: 1px solid #f8f9fa; padding-bottom: 8px; }
        .label { font-size: 10px; color: #aaa; text-transform: uppercase; font-weight: bold; }
        .value { font-size: 15px; color: #333; font-weight: 600; }
        .status-active { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .status-expired { background: #fff3e0; color: #ef6c00; border: 1px solid #ffe0b2; }
        .status-tampered { background: #000; color: #ffeb3b; border: 2px dashed #ffeb3b; }
        .status-invalid { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

<div class="verify-card">
    <div class="header">
        <h2 style="margin:0; letter-spacing: 3px;">SEAL</h2>
        <p style="margin:5px 0 0; font-size: 11px;">Verification Portal</p>
    </div>

    <div class="status-banner <?php echo $statusClass; ?>">
        <i class="fa-solid <?php echo $icon; ?> fa-lg"></i><br>
        <span style="display:inline-block; margin-top:10px;"><?php echo $statusLabel; ?></span>
    </div>

    <?php if ($doc && $isAuthentic): ?>
        <div class="details">
            <div class="detail-row">
                <div class="label">Patient Name</div>
                <div class="value"><?php echo strtoupper(htmlspecialchars($doc['patientName'])); ?></div>
            </div>
            <div class="detail-row">
                <div class="label">Document Type</div>
                <div class="value"><?php echo ($type === 'mc') ? 'Medical Certificate' : 'Time-Slip'; ?></div>
            </div>
            
            <div class="detail-row">
                 <div class="label">Validity / Time</div>
                <div class="value">
                    <?php 
                    if ($type === 'mc') {
                        echo date("d M Y", strtotime($doc['startDate'])) . " - " . date("d M Y", strtotime($doc['endDate']));
                    } else {
                        echo date("d M Y", strtotime($doc['visitDate'])) . "<br>";
                        echo "<small style='color:#666;'>" . date("h:i A", strtotime($doc['timeIn'])) . " - " . date("h:i A", strtotime($doc['timeOut'])) . "</small>";
                    }
                    ?>
                </div>
            </div>

            <div class="detail-row">
                <div class="label">Healthcare Provider</div>
                <div class="value">Dr. <?php echo htmlspecialchars($doc['doctor_name']); ?></div>
            </div>

            <div class="detail-row">
                <div class="label">Blockchain Transaction</div>
                <div class="value" style="font-size: 10px; color: #2b7a9e; word-break: break-all;">
                    <a href="https://sepolia.etherscan.io/tx/<?php echo $doc['transactionHash']; ?>" target="_blank" style="text-decoration:none; color:inherit;">
                        <?php echo $doc['transactionHash']; ?> <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </div>
            </div>
        </div>
    <?php elseif ($doc && !$isAuthentic): ?>
        <div style="padding: 30px; text-align: center; color: #c62828;">
            <i class="fa-solid fa-burst fa-3x"></i>
            <h3>Security Alert!</h3>
            <p>Data in the database has been tampered with and does not match Blockchain records.</p>
        </div>
    <?php else: ?>
        <div style="padding: 30px; text-align: center; color: #888;">
            <i class="fa-solid fa-magnifying-glass fa-3x"></i>
            <p>No valid document found or invalid QR link.</p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>