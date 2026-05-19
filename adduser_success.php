<?php
session_start();

// 1. SECURITY CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/login.php");
    exit();
}

require '../db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 2. COLLECT AND SANITIZE INPUTS
    $name           = mysqli_real_escape_string($conn, $_POST['name']);
    $username       = mysqli_real_escape_string($conn, $_POST['username']); 
    $password       = $_POST['password'];
    $phone_number   = mysqli_real_escape_string($conn, $_POST['phone_number']);
    $staff_number   = mysqli_real_escape_string($conn, $_POST['staff_number']);
    $role           = mysqli_real_escape_string($conn, $_POST['role']);
    
    // FIX: Truncate MMC Number to 50 characters to prevent "Data too long" error
    $mmc_input = mysqli_real_escape_string($conn, $_POST['mmc_number']);
    $mmc_number = (!empty($mmc_input)) ? substr($mmc_input, 0, 50) : NULL;

    // 3. HASH PASSWORD
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // 4. DUPLICATE CHECK
    $check_query = "SELECT userID FROM users WHERE username = ? OR staff_number = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("ss", $username, $staff_number);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        header("Location: add_user.php?error=duplicate");
        exit();
    }

    // 5. INSERT NEW USER
    $sql = "INSERT INTO users (username, password, role, name, phone_number, mmc_number, staff_number) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $insert_stmt = $conn->prepare($sql);
    $insert_stmt->bind_param("sssssss", $username, $hashed_password, $role, $name, $phone_number, $mmc_number, $staff_number);

    if ($insert_stmt->execute()) {
        
        // --- START AUDIT LOG ---
        $adminID = $_SESSION['userID']; 
        
        // Fallback: Use 'Administrator' if the session name is missing
        $adminName = isset($_SESSION['name']) ? $_SESSION['name'] : 'Administrator'; 

        $logAction = "Create User";
        // Constructing the resource string
        $logResource = " Staff: " . $staff_number . " (Added by " . $adminName . ")";

        $log_sql = "INSERT INTO auditlog (userID, action, resource) VALUES (?, ?, ?)";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->bind_param("iss", $adminID, $logAction, $logResource);
        $log_stmt->execute();
        // --- END AUDIT LOG ---

        // SUCCESS: Redirect back to User Management
        header("Location: ../admin/user_management.php?msg=created");
        exit();

    } else {
        error_log("Database Error: " . $conn->error);
        header("Location: add_user.php?error=failed");
        exit();
    }
}
?>