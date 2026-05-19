<?php
session_start();
require '../db_connect.php';

if (isset($_GET['id']) && isset($_GET['current'])) {
    $id = $_GET['id'];
    $new_status = ($_GET['current'] == 'active') ? 'inactive' : 'active';
    
    // Elakkan admin deactivate diri sendiri
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