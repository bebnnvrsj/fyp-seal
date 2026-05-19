<?php
session_start();

// 1. SECURITY CHECK
// Only allow logged-in Admins to access this processing file
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/login.php");
    exit();
}

require '../db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 2. COLLECT AND SANITIZE INPUTS
    // Prevents SQL Injection by escaping strings
    $userID         = mysqli_real_escape_string($conn, $_POST['userID']);
    $name           = mysqli_real_escape_string($conn, $_POST['name']);
    $username       = mysqli_real_escape_string($conn, $_POST['username']); 
    $phone_number   = mysqli_real_escape_string($conn, $_POST['phone_number']);
    $staff_number   = mysqli_real_escape_string($conn, $_POST['staff_number']);
    $role           = mysqli_real_escape_string($conn, $_POST['role']);
    
    // MMC Number is optional (NULL if empty)
    $mmc_input = mysqli_real_escape_string($conn, $_POST['mmc_number']);
    $mmc_number = (!empty($mmc_input)) ? substr($mmc_input, 0, 100) : NULL;
    // 3. DUPLICATE CHECK
    // Ensure the new username or staff number isn't already used by another account
    $check_sql = "SELECT userID FROM users WHERE (username = ? OR staff_number = ?) AND userID != ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ssi", $username, $staff_number, $userID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // If a duplicate is found, redirect back to the edit page with an error
        header("Location: update_user.php?id=$userID&error=duplicate");
        exit();
    }

    // 4. PREPARE THE UPDATE STATEMENT
    $update_sql = "UPDATE users SET 
                    username = ?, 
                    role = ?, 
                    name = ?, 
                    phone_number = ?, 
                    mmc_number = ?, 
                    staff_number = ? 
                   WHERE userID = ?";
    
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ssssssi", $username, $role, $name, $phone_number, $mmc_number, $staff_number, $userID);

    // 5. EXECUTE UPDATE AND RECORD AUDIT LOG
    // We execute the statement ONLY ONCE inside the if condition
    if ($update_stmt->execute()) {
        
        // --- START AUDIT LOG ---
        $adminID = $_SESSION['userID']; 
        
        // Use a fallback name if $_SESSION['name'] is not set during login
        $adminName = isset($_SESSION['name']) ? $_SESSION['name'] : 'Administrator'; 

        $logAction = "Update User";
        // Constructing the resource string for the audit log
        $logResource = " Staff: " . $staff_number . " (Updated by " . $adminName . ")";

        $log_sql = "INSERT INTO auditlog (userID, action, resource) VALUES (?, ?, ?)";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->bind_param("iss", $adminID, $logAction, $logResource);
        $log_stmt->execute();   
        // --- END AUDIT LOG ---

        // 6. SUCCESS REDIRECTION
        header("Location: user_management.php?msg=updated");
        exit();

    } else {
        // Log technical error if the update fails
        error_log("Update Failed: " . $conn->error);
        echo "Error updating record. Please try again.";
    }
}
?>