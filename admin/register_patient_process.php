<?php
session_start();
require '../db_connect.php'; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = strtoupper(mysqli_real_escape_string($conn, $_POST['full_name']));
    $ic_passport = mysqli_real_escape_string($conn, $_POST['ic_passport']);
    $matric_staff_no = mysqli_real_escape_string($conn, $_POST['matric_staff_no']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);

    // Semak jika IC atau No Matrik sudah wujud
    $check = "SELECT patientID FROM patients WHERE ic_passport = ? OR matric_staff_no = ?";
    $stmt_check = $conn->prepare($check);
    $stmt_check->bind_param("ss", $ic_passport, $matric_staff_no);
    $stmt_check->execute();
    $result = $stmt_check->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['msg'] = "Error: Patient IC or Matric Number already exists.";
        header("Location: register_patient.php?status=exists");
    } else {
        $sql = "INSERT INTO patients (full_name, ic_passport, matric_staff_no, email) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $full_name, $ic_passport, $matric_staff_no, $email);

        if ($stmt->execute()) {
            header("Location: register_patient.php?status=success");
        } else {
            header("Location: register_patient.php?status=failed");
        }
    }
    exit();
}
?>