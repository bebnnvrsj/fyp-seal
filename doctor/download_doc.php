<?php
session_start();
require '../db_connect.php';

if (!isset($_GET['id'])) {
    die("Access Denied.");
}

$docID = mysqli_real_escape_string($conn, $_GET['id']);

// Dapatkan hash dokumen untuk paparan view
$sql = "SELECT documentHash FROM medicaldocument WHERE documentID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $docID);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if ($res) {
    // Arahkan ke view_document dan auto-trigger print menggunakan JS
    header("Location: view_document.php?hash=" . $res['documentHash'] . "&action=download");
} else {
    echo "Document not found.";
}
?>