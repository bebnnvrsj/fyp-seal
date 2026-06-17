<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in
if (!isset($_SESSION['userID'])) {
    header("Location: ../login/login.php");
    exit();
}

// 2. Check if the user's role is 'admin'
if ($_SESSION['role'] !== 'admin') {
    // If not admin, redirect to their original dashboard or logout
    echo "<script>alert('Access Denied: Admin Only!'); window.location.href='../login/login.php';</script>";
    exit();
}
?>