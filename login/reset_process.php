<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_pw.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $email = $_SESSION['reset_email'];

    // 1. Semakan kesepadanan
    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: reset_pw.php");
        exit;
    }

    // 2. Implementasi REGEX Ketat (12+ aksara, Besar, Kecil, Nombor, Simbol)
    $regex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&_]{12,}$/';
    
    if (!preg_match($regex, $password)) {
        $_SESSION['error'] = "Password must be 12+ chars with uppercase, lowercase, number, and symbol.";
        header("Location: reset_pw.php");
        exit;
    }

    // 3. Jika lulus, lakukan hashing dan kemaskini DB[cite: 11]
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $sql = "UPDATE users SET password = ? WHERE username = ?"; // Guna 'username' ikut skema anda
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $hashed_password, $email);

    if ($stmt->execute()) {
        unset($_SESSION['reset_email']);
        header("Location: login.php?status=reset_success");
    } else {
        $_SESSION['error'] = "Failed to update password.";
        header("Location: reset_pw.php");
    }
    exit;
}