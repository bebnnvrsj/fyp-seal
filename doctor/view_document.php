<?php
date_default_timezone_set('Asia/Kuala_Lumpur');
session_start();
if (!isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}
require '../db_connect.php';

// Get ID and URL from type
if (!isset($_GET['id']) || !isset($_GET['type'])) {
    header("Location: view_history.php");
    exit();
}

$id = intval($_GET['id']);
$type = strtolower(mysqli_real_escape_string($conn, $_GET['type']));

// Determine document type and prepare corresponding query
if ($type === 'mc') {
    $sql = "SELECT m.*, u.name as doctor_name 
            FROM mc m 
            JOIN users u ON m.doctorID = u.userID 
            WHERE m.mcID = ?";
    //Set page title for MC details view
    $displayTitle = "Medical Certificate Details";
} else {
    //Fetch time slip record and associated doctor name
    $sql = "SELECT t.*, u.name as doctor_name 
            FROM timeslip t 
            JOIN users u ON t.doctorID = u.userID 
            WHERE t.slipID = ?";
    //Set page title for time slip details view
    $displayTitle = "Time-Slip Details";
}

//Prepare SQL dtatement to prevent SQLi
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();

if (!$doc) { die("Record not found."); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $displayTitle; ?> - SEAL</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; padding: 40px; }
        .doc-card { background: white; max-width: 700px; margin: auto; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); overflow: hidden; }
        .doc-header { background: #183055; color: white; padding: 25px; text-align: center; }
        .doc-body { padding: 30px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
        .info-item { border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .label { display: block; font-size: 12px; color: #888; text-transform: uppercase; font-weight: bold; }
        .value { font-size: 15px; color: #333; font-weight: 500; }
        .full-width { grid-column: span 2; }
        .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; color: #183055; font-weight: bold; }
    </style>
</head>
<body>

<div class="doc-card">
    <div class="doc-header">
        <h2><i class="fa-solid fa-file-invoice"></i> <?php echo $displayTitle; ?></h2>
        <p style="opacity: 0.8; font-size: 14px;">Internal Medical Log Record</p>
    </div>

    <div class="doc-body">
        <div class="info-grid">
            <div class="info-item">
                <span class="label">Patient Name</span>
                <span class="value"><?php echo htmlspecialchars($doc['patientName']); ?></span>
            </div>
            <div class="info-item">
                <span class="label">Patient Email</span>
                <span class="value"><?php echo htmlspecialchars($doc['patientEmail']); ?></span>
            </div>

            <?php if ($type === 'mc'): ?>
                <div class="info-item">
                    <span class="label">MC ID</span>
                    <span class="value" style="color: #2b7a9e; font-weight: bold;">MCUTHM<?php echo str_pad($doc['mcID'], 5, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Total Days</span>
                    <span class="value"><?php echo $doc['totalDays']; ?> Day(s)</span>
                </div>
                <div class="info-item">
                    <span class="label">Start Date</span>
                    <span class="value"><?php echo date("d M Y", strtotime($doc['startDate'])); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">End Date</span>
                    <span class="value"><?php echo date("d M Y", strtotime($doc['endDate'])); ?></span>
                </div>
                <div class="info-item full-width">
                    <span class="label">Diagnosis</span>
                    <span class="value"><?php echo htmlspecialchars($doc['diagnosis']); ?></span>
                </div>
            <?php else: ?>
                <div class="info-item">
                    <span class="label">Slip ID</span>
                    <span class="value" style="color: #2b7a9e; font-weight: bold;">TSUTHM<?php echo str_pad($doc['slipID'], 5, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Visit Date</span>
                    <span class="value"><?php echo date("d M Y", strtotime($doc['visitDate'])); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Time In</span>
                    <span class="value"><?php echo date("h:i A", strtotime($doc['timeIn'])); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Time Out</span>
                    <span class="value"><?php echo date("h:i A", strtotime($doc['timeOut'])); ?></span>
                </div>
                <div class="info-item full-width">
                    <span class="label">Purpose of Visit</span>
                    <span class="value"><?php echo htmlspecialchars($doc['diagnosis']); ?></span>
                </div>
            <?php endif; ?>

            <div class="info-item">
                <span class="label">Issued By</span>
                <span class="value">Dr. <?php echo htmlspecialchars($doc['doctor_name']); ?></span>
            </div>
            <div class="info-item">
                <span class="label">Created At</span>
                <span class="value"><?php echo date("d M Y, h:i A", strtotime($doc['createdAt'])); ?></span>
            </div>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="view_history.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Back to History</a>
        </div>
    </div>
</div>

</body>
</html>