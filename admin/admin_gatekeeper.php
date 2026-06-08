<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Semak jika pengguna sudah log masuk
if (!isset($_SESSION['userID'])) {
    header("Location: ../login/login.php");
    exit();
}

// 2. Semak jika role pengguna adalah 'admin'
if ($_SESSION['role'] !== 'admin') {
    // Jika bukan admin, hantar ke dashboard asal mereka atau keluar
    echo "<script>alert('Access Denied: Admin Only!'); window.location.href='../login/login.php';</script>";
    exit();
}
?>