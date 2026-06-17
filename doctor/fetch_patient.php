<?php
require '../db_connect.php';

if(isset($_POST['matric_no'])) {
    $id = mysqli_real_escape_string($conn, $_POST['matric_no']);
    
    // Find patient details based on matric number or staff number
    $sql = "SELECT full_name, ic_passport, email FROM patients WHERE matric_staff_no = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if($row = $result->fetch_assoc()) {
        echo json_encode([
            'status' => 'success',
            'full_name' => $row['full_name'],
            'ic_passport' => $row['ic_passport'],
            'email' => $row['email']
        ]);
    } else {
        echo json_encode(['status' => 'error']);
    }
}
?>