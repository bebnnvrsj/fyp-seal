<?php
session_start();
require 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'doctor') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wallet_address'])) {
    $userID = $_SESSION['userID'];
    $walletAddress = trim($_POST['wallet_address']);

    // Validasi format alamat Ethereum (42 aksara, bermula dengan 0x)
    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $walletAddress)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid wallet address format.']);
        exit();
    }

    // Kemaskini walletAddress dalam jadual doctor_profiles
    $sql = "UPDATE doctor_profiles SET walletAddress = ? WHERE doctorID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $walletAddress, $userID);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Wallet linked successfully!']);
    } else {
        // Semak jika alamat wallet sudah digunakan oleh doktor lain (Unique Constraint)
        if ($conn->errno === 1062) {
            echo json_encode(['status' => 'error', 'message' => 'This wallet address is already linked to another account.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database update failed: ' . $conn->error]);
        }
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
}
?>