<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['role'])) {
    header("Location: ../login/login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userID = $_SESSION['userID'];
    $role = $_SESSION['role'];
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $phone_number = mysqli_real_escape_string($conn, $_POST['phone_number']);

    // Mulakan Transaksi untuk pastikan kedua-dua jadual dikemas kini serentak
    $conn->begin_transaction();

    try {
        // 1. Kemas kini jadual 'users' (untuk phone_number)
        $sql1 = "UPDATE users SET phone_number = ? WHERE userID = ?";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->bind_param("si", $phone_number, $userID);
        $stmt1->execute();

        // 2. Kemas kini jadual profil berdasarkan ROLE
        if ($role == 'doctor') {
            $mmc_number = mysqli_real_escape_string($conn, $_POST['mmc_number']);
            $sql2 = "UPDATE doctor_profiles SET name = ?, mmc_number = ? WHERE doctorID = ?";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bind_param("ssi", $name, $mmc_number, $userID);
        } elseif ($role == 'admin') {
            $sql2 = "UPDATE admin_profiles SET name = ? WHERE adminID = ?";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bind_param("si", $name, $userID);
        } else {
            // Role: Verifier
            $sql2 = "UPDATE verifier_profiles SET name = ? WHERE verifierID = ?";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bind_param("si", $name, $userID);
        }

        $stmt2->execute();

        // Jika sampai sini tanpa ralat, simpan perubahan secara kekal
        $conn->commit();

        // Kemas kini nama dalam session supaya paparan di dashboard berubah
        $_SESSION['name'] = $name;

        $_SESSION['msg'] = "Profile Updated Successfully!";
        $_SESSION['msg_type'] = "success";

    } catch (Exception $e) {
        // Jika ada ralat, batalkan semua perubahan dalam transaksi
        $conn->rollback();
        $_SESSION['msg'] = "Error updating profile: " . $e->getMessage();
        $_SESSION['msg_type'] = "error";
    }

    $conn->close();
    header("Location: profile.php");
    exit();
} else {
    header("Location: ../login/login.php");
    exit();
}
?>