<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['id']) && isset($_GET['type'])) {
    $docID = intval($_GET['id']);
    $type = mysqli_real_escape_string($conn, $_GET['type']);
    $doctorID = $_SESSION['userID'];

if ($type === 'mc') {
        $check_sql = "SELECT patientName FROM mc WHERE mcID = ? AND doctorID = ?";
        $update_sql = "UPDATE mc SET status = 'Revoked' WHERE mcID = ?";
} 
else {
        $check_sql = "SELECT patientName FROM timeslip WHERE slipID = ? AND doctorID = ?";
        $update_sql = "UPDATE timeslip SET status = 'Revoked' WHERE slipID = ?";
}  
$stmt = $conn->prepare($check_sql);
$stmt->bind_param("ii", $docID, $doctorID);
$stmt->execute();
$result = $stmt->get_result();
$doc = $result->fetch_assoc();

if ($doc) {
    $upd_stmt = $conn->prepare($update_sql);
    $upd_stmt->bind_param("i", $docID);
    
    if ($upd_stmt->execute()) {    
        $logAction = "REVOKE_DOCUMENT";
        $logResource = strtoupper($type) . " ID: #" . $docID . " | Patient: " . $doc['patientName'];
            
        $log_sql = "INSERT INTO auditlog (userID, action, resource) VALUES (?, ?, ?)";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->bind_param("iss", $doctorID, $logAction, $logResource);
        $log_stmt->execute();

        header("Location: manage_documents.php?msg=revoked");
    } 
    else {
        header("Location: manage_documents.php?msg=error");
    }
} 
    else {
        // Dokumen tidak dijumpai atau bukan milik doktor tersebut
        header("Location: manage_documents.php?msg=unauthorized");
    }
} else {
    header("Location: manage_documents.php");
}
exit();
?>