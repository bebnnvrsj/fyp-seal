<?php
session_start();
// Only admin users can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/login.php");
    exit();
}
require '../db_connect.php'; 

if (isset($_GET['id']) && isset($_GET['current'])) {
    $id = $_GET['id'];
    $new_status = ($_GET['current'] == 'active') ? 'inactive' : 'active';
    
    // Block admin from deactivating their own account to prevent accidental lockout
    if ($id == $_SESSION['userID']) {
        header("Location: user_management.php?msg=self_delete_error");
        exit();
    }

    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE userID = ?");
    $stmt->bind_param("si", $new_status, $id);
    
    if ($stmt->execute()) {
        header("Location: user_management.php?msg=updated");
    }
}
?>