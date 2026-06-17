<?php
session_start();
//Only doctor can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}
require '../db_connect.php';

if (!isset($_GET['id'])) {
    die("Access Denied.");
}

$docID = mysqli_real_escape_string($conn, $_GET['id']);

// Retrieve document hash for display in the view layer
$sql = "SELECT documentHash FROM medicaldocument WHERE documentID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $docID);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if ($res) {
    // Redirect to view_document page and automatically trigger print function via JavaScript
    header("Location: view_document.php?hash=" . $res['documentHash'] . "&action=download");
} else {
    echo "Document not found.";
}
?>