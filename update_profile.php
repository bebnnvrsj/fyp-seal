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

    // Begin isolated safe database transaction blocks
    $conn->begin_transaction();

    try {
        // Update specialized child profile table fields depending on active login role scopes
        if ($role == 'doctor') {
            $mmc_number = mysqli_real_escape_string($conn, $_POST['mmc_number']);
            $sql = "UPDATE doctor_profiles SET name = ?, mmc_number = ? WHERE doctorID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $name, $mmc_number, $userID);
        } elseif ($role == 'admin') {
            $sql = "UPDATE admin_profiles SET name = ? WHERE adminID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $name, $userID);
        } else {
            // Role: Verifier
            $organization_name = mysqli_real_escape_string($conn, $_POST['organization_name']);
            $sql = "UPDATE verifier_profiles SET name = ?, organization_name = ? WHERE verifierID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $name, $organization_name, $userID);
        }

        $stmt->execute();

        // Commit all safe structural transactions permanently to MySQL storage logs
        $conn->commit();

        // Update name in session to instantly synchronize frontend dashboards
        $_SESSION['name'] = $name;

        $_SESSION['msg'] = "Profile Updated Successfully!";
        $_SESSION['msg_type'] = "success";

    } catch (Exception $e) {
        // Rollback operations in case of any database runtime faults
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