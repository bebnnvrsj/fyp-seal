<?php
session_start();
if (!isset($_SESSION['role'])) {
    header("Location: ../login/login.php");
    exit();
}
require 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userID = $_SESSION['userID'];
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // 1. Validasi Input (Match Check)
    if ($new_password !== $confirm_password) {
        $_SESSION['msg'] = "New password and confirmation do not match!";
        $_SESSION['msg_type'] = "error";
        header("Location: profile.php");
        exit();
    }

    // 2. Semak syarat keselamatan Password (Regex)
    $regex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{12,}$/';
    if (!preg_match($regex, $new_password)) {
        $_SESSION['msg'] = "Password must be 12+ chars with uppercase, lowercase, number, and symbol.";
        $_SESSION['msg_type'] = "error";
        header("Location: profile.php");
        exit();
    }

    // 3. Verifikasi Password Semasa
    $sql = "SELECT password FROM users WHERE userID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($current_password, $user['password'])) {
        // Hashing password baru
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Mula Transaksi untuk DB Consistency
        $conn->begin_transaction();

        try {
            // Update Password
            $update_sql = "UPDATE users SET password = ? WHERE userID = ?";
            $up_stmt = $conn->prepare($update_sql);
            $up_stmt->bind_param("si", $hashed_password, $userID);
            $up_stmt->execute();

            $conn->commit();
            $_SESSION['msg'] = "Password Updated Successfully!";
            $_SESSION['msg_type'] = "success";

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['msg'] = "System error. Please try again later.";
            $_SESSION['msg_type'] = "error";
        }
    } else {
        $_SESSION['msg'] = "Current password is incorrect!";
        $_SESSION['msg_type'] = "error";
    }

    $conn->close();
    header("Location: profile.php");
    exit();
}