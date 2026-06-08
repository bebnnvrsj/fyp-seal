<?php
session_start();
require '../db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Ambil token dari hidden input form untuk pastikan pautan cross-device kekal sah jika ralat berlaku
    $token = isset($_POST['token']) ? mysqli_real_escape_string($conn, $_POST['token']) : '';

    // Pastikan session emel wujud (telah diset oleh pengesahan token di reset_pw.php)
    if (!isset($_SESSION['reset_email'])) {
        $_SESSION['error'] = "Session expired. Please restart the password recovery process.";
        header("Location: forgot_pw.php");
        exit;
    }

    $email = $_SESSION['reset_email'];

    // 1. Check whether passwords match
    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: reset_pw.php?token=" . urlencode($token));
        exit;
    }

    // 2. Implement password strength validation
    $regex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&_])[A-Za-z\d@$!%*?&_]{12,}$/';
    
    if (!preg_match($regex, $password)) {
        $_SESSION['error'] = "Password must be 12+ chars with uppercase, lowercase, number, and symbol.";
        header("Location: reset_pw.php?token=" . urlencode($token));
        exit;
    }

    // 3. If validation passes, hash the password, update database, and CLEAR token data
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Set reset_token dan token_expires kepada NULL supaya token tidak boleh diguna semula (One-time use security)
    $sql = "UPDATE users SET password = ?, reset_token = NULL, token_expires = NULL WHERE username = ?"; 
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $hashed_password, $email);

    if ($stmt->execute()) {
        unset($_SESSION['reset_email']); // Bersihkan session pelayan selepas berjaya
        header("Location: login.php?status=reset_success");
    } else {
        $_SESSION['error'] = "Failed to update password.";
        header("Location: reset_pw.php?token=" . urlencode($token));
    }
    exit;
} else {
    header("Location: forgot_pw.php");
    exit;
}
?>