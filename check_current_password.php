<?php
session_start();
require 'db_connect.php';

// Pastikan hanya permintaan POST yang diproses
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['current_password'])) {
    $userID = $_SESSION['userID'];
    $current_password = $_POST['current_password']; // Jangan trim di sini jika password asal ada space

    // Ambil hash password dari jadual users
    $sql = "SELECT password FROM users WHERE userID = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("i", $userID);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            // Verifikasi password input dengan hash di database
            if (password_verify($current_password, $user['password'])) {
                echo 'match';
            } else {
                echo 'nomatch';
            }
        } else {
            echo 'nomatch';
        }
        $stmt->close();
    }
}
$conn->close();
exit(); // Pastikan tiada output lain selepas ini
?>